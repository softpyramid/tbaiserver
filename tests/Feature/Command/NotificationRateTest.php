<?php

use App\Enums\AreaType;
use App\Enums\PopulationStatus;
use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\Sector;
use App\Models\CaseNotification;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\PopulationDenominator;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use App\Support\NotificationRate;
use App\Support\PopulationLookup;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('population lookup prefers the exact year and falls back to the latest earlier year', function () {
    $city = City::factory()->create();

    PopulationDenominator::factory()->district($city->id)->create(['year' => 2024, 'population' => 900000]);
    PopulationDenominator::factory()->district($city->id)->create(['year' => 2026, 'population' => 1100000]);

    expect(PopulationLookup::forArea(AreaType::District, $city->id, 2026))->toEqual([
        'population' => 1100000,
        'year' => 2026,
        'source' => 'Test data',
    ])
        ->and(PopulationLookup::forArea(AreaType::District, $city->id, 2025)['population'])->toBe(900000)
        ->and(PopulationLookup::forArea(AreaType::District, $city->id, 2023))->toBeNull();
});

test('a notification card counts new and relapse cases and divides by the population', function () {
    $city = City::factory()->create();
    $facility = Facility::factory()->create(['city_id' => $city->id]);

    CaseNotification::factory()->create([
        'facility_id' => $facility->id,
        'month' => '2025-10',
        'new_cases' => 40,
        'relapse_cases' => 10,
        'other_previously_treated' => 100,
        'history_unknown' => 50,
    ]);
    PopulationDenominator::factory()->district($city->id)->create(['year' => 2025, 'population' => 500000]);

    $card = NotificationRate::card(AreaType::District, $city->id, '2025-10-31');

    expect($card['cases'])->toBe(50)
        ->and($card['ytd_cases'])->toBe(50)
        ->and($card['population'])->toBe(500000)
        ->and($card['population_year'])->toBe(2025)
        ->and($card['rate'])->toBe(10.0)
        ->and($card['status'])->toBeNull();
});

test('a notification card without a population reports population_missing', function () {
    $city = City::factory()->create();
    $facility = Facility::factory()->create(['city_id' => $city->id]);

    CaseNotification::factory()->create(['facility_id' => $facility->id, 'month' => '2025-10', 'new_cases' => 5, 'relapse_cases' => 1]);

    $card = NotificationRate::card(AreaType::District, $city->id, '2025-10-31');

    expect($card['status'])->toBe('population_missing')
        ->and($card['cases'])->toBe(6)
        ->and($card['message'])->toBeNull()
        ->and($card['rate'])->toBeNull();
});

test('a placeholder denominator never feeds a rate until its figure is entered', function () {
    $city = City::factory()->create();
    $facility = Facility::factory()->create(['city_id' => $city->id]);

    CaseNotification::factory()->create(['facility_id' => $facility->id, 'month' => '2025-10', 'new_cases' => 5, 'relapse_cases' => 1]);
    PopulationDenominator::factory()->placeholder($city->id)->create(['year' => 2025]);

    $card = NotificationRate::card(AreaType::District, $city->id, '2025-10-31');

    expect($card['status'])->toBe('population_missing')
        ->and($card['population'])->toBeNull()
        ->and($card['rate'])->toBeNull();

    PopulationDenominator::query()->where('area_id', $city->id)->update(['population' => 200000, 'status' => 'confirmed']);

    $card = NotificationRate::card(AreaType::District, $city->id, '2025-10-31');

    expect($card['population'])->toBe(200000)
        ->and($card['rate'])->toBe(3.0);
});

test('a province without a confirmed denominator says which population is not set', function () {
    $province = Province::factory()->create(['name' => 'Sindh']);
    $city = City::factory()->create(['province_id' => $province->id]);

    CaseNotification::factory()->create(['facility_id' => Facility::factory()->create(['city_id' => $city->id])->id, 'month' => '2025-10', 'new_cases' => 5, 'relapse_cases' => 1]);
    PopulationDenominator::factory()->province($province->id)->create(['year' => 2025, 'population' => null, 'status' => 'placeholder']);

    $card = NotificationRate::card(AreaType::Province, $province->id, '2025-10-31');

    expect($card['status'])->toBe('population_missing')
        ->and($card['message'])->toBe('Sindh population not set')
        ->and($card['rate'])->toBeNull();
});

test('a confirmed province denominator clears the missing-population message', function () {
    $province = Province::factory()->create(['name' => 'Sindh']);
    PopulationDenominator::factory()->province($province->id)->create(['year' => 2025, 'population' => 1000000]);

    $card = NotificationRate::card(AreaType::Province, $province->id, '2025-10-31');

    expect($card['status'])->toBe('no_notification_data')
        ->and($card['message'])->toBeNull();
});

test('a notification card without notifications reports no_notification_data', function () {
    $city = City::factory()->create();

    PopulationDenominator::factory()->district($city->id)->create(['year' => 2025, 'population' => 500000]);

    $card = NotificationRate::card(AreaType::District, $city->id, '2025-10-31');

    expect($card['status'])->toBe('no_notification_data')
        ->and($card['cases'])->toBeNull()
        ->and($card['rate'])->toBeNull();
});

test('a province pools its districts and divides by the province denominator', function () {
    $province = Province::factory()->create();
    $cityA = City::factory()->create(['province_id' => $province->id]);
    $cityB = City::factory()->create(['province_id' => $province->id]);

    CaseNotification::factory()->create(['facility_id' => Facility::factory()->create(['city_id' => $cityA->id])->id, 'month' => '2025-10', 'new_cases' => 100, 'relapse_cases' => 20]);
    CaseNotification::factory()->create(['facility_id' => Facility::factory()->create(['city_id' => $cityB->id])->id, 'month' => '2025-10', 'new_cases' => 30, 'relapse_cases' => 10]);
    PopulationDenominator::factory()->province($province->id)->create(['year' => 2025, 'population' => 1000000]);

    $card = NotificationRate::card(AreaType::Province, $province->id, '2025-10-31');

    expect($card['cases'])->toBe(160)
        ->and($card['rate'])->toBe(16.0);
});

test('year to date cases accumulate from January of the reference year', function () {
    $city = City::factory()->create();
    $facility = Facility::factory()->create(['city_id' => $city->id]);

    CaseNotification::factory()->create(['facility_id' => $facility->id, 'month' => '2025-03', 'new_cases' => 20, 'relapse_cases' => 5]);
    CaseNotification::factory()->create(['facility_id' => $facility->id, 'month' => '2025-10', 'new_cases' => 30, 'relapse_cases' => 10]);
    PopulationDenominator::factory()->district($city->id)->create(['year' => 2025, 'population' => 500000]);

    $card = NotificationRate::card(AreaType::District, $city->id, '2025-10-31');

    expect($card['cases'])->toBe(40)
        ->and($card['ytd_cases'])->toBe(65);
});

test('district notification rates skip areas without a population', function () {
    $cityA = City::factory()->create();
    $cityB = City::factory()->create();

    CaseNotification::factory()->create(['facility_id' => Facility::factory()->create(['city_id' => $cityA->id])->id, 'month' => '2025-10', 'new_cases' => 10, 'relapse_cases' => 0]);
    PopulationDenominator::factory()->district($cityA->id)->create(['year' => 2025, 'population' => 200000]);

    $rates = NotificationRate::districtRates([$cityA->id, $cityB->id], '2025-10-31');

    expect($rates[$cityA->id])->toBe(5.0)
        ->and($rates[$cityB->id])->toBeNull();
});

test('the cumulative overview carries a district notification card from imported data', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03 12:00:00', 'Asia/Karachi'));

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create(['name' => 'Karachi West']);
    $facility = commandFacility($city, Sector::Public, 'West BMU');
    commandReturn($facility, '2026-09-15', suspects: 10, tested: 10, positive: 2, negative: 8, started: 2, followUp: 0);
    CaseNotification::factory()->create(['facility_id' => $facility->id, 'month' => '2026-10', 'new_cases' => 30, 'relapse_cases' => 10]);
    PopulationDenominator::factory()->district($city->id)->create(['year' => 2026, 'population' => 500000]);

    $this->actingAs($admin)
        ->get(route('command.index', ['district' => $city->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
            ->where('notification.period', '2026-10')
            ->where('notification.cases', 40)
            ->where('notification.ytd_cases', 40)
            ->where('notification.population', 500000)
            ->where('notification.population_year', 2026)
            ->where('notification.rate', 8)
            ->where('notification.status', null)));
});

test('all and sindh divide 90 cases by the confirmed sindh population on both pages', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 12:00:00', 'Asia/Karachi'));

    $sindh = Province::factory()->create(['name' => 'Sindh', 'slug' => 'sindh']);
    $city = City::factory()->create(['province_id' => $sindh->id, 'name' => 'Karachi West']);
    $facility = Facility::factory()->create(['city_id' => $city->id]);

    CaseNotification::factory()->create([
        'facility_id' => $facility->id,
        'month' => '2026-10',
        'new_cases' => 70,
        'relapse_cases' => 20,
        'other_previously_treated' => 15,
    ]);
    PopulationDenominator::factory()->province($sindh->id)->create([
        'year' => 2023,
        'population' => 3000000,
        'status' => PopulationStatus::Confirmed,
        'source' => 'PBS Census 2023',
    ]);

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $card = fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
        ->where('notification.rate', 3)
        ->where('notification.cases', 90)
        ->where('notification.population', 3000000)
        ->where('notification.population_year', 2023)
        ->where('notification.source', 'PBS Census 2023')
        ->where('notification.status', null));

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia($card);

    $this->actingAs($admin)
        ->get(route('command.index', ['province' => $sindh->id]))
        ->assertOk()
        ->assertInertia($card);

    $this->actingAs($admin)
        ->getJson(route('api.command.cumulative'))
        ->assertOk()
        ->assertJsonPath('data.notification.rate', 3.0)
        ->assertJsonPath('data.notification.cases', 90)
        ->assertJsonPath('data.notification.population', 3000000)
        ->assertJsonPath('data.notification.population_year', 2023)
        ->assertJsonPath('data.notification.status', null);

    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $sindh->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia($card);
});

test('a placeholder district stays population not set when cases exist', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 12:00:00', 'Asia/Karachi'));

    $sindh = Province::factory()->create(['name' => 'Sindh', 'slug' => 'sindh']);
    $city = City::factory()->create(['province_id' => $sindh->id, 'name' => 'Karachi West']);
    $facility = Facility::factory()->create(['city_id' => $city->id]);

    CaseNotification::factory()->create([
        'facility_id' => $facility->id,
        'month' => '2026-10',
        'new_cases' => 40,
        'relapse_cases' => 5,
    ]);
    PopulationDenominator::factory()->placeholder($city->id)->create(['year' => 2023]);
    PopulationDenominator::factory()->province($sindh->id)->create([
        'year' => 2023,
        'population' => 3000000,
        'status' => PopulationStatus::Confirmed,
    ]);

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('command.index', ['district' => $city->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
            ->where('notification.rate', null)
            ->where('notification.population', null)
            ->where('notification.status', 'population_missing')
            ->where('notification.cases', 45)));

    $this->actingAs($admin)
        ->getJson(route('api.command.cumulative', ['district' => $city->id]))
        ->assertOk()
        ->assertJsonPath('data.notification.rate', null)
        ->assertJsonPath('data.notification.status', 'population_missing')
        ->assertJsonPath('data.notification.cases', 45);

    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $city->id,
    ]);

    $this->actingAs($officer)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
            ->where('notification.status', 'population_missing')
            ->where('notification.rate', null)));
});

test('no case notifications say so instead of population not set', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 12:00:00', 'Asia/Karachi'));

    $sindh = Province::factory()->create(['name' => 'Sindh', 'slug' => 'sindh']);
    $city = City::factory()->create(['province_id' => $sindh->id]);
    PopulationDenominator::factory()->province($sindh->id)->create([
        'year' => 2023,
        'population' => 3000000,
        'status' => PopulationStatus::Confirmed,
    ]);
    PopulationDenominator::factory()->placeholder($city->id)->create(['year' => 2023]);

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
            ->where('notification.status', 'no_notification_data')
            ->where('notification.rate', null)
            ->where('notification.population', 3000000)));

    $this->actingAs($admin)
        ->get(route('command.index', ['district' => $city->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
            ->where('notification.status', 'no_notification_data')
            ->where('notification.population', null)));
});

test('all uses a national population row when one is confirmed', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 12:00:00', 'Asia/Karachi'));

    $country = Country::factory()->create(['name' => 'Pakistan', 'slug' => 'pakistan']);
    $sindh = Province::factory()->create(['country_id' => $country->id, 'name' => 'Sindh', 'slug' => 'sindh']);
    $city = City::factory()->create(['province_id' => $sindh->id]);
    $facility = Facility::factory()->create(['city_id' => $city->id]);

    CaseNotification::factory()->create([
        'facility_id' => $facility->id,
        'month' => '2026-10',
        'new_cases' => 90,
        'relapse_cases' => 0,
    ]);
    PopulationDenominator::factory()->province($sindh->id)->create([
        'year' => 2023,
        'population' => 3000000,
        'status' => PopulationStatus::Confirmed,
    ]);
    PopulationDenominator::factory()->create([
        'area_type' => AreaType::Country,
        'area_id' => $country->id,
        'year' => 2023,
        'population' => 10000000,
        'status' => PopulationStatus::Confirmed,
        'source' => 'PBS Census 2023',
    ]);

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
            ->where('notification.population', 10000000)
            ->where('notification.rate', 0.9)
            ->where('notification.cases', 90)
            ->where('notification.status', null)));

    $this->actingAs($admin)
        ->get(route('command.index', ['province' => $sindh->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
            ->where('notification.population', 3000000)
            ->where('notification.rate', 3)
            ->where('notification.status', null)));
});

test('the map shows the notification rate indicator per district', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03 12:00:00', 'Asia/Karachi'));

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $west = City::factory()->create(['name' => 'Karachi West']);
    $facility = commandFacility($west, Sector::Public, 'West BMU');
    commandReturn($facility, '2026-09-15', suspects: 20, tested: 10, positive: 2, negative: 8, started: 2, followUp: 0);
    CaseNotification::factory()->create(['facility_id' => $facility->id, 'month' => '2026-10', 'new_cases' => 30, 'relapse_cases' => 10]);
    PopulationDenominator::factory()->district($west->id)->create(['year' => 2026, 'population' => 200000]);

    $this->actingAs($admin)
        ->get(route('command.map', ['indicator' => 'notification_rate']))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $districts = collect($page->toArray()['props']['districts'])->keyBy('name');
            $west = $districts->get('Karachi West');

            expect($west)->not->toBeNull()
                ->and($west['figures']['notification_rate'])->toBe(20)
                ->and($west['value'])->toBe(20)
                ->and($west['display'])->toBe('20.0 per 100,000');
        });
});

test('the map pairs the notification rate card value with the prototype api', function () {
    $users = commandApiUsers();
    $response = $this->actingAs($users['admin'])->getJson('/api/command/cumulative')->assertOk();
    $to = $response->json('meta.filters.to');
    $month = substr($to, 0, 7);
    $year = (int) substr($to, 0, 4);
    $sindh = Province::query()->where('slug', 'sindh')->firstOrFail();
    $facility = Facility::query()
        ->where('city_id', City::query()->where('slug', 'karachi-west')->value('id'))
        ->firstOrFail();

    CaseNotification::factory()->create(['facility_id' => $facility->id, 'month' => $month, 'new_cases' => 30, 'relapse_cases' => 10]);
    PopulationDenominator::factory()->province($sindh->id)->create(['year' => $year, 'population' => 1000000]);

    $this->actingAs($users['admin'])
        ->getJson('/api/command/cumulative')
        ->assertOk()
        ->assertJsonPath('data.notification.period', $month)
        ->assertJsonPath('data.notification.cases', 40)
        ->assertJsonPath('data.notification.population', 1000000)
        ->assertJsonPath('data.notification.rate', 4.0)
        ->assertJsonPath('data.notification.status', null);
});
