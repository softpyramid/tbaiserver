<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\Country;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use App\Support\ProvisionalReportedTat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('cumulative data is summed live and year to date is the default range', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03 12:00:00', 'Asia/Karachi'));

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create(['name' => 'Karachi West']);
    $facility = commandFacility($city, Sector::Public, 'West BMU');
    $other = commandFacility($city, Sector::Public, 'Second BMU');

    commandReturn($facility, '2025-12-31', suspects: 100, tested: 100, positive: 50, negative: 50, started: 50, followUp: 9);
    commandReturn($facility, '2026-01-01', suspects: 10, tested: 8, positive: 2, negative: 6, started: 1, followUp: 3, tat: 2);
    commandReturn($facility, '2026-02-01', suspects: 0, tested: 0, positive: 0, negative: 0, started: 0, followUp: 0, tat: 9);
    commandReturn($other, '2026-10-03', suspects: 5, tested: 4, positive: 1, negative: 9, started: 0, followUp: 1, tat: 5);
    commandReturn($other, '2026-10-04', suspects: 80, tested: 80, positive: 80, negative: 0, started: 80, followUp: 8);

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('command/index')
            ->where('title', 'Cumulative Data')
            ->where('filters.from', '2026-01-01')
            ->where('filters.to', '2026-10-03')
            ->where('totals.suspects', 15)
            ->where('totals.tested', 12)
            ->where('totals.positive', 3)
            ->where('totals.negative', 15)
            ->where('totals.follow_up', 4)
            ->where('totals.positivity', 0.25)
            ->where('totals.treatment_gap', 2)
            ->where('totals.active_facilities', 2)
            ->where('totals.provisional_tat', 3)
            ->where('kpis.4.value', '25%')
            ->where('kpis.6.label', 'Positives without a treatment start')
            ->where('kpis.6.value', '2')
            ->where('months.0.suspects', 10)
            ->where('months.1.suspects', 10)
            ->where('months.9.suspects', 15)
            ->where('cascade.0.value', 15)
            ->where('cascade.1.value', 12)
            ->where('cascade.2.value', 3)
            ->where('cascade.3.value', 1)
            ->where('options.sectors', [
                ['value' => '', 'label' => 'All'],
                ['value' => 'public', 'label' => 'Public'],
                ['value' => 'private', 'label' => 'Private'],
                ['value' => 'ppm', 'label' => 'PPM'],
            ]));
});

test('a zero tested range does not divide positivity', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-01 12:00:00', 'Asia/Karachi'));

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $facility = commandFacility(City::factory()->create(), Sector::Public, 'Quiet BMU');
    commandReturn($facility, '2026-02-02', suspects: 4, tested: 0, positive: 0, negative: 0, started: 2, followUp: 0);

    $this->actingAs($admin)
        ->get(route('command.index', ['from' => '2026-02-01', 'to' => '2026-02-28']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.tested', 0)
            ->where('totals.positivity', null)
            ->where('totals.treatment_gap', -2)
            ->where('totals.active_facilities', 1)
            ->where('kpis.4.value', 'Positivity appears after tested is above zero.')
            ->where('months', [
                [
                    'key' => '2026-02',
                    'label' => 'Feb 2026',
                    'suspects' => 4,
                    'tested' => 0,
                    'positive' => 0,
                    'started_treatment' => 2,
                ],
            ]));
});

test('not collected measures are labelled and have no numeric value', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    expect(Schema::hasColumn('daily_returns', 'treatment_success'))->toBeFalse()
        ->and(Schema::hasColumn('daily_returns', 'lost_to_follow_up'))->toBeFalse()
        ->and(Schema::hasColumn('daily_returns', 'mdr_rr'))->toBeFalse()
        ->and(Schema::hasColumn('daily_returns', 'ai_assisted_tests'))->toBeFalse();

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notCollected', [
                'heading' => 'Not collected yet',
                'items' => [
                    ['key' => 'treatment_success', 'status' => 'not_collected', 'label' => 'Treatment success'],
                    ['key' => 'lost_to_follow_up', 'status' => 'not_collected', 'label' => 'Lost to follow-up'],
                    ['key' => 'mdr_rr', 'status' => 'not_collected', 'label' => 'MDR/RR'],
                    ['key' => 'ai_assisted_tests', 'status' => 'not_collected', 'label' => 'AI-assisted tests'],
                ],
            ])
            ->missing('notCollected.items.0.value')
            ->missing('notCollected.items.1.value')
            ->missing('notCollected.items.2.value')
            ->missing('notCollected.items.3.value')
            ->missing('totals.treatment_success')
            ->missing('totals.lost_to_follow_up')
            ->missing('totals.mdr_rr')
            ->missing('totals.ai_assisted_tests'));
});

test('a city officer cannot see another city or widen scope with query params', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00', 'Asia/Karachi'));

    $city = City::factory()->create(['name' => 'Karachi West']);
    $otherCity = City::factory()->create(['name' => 'Thatta']);
    $own = commandFacility($city, Sector::Public, 'West BMU');
    $other = commandFacility($otherCity, Sector::Private, 'Thatta BMU');
    commandReturn($own, '2026-02-01', suspects: 7, tested: 4, positive: 1, negative: 3, started: 1, followUp: 0);
    commandReturn($other, '2026-02-01', suspects: 90, tested: 40, positive: 20, negative: 20, started: 10, followUp: 5);

    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $city->id,
    ]);

    $this->actingAs($officer)
        ->get(route('command.index', [
            'district' => $otherCity->id,
            'province' => $otherCity->province_id,
            'facility' => $other->id,
        ]))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $labels = array_column($page->toArray()['props']['options']['districts'], 'label');

            expect($labels)->toContain('Karachi West')
                ->and($labels)->not->toContain('Thatta');

            $page->where('totals.suspects', 7)
                ->where('filters.district', '')
                ->where('filters.province', '');
        });

    $this->actingAs($officer)
        ->get(route('command.districts.show', ['sector' => 'private', 'city' => $otherCity->id]))
        ->assertForbidden();
});

test('a provincial viewer cannot see another province', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00', 'Asia/Karachi'));

    $province = Province::factory()->create(['name' => 'Sindh']);
    $otherProvince = Province::factory()->create(['name' => 'Punjab']);
    $city = City::factory()->create(['province_id' => $province->id, 'name' => 'Karachi West']);
    $otherCity = City::factory()->create(['province_id' => $otherProvince->id, 'name' => 'Lahore']);
    commandReturn(commandFacility($city, Sector::Public, 'Sindh BMU'), '2026-03-01', suspects: 11, tested: 10, positive: 2, negative: 8, started: 1, followUp: 0);
    commandReturn(commandFacility($otherCity, Sector::Public, 'Punjab BMU'), '2026-03-01', suspects: 60, tested: 50, positive: 10, negative: 40, started: 8, followUp: 2);

    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $province->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('command.index', ['province' => $otherProvince->id, 'district' => $otherCity->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.suspects', 11)
            ->where('filters.province', '')
            ->where('scope', 'Sindh'));
});

test('a country viewer is limited to their country and an admin sees every country', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-01 12:00:00', 'Asia/Karachi'));

    $country = Country::factory()->create(['name' => 'Pakistan']);
    $otherCountry = Country::factory()->create(['name' => 'Elsewhere']);
    $province = Province::factory()->create(['country_id' => $country->id, 'name' => 'Sindh']);
    $otherProvince = Province::factory()->create(['country_id' => $otherCountry->id, 'name' => 'Foreign Province']);
    $city = City::factory()->create(['province_id' => $province->id, 'name' => 'Karachi West']);
    $otherCity = City::factory()->create(['province_id' => $otherProvince->id, 'name' => 'Foreign City']);
    commandReturn(commandFacility($city, Sector::Public, 'Pakistan BMU'), '2026-04-01', suspects: 8, tested: 8, positive: 1, negative: 7, started: 1, followUp: 0);
    commandReturn(commandFacility($otherCity, Sector::Public, 'Foreign BMU'), '2026-04-01', suspects: 30, tested: 20, positive: 5, negative: 15, started: 4, followUp: 1);

    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::CountryViewer,
        'scope_type' => ScopeType::Country,
        'scope_id' => $country->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('command.index', ['province' => $otherProvince->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('totals.suspects', 8));

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('totals.suspects', 38));
});

test('facility staff cannot open the command center', function () {
    $facility = Facility::factory()->create();
    $member = User::factory()->create();
    $owner = User::factory()->create();
    joinFacility($member, $facility, TeamRole::Member);
    joinFacility($owner, $facility, TeamRole::Owner);

    $this->actingAs($member)->get(route('command.index'))->assertForbidden();
    $this->actingAs($member)->get(route('command.sectors.show', ['sector' => 'public']))->assertForbidden();
    $this->actingAs($owner)->get(route('command.index'))->assertForbidden();
    $this->actingAs($owner)
        ->get(route('command.districts.show', ['sector' => 'public', 'city' => $facility->city_id]))
        ->assertForbidden();
});

test('a sector page is a sortable district table and a row opens facility charts', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-01 12:00:00', 'Asia/Karachi'));

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $west = City::factory()->create(['name' => 'Karachi West']);
    $thatta = City::factory()->create(['name' => 'Thatta']);
    $publicWest = commandFacility($west, Sector::Public, 'West Public');
    $publicThatta = commandFacility($thatta, Sector::Public, 'Thatta Public');
    $privateWest = commandFacility($west, Sector::Private, 'West Private');

    commandReturn($publicWest, '2026-01-10', suspects: 10, tested: 8, positive: 2, negative: 6, started: 1, followUp: 1, tat: 2);
    commandReturn($publicThatta, '2026-01-11', suspects: 4, tested: 0, positive: 0, negative: 0, started: 0, followUp: 0);
    commandReturn($privateWest, '2026-01-12', suspects: 50, tested: 40, positive: 10, negative: 30, started: 9, followUp: 2, tat: 6);

    $this->actingAs($admin)
        ->get(route('command.sectors.show', ['sector' => 'public', 'sort' => 'suspects', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('command/sector')
            ->where('title', 'Public registered')
            ->where('rows.0.cells.district', 'Karachi West')
            ->where('rows.0.cells.suspects', '10')
            ->where('rows.0.cells.tested', '8')
            ->where('rows.0.cells.positive', '2')
            ->where('rows.0.cells.positivity', '25%')
            ->where('rows.0.cells.started_treatment', '1')
            ->where('rows.0.cells.treatment_gap', '1')
            ->where('rows.0.cells.provisional_tat', '2')
            ->where('rows.0.cells.active_facilities', '1')
            ->where('rows.1.cells.district', 'Thatta')
            ->where('rows.1.cells.positivity', 'Not available')
            ->where('rows.1.cells.provisional_tat', ProvisionalReportedTat::INSUFFICIENT)
            ->where('tat.note', 'Provisional until the official turnaround formula is confirmed.')
            ->missing('rows.0.cells.treatment_success')
            ->missing('rows.0.cells.lost_to_follow_up')
            ->missing('rows.0.cells.mdr_rr')
            ->missing('rows.0.cells.ai_assisted_tests')
            ->has('columns', 9));

    $this->actingAs($admin)
        ->get(route('command.districts.show', ['sector' => 'public', 'city' => $west->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('command/district')
            ->where('title', 'Karachi West')
            ->has('facilities', 1)
            ->where('facilities.0.name', 'West Public')
            ->where('facilities.0.cascade.0.value', 10)
            ->missing('facilities.0.treatment_success'));
});

function commandFacility(City $city, Sector $sector, string $name): Facility
{
    $facility = Facility::factory()->create([
        'city_id' => $city->id,
        'sector' => $sector,
    ]);
    $facility->team->update(['name' => $name]);

    return $facility;
}

function commandReturn(
    Facility $facility,
    string $date,
    int $suspects,
    int $tested,
    int $positive,
    int $negative,
    int $started,
    int $followUp,
    ?float $tat = null,
): DailyReturn {
    return DailyReturn::factory()->create([
        'facility_id' => $facility->id,
        'reported_on' => $date,
        'suspects' => $suspects,
        'tested' => $tested,
        'positive' => $positive,
        'negative' => $negative,
        'started_treatment' => $started,
        'follow_up' => $followUp,
        'median_tat_days' => $tat,
    ]);
}
