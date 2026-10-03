<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('map indicators and the 12 month trend come from daily returns', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03 12:00:00', 'Asia/Karachi'));

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $west = City::factory()->create(['name' => 'Karachi West']);
    $thatta = City::factory()->create(['name' => 'Thatta']);
    $hyderabad = City::factory()->create(['name' => 'Hyderabad']);
    $westFacility = commandFacility($west, Sector::Public, 'West BMU');
    commandReturn($westFacility, '2026-02-01', suspects: 10, tested: 4, positive: 1, negative: 3, started: 0, followUp: 0, tat: 2);
    commandReturn($westFacility, '2025-10-01', suspects: 99, tested: 9, positive: 9, negative: 0, started: 9, followUp: 0);
    commandReturn(commandFacility($thatta, Sector::Public, 'Thatta BMU'), '2026-02-02', suspects: 0, tested: 0, positive: 0, negative: 0, started: 0, followUp: 0);
    commandReturn(commandFacility($hyderabad, Sector::Private, 'Hyderabad BMU'), '2026-02-03', suspects: 8, tested: 0, positive: 0, negative: 0, started: 0, followUp: 0);

    $this->actingAs($admin)
        ->get(route('command.map', ['indicator' => 'testing_coverage']))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $page->component('command/map')
                ->where('title', 'Geospatial Heatmapping')
                ->where('boundaries', 'District boundaries are indicative, not official')
                ->where('tat.note', 'Provisional until the official turnaround formula is confirmed.')
                ->where('indicator', 'testing_coverage')
                ->where('top.0.name', 'Karachi West')
                ->where('top.1.name', 'Hyderabad')
                ->has('top', 2);

            $districts = collect($page->toArray()['props']['districts'])->keyBy('name');
            $west = $districts['Karachi West'];
            $february = collect($west['trend'])->firstWhere('key', '2026-02');

            expect($west['figures']['suspects'])->toBe(10)
                ->and($west['figures']['tested'])->toBe(4)
                ->and($west['figures']['positive'])->toBe(1)
                ->and($west['figures']['positivity'])->toBe(0.25)
                ->and($west['figures']['treatment_gap'])->toBe(1)
                ->and($west['figures']['testing_coverage'])->toBe(0.4)
                ->and($west['figures']['provisional_tat'])->toBe(2)
                ->and($west['lines'][3]['value'])->toBe('25%')
                ->and($districts['Thatta']['figures']['positivity'])->toBeNull()
                ->and($districts['Thatta']['figures']['testing_coverage'])->toBeNull()
                ->and($districts['Thatta']['lines'][3]['value'])->toBe('Positivity appears after tested is above zero.')
                ->and($districts['Thatta']['lines'][7]['value'])->toBe('Testing coverage appears after suspects is above zero.')
                ->and($districts['Hyderabad']['figures']['positivity'])->toBeNull()
                ->and($districts['Hyderabad']['figures']['testing_coverage'])->toEqual(0)
                ->and($west['trend'])->toHaveCount(12)
                ->and($february['suspects'])->toBe(10)
                ->and($february['tested'])->toBe(4)
                ->and($february['positive'])->toBe(1)
                ->and(collect($west['trend'])->sum('suspects'))->toBe(10);

            $keys = collect($page->toArray()['props']['nav']['groups'])
                ->flatMap(fn (array $group): array => array_column($group['items'], 'key'))
                ->all();

            expect($keys)->toContain('command_map')
                ->and($keys)->toContain('command_alerts')
                ->and(file_get_contents(public_path('geo/sindh-districts.geojson')))->toContain('CC BY-IGO');
        });
});

test('a city officer does not see another district and facility staff cannot open the map', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00', 'Asia/Karachi'));

    $city = City::factory()->create(['name' => 'Karachi West']);
    $otherCity = City::factory()->create(['name' => 'Thatta']);
    commandReturn(commandFacility($city, Sector::Public, 'West BMU'), '2026-02-01', suspects: 7, tested: 4, positive: 1, negative: 3, started: 1, followUp: 0);
    commandReturn(commandFacility($otherCity, Sector::Private, 'Thatta BMU'), '2026-02-01', suspects: 90, tested: 40, positive: 20, negative: 20, started: 10, followUp: 0);

    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $city->id,
    ]);

    $this->actingAs($officer)
        ->get(route('command.map', [
            'district' => $otherCity->id,
            'province' => $otherCity->province_id,
            'indicator' => 'suspects',
        ]))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $encoded = json_encode($page->toArray()['props']['districts']);

            expect($encoded)->not->toContain('Thatta')
                ->and($encoded)->not->toContain('90');

            $page->where('districts.0.name', 'Karachi West')
                ->where('districts.0.figures.suspects', 7)
                ->where('filters.district', '')
                ->where('filters.province', '');
        });

    $facility = Facility::factory()->create();
    $member = User::factory()->create();
    $owner = User::factory()->create();
    joinFacility($member, $facility, TeamRole::Member);
    joinFacility($owner, $facility, TeamRole::Owner);

    $this->actingAs($member)->get(route('command.map'))->assertForbidden();
    $this->actingAs($owner)->get(route('command.map'))->assertForbidden();

    $this->actingAs($member)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $keys = collect($page->toArray()['props']['nav']['groups'])
                ->flatMap(fn (array $group): array => array_column($group['items'], 'key'))
                ->all();

            expect($keys)->not->toContain('command_map')
                ->and($keys)->not->toContain('command_alerts');
        });
});

test('the boundary file records its open license', function () {
    $note = file_get_contents(public_path('geo/sindh-districts.txt'));
    $geojson = file_get_contents(public_path('geo/sindh-districts.geojson'));

    expect($note)->toContain('CC BY-IGO')
        ->and($note)->toContain('https://data.humdata.org/dataset/cod-ab-pak')
        ->and($geojson)->toContain('karachi-west')
        ->and($geojson)->toContain('District boundaries are indicative, not official');
});
