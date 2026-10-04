<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use App\Support\DistrictMatch;
use Database\Seeders\CommandPrototypeSeeder;
use Database\Seeders\Support\CommandPrototypeData;
use Illuminate\Support\Facades\Artisan;

/**
 * @return array<string, User>
 */
function commandApiUsers(): array
{
    $admin = User::factory()->create(['email' => 'admin@tbai.test']);
    ProgrammeAssignment::factory()->create(['user_id' => $admin->id, 'role' => ProgrammeRole::TbaiAdmin]);

    app()['env'] = 'local';
    config(['tbai.features.drivers' => true, 'tbai.features.interventions' => true, 'tbai.features.incidence' => true, 'tbai.features.ai_tests' => true]);
    Artisan::call('db:seed', ['--class' => CommandPrototypeSeeder::class, '--force' => true]);

    $country = Country::query()->where('slug', 'pakistan')->firstOrFail();
    $sindh = Province::query()->where('slug', 'sindh')->firstOrFail();
    $punjab = Province::query()->where('slug', 'punjab')->firstOrFail();
    $karachiWest = City::query()->where('slug', 'karachi-west')->firstOrFail();
    $sukkur = City::query()->where('slug', 'sukkur')->firstOrFail();
    $users = ['admin' => $admin];

    foreach ([
        'country' => [ProgrammeRole::CountryViewer, ScopeType::Country, $country->id],
        'sindh' => [ProgrammeRole::ProvincialViewer, ScopeType::Province, $sindh->id],
        'punjab' => [ProgrammeRole::ProvincialViewer, ScopeType::Province, $punjab->id],
        'karachi_west' => [ProgrammeRole::CityOfficer, ScopeType::City, $karachiWest->id],
        'sukkur' => [ProgrammeRole::CityOfficer, ScopeType::City, $sukkur->id],
    ] as $key => [$role, $type, $scopeId]) {
        $users[$key] = User::factory()->create();
        ProgrammeAssignment::factory()->create(['user_id' => $users[$key]->id, 'role' => $role, 'scope_type' => $type, 'scope_id' => $scopeId]);
    }

    $team = Team::factory()->create();
    $users['staff'] = User::factory()->create();
    $team->members()->attach($users['staff']->id, ['role' => 'member']);
    Facility::factory()->create(['team_id' => $team->id, 'city_id' => $karachiWest->id]);

    return $users;
}

const COMMAND_API_PATHS = [
    '/api/command/frame',
    '/api/command/cumulative',
    '/api/command/intelligence',
    '/api/command/sectors/public',
    '/api/command/sectors/private',
    '/api/command/sectors/ppm',
    '/api/command/map',
    '/api/command/drivers',
    '/api/command/ai-tests',
    '/api/command/incidence',
];

test('every command api endpoint answers 401 JSON when signed out', function () {
    foreach (COMMAND_API_PATHS as $path) {
        $this->getJson($path)->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    }

    $this->get('/api/command/cumulative')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
});

test('facility staff get 403 JSON on every command api endpoint', function () {
    $users = commandApiUsers();

    foreach (COMMAND_API_PATHS as $path) {
        $this->actingAs($users['staff'])->getJson($path)->assertForbidden()->assertJsonStructure(['message']);
    }

    $city = City::query()->where('slug', 'karachi-west')->firstOrFail();
    $this->actingAs($users['staff'])->getJson("/api/command/sectors/public/districts/{$city->id}/bmus")->assertForbidden();
});

test('admin sees the prototype headline, four alerts and the same shape every role gets', function () {
    $users = commandApiUsers();
    $response = $this->actingAs($users['admin'])->getJson('/api/command/cumulative');

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonStructure([
            'meta' => ['scope_name', 'filters' => ['sector', 'province', 'district', 'from', 'to'], 'has_returns', 'generated_at'],
            'data' => ['cascade' => [['key', 'label', 'value']], 'performance' => ['rings', 'tiles'], 'months', 'table'],
        ])
        ->assertJsonPath('meta.scope_name', 'Sindh')
        ->assertJsonPath('data.cascade.0.value', 236271)
        ->assertJsonPath('data.cascade.1.value', 211103)
        ->assertJsonPath('data.cascade.2.value', 30737)
        ->assertJsonPath('data.cascade.3.value', 180366)
        ->assertJsonPath('data.cascade.4.value', 28004)
        ->assertJsonPath('data.cascade.5.value', 66826)
        ->assertJsonPath('data.performance.rings.0.value', 14.5602)
        ->assertJsonPath('data.performance.tiles.1.value', 143);

    expect($response->json('data.months'))->toHaveCount(10)
        ->and(collect($response->json('data.months'))->last()['tested'])->toBe(211103)
        ->and($response->json('data.table'))->toHaveCount(11);

    $alerts = $this->actingAs($users['admin'])->getJson('/api/command/intelligence')->assertOk();

    expect(collect($alerts->json('data.alert_center.lines'))->pluck('severity')->all())->toBe(['critical', 'attention', 'emerging', 'improvement'])
        ->and($alerts->json('data.alert_center.lines.0.text'))->toBe('2,733 diagnosed patients have no treatment-start record')
        ->and($alerts->json('data.alert_center.lines.1.values.ratio'))->toBe(2.3)
        ->and($alerts->json('data.alert_center.lines.1.subject.district.name'))->toBe('Umerkot')
        ->and($alerts->json('data.alert_center.lines.2.subject.name'))->toBe('Karachi Central')
        ->and($alerts->json('data.alert_center.lines.3.values'))->toBe(['from_days' => 3.2, 'to_days' => 1.4])
        ->and($alerts->json('data.top8'))->toHaveCount(3)
        ->and($alerts->json('data.top8.0.items.0.name'))->toBe('Karachi Central')
        ->and($alerts->json('data.top8.0.items'))->toHaveCount(8)
        ->and($alerts->json('data.top8.2.items.0.value'))->toBe(385);
});

test('sector pages add up to the programme total and sort', function () {
    $users = commandApiUsers();
    $sum = 0;

    foreach (['public', 'private', 'ppm'] as $sector) {
        $response = $this->actingAs($users['admin'])->getJson("/api/command/sectors/{$sector}")->assertOk()
            ->assertJsonPath('meta.sector', $sector)
            ->assertJsonPath('meta.sort.key', 'suspects')
            ->assertJsonStructure(['data' => ['columns', 'rows' => [['city_id', 'district', 'match_key', 'suspects', 'tested', 'positive', 'positivity', 'started_pct', 'success_pct', 'ltfu_pct', 'tat_days', 'mdr_rr', 'ai_tests', 'bmus']], 'total', 'row_action']]);
        $sum += $response->json('data.total.suspects');

        expect($response->json('data.rows'))->toHaveCount(29)
            ->and(collect($response->json('data.rows'))->sum('suspects'))->toBe($response->json('data.total.suspects'));
    }

    expect($sum)->toBe(236271);

    $byName = $this->actingAs($users['admin'])->getJson('/api/command/sectors/public?sort=district&direction=asc')->assertOk();
    $names = collect($byName->json('data.rows'))->pluck('district')->all();
    $sorted = $names;
    natcasesort($sorted);

    expect($names)->toBe(array_values($sorted))
        ->and($byName->json('data.total.bmus'))->toBe(163);

    $this->actingAs($users['admin'])->getJson('/api/command/sectors/bogus')->assertNotFound();
    $this->actingAs($users['admin'])->getJson('/api/command/sectors/public?sort=nope')->assertUnprocessable();
});

test('bmu drill down lists each site once with its months', function () {
    $users = commandApiUsers();
    $city = City::query()->where('slug', 'jacobabad')->firstOrFail();
    $response = $this->actingAs($users['admin'])->getJson("/api/command/sectors/public/districts/{$city->id}/bmus")->assertOk();

    expect($response->json('data.district.match_key'))->toBe('jacobabad')
        ->and($response->json('data.bmus'))->not->toBeEmpty()
        ->and($response->json('data.bmus.0.label'))->toBe('BMU 1')
        ->and($response->json('data.bmus.0.months'))->toHaveCount(10)
        ->and($response->json('data.summary.suspects'))->toBe(collect($response->json('data.bmus'))->sum('suspects'))
        ->and(collect($response->json('data.bmus'))->sum('share_of_district_positive'))->toBeGreaterThan(95);

    $this->actingAs($users['admin'])->getJson('/api/command/sectors/public/districts/999999/bmus')->assertNotFound();
});

test('city officers, provincial viewers and country viewers only get their scope', function () {
    $users = commandApiUsers();
    $west = City::query()->where('slug', 'karachi-west')->firstOrFail();
    $sukkur = City::query()->where('slug', 'sukkur')->firstOrFail();

    $frame = $this->actingAs($users['karachi_west'])->getJson('/api/command/frame')->assertOk()
        ->assertJsonPath('data.scope.level', 'district')
        ->assertJsonPath('data.scope.locks.district', true)
        ->assertJsonPath('data.scope.district_id', $west->id);
    expect($frame->json('data.options.districts'))->toHaveCount(1);

    $cumulative = $this->actingAs($users['karachi_west'])->getJson("/api/command/cumulative?district={$sukkur->id}")->assertOk();
    expect($cumulative->json('meta.scope_name'))->toBe('Karachi West')
        ->and($cumulative->json('data.cascade.0.value'))->toBe(24446);

    $rows = $this->actingAs($users['karachi_west'])->getJson('/api/command/sectors/public')->assertOk();
    expect($rows->json('data.rows'))->toHaveCount(1)
        ->and($rows->json('data.total.district'))->toBe('Karachi West');

    expect($this->actingAs($users['karachi_west'])->getJson('/api/command/map')->json('data.districts'))->toHaveCount(1);
    $this->actingAs($users['karachi_west'])->getJson("/api/command/sectors/public/districts/{$sukkur->id}/bmus")->assertForbidden();
    $this->actingAs($users['karachi_west'])->getJson("/api/command/sectors/public/districts/{$west->id}/bmus")->assertOk();

    $top = $this->actingAs($users['karachi_west'])->getJson('/api/command/intelligence')->assertOk();
    foreach ($top->json('data.top8') as $chart) {
        expect($chart['items'])->toHaveCount(1);
    }
    foreach ($top->json('data.alert_center.all') as $alert) {
        expect($alert['subject']['type'] === 'scope' || ($alert['subject']['district']['name'] ?? $alert['subject']['name']) === 'Karachi West')->toBeTrue();
    }

    expect(collect($this->actingAs($users['karachi_west'])->getJson('/api/command/ai-tests')->json('data.patients'))->pluck('id')->all())->toBe(['P-1042'])
        ->and(collect($this->actingAs($users['sukkur'])->getJson('/api/command/ai-tests')->json('data.patients'))->pluck('id')->all())->toBe(['P-1045'])
        ->and(collect($this->actingAs($users['sindh'])->getJson('/api/command/ai-tests')->json('data.patients'))->pluck('id')->all())->toHaveCount(4)
        ->and(collect($this->actingAs($users['country'])->getJson('/api/command/ai-tests')->json('data.patients'))->pluck('id')->all())->toHaveCount(4);

    $sindhFrame = $this->actingAs($users['sindh'])->getJson('/api/command/frame')->assertOk()
        ->assertJsonPath('data.scope.level', 'province')
        ->assertJsonPath('data.scope.locks.province', true)
        ->assertJsonPath('data.scope.locks.district', false);
    expect($sindhFrame->json('data.options.provinces'))->toHaveCount(1)
        ->and($this->actingAs($users['sindh'])->getJson('/api/command/cumulative?province=all')->json('data.cascade.0.value'))->toBe(236271);

    $punjab = $this->actingAs($users['punjab'])->getJson('/api/command/cumulative')->assertOk();
    expect($punjab->json('meta.has_returns'))->toBeFalse()
        ->and($punjab->json('data.cascade.0.value'))->toBe(0)
        ->and($punjab->json('data.performance.rings.0.value'))->toBeNull()
        ->and($this->actingAs($users['punjab'])->getJson('/api/command/map')->json('data.districts'))->toHaveCount(0);

    $country = $this->actingAs($users['country'])->getJson('/api/command/frame')->assertOk()
        ->assertJsonPath('data.scope.level', 'country')
        ->assertJsonPath('data.scope.locks.province', false);
    expect($country->json('data.options.provinces'))->not->toBeEmpty();
});

test('map, drivers and incidence honour the contract', function () {
    $users = commandApiUsers();
    $map = $this->actingAs($users['admin'])->getJson('/api/command/map?indicator=treatment_gap&selected=7')->assertOk();

    expect($map->json('meta.indicator'))->toBe('treatment_gap')
        ->and($map->json('data.districts'))->toHaveCount(29)
        ->and($map->json('data.districts.0.trend12'))->toHaveCount(12)
        ->and($map->json('data.trend_months'))->toHaveCount(12)
        ->and($map->json('data.pulse_top3'))->toHaveCount(3)
        ->and($map->json('data.top5'))->toHaveCount(5)
        ->and($map->json('data.indicators'))->toHaveCount(7)
        ->and($map->json('data.scale.min'))->toBeLessThanOrEqual($map->json('data.scale.max'))
        ->and($map->json('data.selected'))->toBe(City::query()->where('slug', 'karachi-west')->value('id'));

    $ppm = $this->actingAs($users['admin'])->getJson('/api/command/map?sector=ppm')->assertOk();
    expect($ppm->json('meta.sector'))->toBe('ppm');

    $drivers = $this->actingAs($users['admin'])->getJson('/api/command/drivers')->assertOk();
    expect($drivers->json('data.variables'))->toHaveCount(11)
        ->and(collect($drivers->json('data.variables'))->every(fn (array $v): bool => $v['available']))->toBeTrue()
        ->and($drivers->json('data.categories'))->toHaveCount(6)
        ->and($drivers->json('data.categories.0.priority.0.name'))->toBe('Umerkot')
        ->and($drivers->json('data.categories.4.lowest_is_worst'))->toBeTrue()
        ->and($drivers->json('data.categories.0.responses'))->toHaveCount(2);

    $incidence = $this->actingAs($users['admin'])->getJson('/api/command/incidence')->assertOk();
    expect($incidence->json('meta.unit'))->toBeNull()
        ->and($incidence->json('data.monthly'))->toHaveCount(10)
        ->and($incidence->json('data.yearly'))->toHaveCount(5)
        ->and($incidence->json('data.monthly.9.value'))->toBe(45);

    $this->actingAs($users['sindh'])->getJson('/api/command/incidence')->assertOk()->assertJsonPath('meta.province.name', 'Sindh');
});

test('turning flags off removes the pages and every AI figure', function () {
    $users = commandApiUsers();
    config(['tbai.features.drivers' => false, 'tbai.features.interventions' => false, 'tbai.features.incidence' => false, 'tbai.features.ai_tests' => false]);

    foreach (['drivers', 'incidence', 'ai-tests'] as $page) {
        $this->actingAs($users['admin'])->getJson("/api/command/{$page}")->assertNotFound();
    }

    $frame = $this->actingAs($users['admin'])->getJson('/api/command/frame')->assertOk();
    expect(collect($frame->json('data.screens'))->pluck('key')->all())->toBe(['cumulative', 'intelligence', 'public', 'private', 'ppm', 'map'])
        ->and($frame->json('data.flags'))->toBe(['drivers' => false, 'interventions' => false, 'incidence' => false, 'ai_tests' => false]);

    $cumulative = $this->actingAs($users['admin'])->getJson('/api/command/cumulative');
    expect($cumulative->json('data.cascade.5.value'))->toBeNull()
        ->and($this->actingAs($users['admin'])->getJson('/api/command/sectors/public')->json('data.total.ai_tests'))->toBeNull()
        ->and(collect($this->actingAs($users['admin'])->getJson('/api/command/map')->json('data.indicators'))->pluck('key')->all())->not->toContain('ai_tests');

    config(['tbai.features.drivers' => true, 'tbai.features.interventions' => false]);
    $drivers = $this->actingAs($users['admin'])->getJson('/api/command/drivers')->assertOk();
    expect($drivers->json('data.categories.0.responses'))->toBe([])
        ->and($drivers->json('data.categories.0.priority'))->toBe([]);
});

test('with no flag set the flagged endpoints answer 404 and stay out of frame.screens', function () {
    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create(['user_id' => $admin->id, 'role' => ProgrammeRole::TbaiAdmin]);

    expect(config('tbai.features.drivers'))->toBeFalse()
        ->and(config('tbai.features.interventions'))->toBeFalse()
        ->and(config('tbai.features.incidence'))->toBeFalse()
        ->and(config('tbai.features.ai_tests'))->toBeFalse();

    foreach (['drivers', 'incidence', 'ai-tests'] as $page) {
        $this->actingAs($admin)->getJson("/api/command/{$page}")->assertNotFound();
    }

    $frame = $this->actingAs($admin)->getJson('/api/command/frame')->assertOk();
    expect(collect($frame->json('data.screens'))->pluck('key')->all())->not->toContain('drivers', 'incidence', 'ai_tests', 'ai-tests')
        ->and($frame->json('data.flags'))->toBe(['drivers' => false, 'interventions' => false, 'incidence' => false, 'ai_tests' => false]);
});

test('an empty database answers nulls and zeros, never an error', function () {
    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create(['user_id' => $admin->id, 'role' => ProgrammeRole::TbaiAdmin]);
    config(['tbai.features.drivers' => true, 'tbai.features.interventions' => true, 'tbai.features.incidence' => true, 'tbai.features.ai_tests' => true]);

    foreach (['frame', 'cumulative', 'intelligence', 'sectors/public', 'map', 'drivers', 'ai-tests'] as $path) {
        $this->actingAs($admin)->getJson("/api/command/{$path}")->assertOk();
    }

    $this->actingAs($admin)->getJson('/api/command/incidence')->assertNotFound();

    $cumulative = $this->actingAs($admin)->getJson('/api/command/cumulative')->json();

    expect($cumulative['meta']['has_returns'])->toBeFalse()
        ->and($cumulative['data']['performance']['rings'][0]['value'])->toBeNull()
        ->and($cumulative['data']['performance']['rings'][0]['status'])->toBe('not_enough_returns');
});

test('every district All row equals the prototype All table and the headline rates are the prototype means', function () {
    $users = commandApiUsers();
    $all = CommandPrototypeData::dataset('all');

    $map = $this->actingAs($users['admin'])->getJson('/api/command/map')->assertOk();
    $byKey = collect($map->json('data.districts'))->keyBy('match_key');
    expect($byKey)->toHaveCount(29);

    foreach ($all as $row) {
        $d = $byKey->get(DistrictMatch::key((string) $row['name']));

        expect($d)->not->toBeNull()
            ->and([$d['suspects'], $d['tested'], $d['positive'], $d['mdr_rr'], $d['ai_tests'], $d['bmus']])
            ->toBe([$row['sus'], $row['tested'], $row['pos'], $row['mdr'], $row['ai'], $row['bmu']], (string) $row['name'])
            ->and($d['treatment_gap'])->toBe($row['pos'] - $row['st'])
            ->and([round($d['positivity'], 1), round($d['tat_days'], 1)])->toBe([round($row['pos'] / $row['tested'] * 100, 1), round($row['tat'], 1)], (string) $row['name']);
    }

    // The Intelligence charts print what the All table prints, from unrounded values.
    $top = $this->actingAs($users['admin'])->getJson('/api/command/intelligence')->assertOk();
    $slowest = $top->json('data.top8.1.items');
    expect($slowest[0]['name'])->toBe('Umerkot')
        ->and(round($slowest[0]['value'], 1))->toBe(4.1)
        ->and($slowest[0]['value'])->not->toBe(4.1)
        ->and(array_column($top->json('data.top8.0.items'), 'name'))->toContain('Karachi Central', 'Karachi West')
        ->and($top->json('data.alert_center.lines.0.map.indicator'))->toBe('treatment_gap');

    $cumulative = $this->actingAs($users['admin'])->getJson('/api/command/cumulative')->assertOk();
    expect(array_map(fn ($v): float => round((float) $v, 2), array_column($cumulative->json('data.performance.rings'), 'value')))->toBe([14.56, 87.65, 7.95])
        ->and(round((float) $cumulative->json('data.performance.tiles.0.value'), 2))->toBe(3.08)
        ->and($cumulative->json('data.performance.tiles.1.value'))->toBe(143);
});
