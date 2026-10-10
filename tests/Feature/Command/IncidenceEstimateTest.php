<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\IncidenceEstimate;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\IncidenceEstimateSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => enableFeature('incidence'));

test('after seeding the national card shows the seeded rate, source and method', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $this->seed(IncidenceEstimateSeeder::class);

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scope', 'All')
            ->where('estimate.label', 'Estimated TB Incidence Rate')
            ->where('estimate.rate', 292)
            ->where('estimate.year', 2026)
            ->where('estimate.scope', 'national_reference')
            ->where('estimate.sub', '(Pakistan national estimate, 2026)')
            ->where('estimate.message', null)
            ->where('estimate.source', 'WHO estimate for Pakistan (per WHO guidelines), 292 per 100,000')
            ->where('estimate.method', 'WHO estimated incidence (new and relapse cases)')
            ->where('estimate.status', null));
});

test('an admin can change the estimate source and year and reseeding the seeder does not revert it', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $this->seed(IncidenceEstimateSeeder::class);

    $this->actingAs($admin)
        ->post(route('command.incidence.estimates.store'), [
            'area_type' => 'country',
            'year' => 2024,
            'rate_per_100k' => 292.00,
            'source' => 'Named source',
            'method' => 'A named method',
        ])
        ->assertRedirect();

    $this->seed(IncidenceEstimateSeeder::class);

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.rate', 292)
            ->where('estimate.year', 2024)
            ->where('estimate.source', 'Named source')
            ->where('estimate.method', 'A named method')
            ->where('estimate.sub', '(Pakistan national estimate, 2024)'));

    expect(IncidenceEstimate::query()->where('area_type', 'country')->whereNull('area_id')->count())
        ->toBe(1)
        ->and(IncidenceEstimate::query()->where('area_type', 'country')->whereNull('area_id')->value('year'))
        ->toBe(2024);
});

test('sindh and district selections fall back to the national row with the right note, and a sindh row wins once entered', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $this->seed(IncidenceEstimateSeeder::class);

    $country = Country::query()->where('slug', 'pakistan')->firstOrFail();
    $sindh = Province::factory()->create(['country_id' => $country->id, 'name' => 'Sindh']);
    $karachi = City::factory()->create(['province_id' => $sindh->id, 'name' => 'Karachi']);

    $this->actingAs($admin)
        ->get(route('command.index', ['province' => $sindh->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.rate', 292)
            ->where('estimate.scope', 'national_reference')
            ->where('estimate.sub', 'National estimate (Pakistan), not a Sindh rate')
            ->where('estimate.message', 'National estimate (Pakistan), not a Sindh rate'));

    $this->actingAs($admin)
        ->get(route('command.index', ['province' => $sindh->id, 'district' => $karachi->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.rate', 292)
            ->where('estimate.sub', 'National estimate (Pakistan), not a district rate')
            ->where('estimate.message', 'National estimate (Pakistan), not a district rate'));

    $this->actingAs($admin)
        ->post(route('command.incidence.estimates.store'), [
            'area_type' => 'province',
            'area_id' => $sindh->id,
            'year' => 2026,
            'rate_per_100k' => 300.00,
            'source' => 'Named source',
            'method' => 'A named method',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('command.index', ['province' => $sindh->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.rate', 300)
            ->where('estimate.scope', 'own')
            ->where('estimate.sub', '(Sindh, 2026)')
            ->where('estimate.message', null));

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.rate', 292)
            ->where('estimate.scope', 'national_reference'));
});

test('all scope keeps the pakistan label, and a scoped user without an own row is told it is not their rate', function () {
    $this->seed(IncidenceEstimateSeeder::class);
    $country = Country::query()->where('slug', 'pakistan')->firstOrFail();
    $sindh = Province::factory()->create(['country_id' => $country->id, 'name' => 'Sindh']);
    $karachi = City::factory()->create(['province_id' => $sindh->id, 'name' => 'Karachi West']);

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('command.index', ['from' => '2024-01-01', 'to' => '2024-12-31']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scope', 'All')
            ->where('estimate.rate', 292)
            ->where('estimate.year', 2026)
            ->where('estimate.status', null)
            ->where('estimate.sub', '(Pakistan national estimate, 2026)'));

    $countryViewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $countryViewer->id,
        'role' => ProgrammeRole::CountryViewer,
        'scope_type' => ScopeType::Country,
        'scope_id' => $country->id,
    ]);

    $this->actingAs($countryViewer)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.rate', 292)
            ->where('estimate.sub', '(Pakistan national estimate, 2026)')
            ->where('estimate.message', null));

    $provincial = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $provincial->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $sindh->id,
    ]);

    $this->actingAs($provincial)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.rate', 292)
            ->where('estimate.sub', 'National estimate (Pakistan), not a Sindh rate'));

    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $karachi->id,
    ]);

    $this->actingAs($officer)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.rate', 292)
            ->where('estimate.sub', 'National estimate (Pakistan), not a district rate'));
});

test('an admin can see and edit the seeded estimate, and reseeding does not overwrite it', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $this->seed(IncidenceEstimateSeeder::class);

    $this->actingAs($admin)
        ->get(route('command.incidence'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('estimates', 1)
            ->where('estimates.0.area', 'Pakistan')
            ->where('estimates.0.year', 2026)
            ->where('estimates.0.rate', '292')
            ->where('estimates.0.source', 'WHO estimate for Pakistan (per WHO guidelines), 292 per 100,000')
            ->where('estimates.0.method', 'WHO estimated incidence (new and relapse cases)'));

    $id = IncidenceEstimate::query()->where('area_type', 'country')->whereNull('area_id')->value('id');

    $this->actingAs($admin)
        ->post(route('command.incidence.estimates.store'), [
            'id' => $id,
            'area_type' => 'country',
            'year' => 2024,
            'rate_per_100k' => 292,
            'source' => 'Named source',
            'method' => 'A named method',
        ])
        ->assertRedirect();

    $this->seed(IncidenceEstimateSeeder::class);

    expect(IncidenceEstimate::query()->where('area_type', 'country')->whereNull('area_id')->count())->toBe(1)
        ->and(IncidenceEstimate::query()->whereKey($id)->value('year'))->toBe(2024)
        ->and(IncidenceEstimate::query()->whereKey($id)->value('source'))->toBe('Named source')
        ->and(IncidenceEstimate::query()->whereKey($id)->value('method'))->toBe('A named method');
});

test('store validates source, method and area and only admins can save an estimate', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $resolver = route('command.incidence.estimates.store');

    $this->actingAs($admin)
        ->from(route('command.incidence'))
        ->post($resolver, ['area_type' => 'country', 'year' => 2026, 'rate_per_100k' => 292.00])
        ->assertSessionHasErrors(['source', 'method']);

    $country = Country::factory()->create();
    $province = Province::factory()->create(['country_id' => $country->id]);

    $this->actingAs($admin)
        ->from(route('command.incidence'))
        ->post($resolver, [
            'area_type' => 'country',
            'area_id' => $province->id,
            'year' => 2026,
            'rate_per_100k' => 292.00,
            'source' => 'A source',
            'method' => 'A method',
        ])
        ->assertSessionHasErrors('area_id');

    $this->actingAs($admin)
        ->from(route('command.incidence'))
        ->post($resolver, [
            'area_type' => 'province',
            'year' => 2026,
            'rate_per_100k' => 292.00,
            'source' => 'A source',
            'method' => 'A method',
        ])
        ->assertSessionHasErrors('area_id');

    expect(IncidenceEstimate::query()->count())->toBe(0);

    $city = City::factory()->create(['province_id' => $province->id]);
    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $city->id,
    ]);

    $this->actingAs($officer)
        ->post($resolver, [
            'area_type' => 'country',
            'year' => 2026,
            'rate_per_100k' => 292.00,
            'source' => 'A source',
            'method' => 'A method',
        ])
        ->assertForbidden();

    expect(IncidenceEstimate::query()->count())->toBe(0);
});

test('with no estimate rows the card says no approved estimate entered', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.status', 'no_estimate')
            ->where('estimate.rate', null)
            ->where('estimate.message', 'No approved estimate entered'));
});

test('the incidence api returns yearly estimates with scope and never a monthly key', function () {
    $users = commandApiUsers();
    $this->seed(IncidenceEstimateSeeder::class);

    $response = $this->actingAs($users['admin'])->getJson('/api/command/incidence')->assertOk();

    expect($response->json('meta.unit'))->toBe('per 100,000')
        ->and($response->json('data.monthly'))->toBeNull()
        ->and($response->json('data.yearly'))->toHaveCount(5)
        ->and($response->json('data.yearly.0.estimate'))->toEqual(292.0)
        ->and($response->json('data.yearly.0.estimate_scope'))->toBe('national_reference')
        ->and($response->json('data.yearly.0.target'))->toBe(90)
        ->and($response->json('data.estimate.scope'))->toBe('national_reference')
        ->and($response->json('data.estimate.rate'))->toEqual(292.0)
        ->and($response->json('data.defaults.year'))->toBe(2026);
});

test('the card on the command center cumulative payload matches the national row', function () {
    $users = commandApiUsers();
    $this->seed(IncidenceEstimateSeeder::class);

    $response = $this->actingAs($users['admin'])->getJson('/api/command/cumulative')->assertOk();

    $sindhDefault = $response;
    $sindhDefault->assertJsonPath('data.estimate.label', 'Estimated TB Incidence Rate')
        ->assertJsonPath('data.estimate.scope', 'national_reference')
        ->assertJsonPath('data.estimate.status', null)
        ->assertJsonPath('data.estimate.sub', 'National estimate (Pakistan), not a Sindh rate');

    expect($sindhDefault->json('data.estimate.rate'))->toEqual(292.0);

    $all = $this->actingAs($users['admin'])->getJson('/api/command/cumulative?province=all')->assertOk();
    $all->assertJsonPath('meta.scope_name', 'All')
        ->assertJsonPath('data.estimate.status', null)
        ->assertJsonPath('data.estimate.sub', '(Pakistan national estimate, 2026)')
        ->assertJsonPath('data.estimate.source', 'WHO estimate for Pakistan (per WHO guidelines), 292 per 100,000')
        ->assertJsonPath('data.estimate.method', 'WHO estimated incidence (new and relapse cases)');

    expect($all->json('data.estimate.rate'))->toEqual(292.0)
        ->and($all->getContent())->not->toContain('(null');
});

test('creating and editing daily returns leaves the estimate card unchanged', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $this->seed(IncidenceEstimateSeeder::class);

    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($owner->id, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner)
        ->put(route('returns.update', $facility->team), returnPayload(now()->toDateString()))
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.rate', 292)
            ->where('estimate.scope', 'national_reference')
            ->where('hasReturns', true));
});

test('the word incidence never appears in positivity or notification labels, map or sector json', function () {
    $users = commandApiUsers();
    $this->seed(IncidenceEstimateSeeder::class);

    $this->actingAs($users['admin'])
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notification.label', 'TB Case Notification Rate'));

    $response = $this->actingAs($users['admin'])->getJson('/api/command/cumulative')->assertOk();
    $positivity = collect($response->json('data.cascade'))
        ->pluck('label')
        ->merge(collect($response->json('data.performance.rings'))->pluck('label'))
        ->merge(collect($response->json('data.performance.tiles'))->pluck('label'))
        ->merge(collect($response->json('data.table'))->pluck('label'))
        ->add($response->json('data.notification.label'))
        ->implode(' ');

    expect(strtolower($positivity))->not->toContain('incidence');

    foreach (['/api/command/map', '/api/command/sectors/public', '/api/command/sectors/private', '/api/command/sectors/ppm'] as $path) {
        $json = $this->actingAs($users['admin'])->getJson($path)->assertOk()->content();

        expect(strtolower($json))->not->toContain('incidence')
            ->and($json)->not->toContain('292.00');
    }
});

test('the seeded national estimate cites the WHO source and method', function () {
    $users = commandApiUsers();
    $this->seed(IncidenceEstimateSeeder::class);

    $this->actingAs($users['admin'])
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('estimate.source', 'WHO estimate for Pakistan (per WHO guidelines), 292 per 100,000')
            ->where('estimate.method', 'WHO estimated incidence (new and relapse cases)'));

    $body = $this->actingAs($users['admin'])->getJson('/api/command/incidence')->assertOk()->content();

    expect($body)->toContain('WHO estimate for Pakistan (per WHO guidelines), 292 per 100,000')
        ->and($body)->toContain('WHO estimated incidence (new and relapse cases)');
});
