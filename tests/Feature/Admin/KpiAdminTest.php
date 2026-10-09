<?php

use App\Enums\ProgrammeRole;
use App\Models\AuditLog;
use App\Models\CaseNotification;
use App\Models\City;
use App\Models\Facility;
use App\Models\PopulationDenominator;
use App\Models\Province;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

test('population denominator and case notification pages need the administer gate', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.population-denominators.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.case-notifications.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.population-denominators.create'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.population-denominators.import-form'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.case-notifications.import-form'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.population-denominators.store'), [])->assertForbidden();
    $this->actingAs($user)->post(route('admin.population-denominators.import'), [])->assertForbidden();
    $this->actingAs($user)->post(route('admin.case-notifications.import'), [])->assertForbidden();
});

test('population denominator edit and update need the administer gate', function () {
    $user = User::factory()->create();
    $row = PopulationDenominator::factory()->create();

    $this->actingAs($user)->get(route('admin.population-denominators.edit', $row))->assertForbidden();
    $this->actingAs($user)->patch(route('admin.population-denominators.update', $row), [])->assertForbidden();
});

test('a signed out visitor is sent to login for the kpi admin pages', function () {
    $this->get(route('admin.population-denominators.index'))->assertRedirect(route('login'));
    $this->get(route('admin.case-notifications.index'))->assertRedirect(route('login'));
});

test('the population denominator list shows rows and exports csv under the kpi tabs', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create(['name' => 'Karachi West']);
    PopulationDenominator::factory()->district($city->id)->create(['year' => 2025, 'population' => 1500000, 'source' => 'PSLM']);

    $this->actingAs($admin)
        ->get(route('admin.population-denominators.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/index')
            ->where('heading', 'KPI reference data')
            ->where('tabs.0.id', 'population_denominators')
            ->where('tabs.0.current', true)
            ->where('tabs.1.id', 'case_notifications')
            ->where('tabs.1.current', false)
            ->where('rows.0.cells.year', '2025')
            ->where('rows.0.cells.population', '1,500,000')
            ->where('rows.0.cells.area', 'Karachi West'));

    $export = $this->actingAs($admin)->get(route('admin.population-denominators.index', ['export' => 'csv']));
    $export->assertOk()->assertDownload('population-denominators.csv');
    expect($export->streamedContent())->toContain('Population')->toContain('1,500,000');
});

test('a population denominator csv import upserts and audit logs the totals', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create(['name' => 'Karachi West']);
    $province = Province::factory()->create();

    PopulationDenominator::factory()->district($city->id)->create(['year' => 2025, 'population' => 1000000]);
    $csv = "area_type,area_id,year,population,source,source_year,method_note\n".
        "district,{$city->id},2025,1500000,PSLM,2023,Updated figure\n".
        "province,{$province->id},2025,49000000,PSLM,2023,Province row\n".
        'district,99999,2025,10,IGNORED,,';

    $this->actingAs($admin)
        ->post(route('admin.population-denominators.import'), [
            'file' => UploadedFile::fake()->createWithContent('rows.csv', $csv),
        ])
        ->assertRedirect()
        ->assertSessionHas('status', '1 rows created, 1 rows updated, 1 rows skipped');

    expect(PopulationDenominator::query()->where('area_id', $city->id)->where('year', 2025)->first()->population)->toBe(1500000)
        ->and(PopulationDenominator::query()->where('area_id', $province->id)->where('year', 2025)->first()->population)->toBe(49000000)
        ->and(PopulationDenominator::query()->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'population_denominators.imported')->sole()->properties)->toBe([
            'created' => 1,
            'updated' => 1,
            'skipped' => 1,
        ]);
});

test('a population denominator csv import stores a placeholder row with its provenance', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create(['name' => 'Jacobabad']);

    $csv = "area_type,area_id,year,population,source,source_year,method_note,reference_url\n".
        "district,{$city->id},2025,,PBS Census 2023,2023,,https://www.pbs.gov.pk/wp-content/uploads/census_tables/tables/table_1_sindh_districts.pdf";

    $this->actingAs($admin)
        ->post(route('admin.population-denominators.import'), [
            'file' => UploadedFile::fake()->createWithContent('rows.csv', $csv),
        ])
        ->assertRedirect()
        ->assertSessionHas('status', '1 rows created, 0 rows updated, 0 rows skipped');

    $row = PopulationDenominator::query()->where('area_id', $city->id)->where('year', 2025)->sole();

    expect($row->population)->toBeNull()
        ->and($row->status->value)->toBe('placeholder')
        ->and($row->source)->toBe('PBS Census 2023')
        ->and($row->source_year)->toBe(2023)
        ->and($row->reference_url)->toBe('https://www.pbs.gov.pk/wp-content/uploads/census_tables/tables/table_1_sindh_districts.pdf');
});

test('a population denominator csv import rejects a row whose population is present but below one', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create();

    $csv = "area_type,area_id,year,population,source,source_year\n".
        "district,{$city->id},2025,0,PBS Census 2023,2023\n";

    $this->actingAs($admin)
        ->post(route('admin.population-denominators.import'), [
            'file' => UploadedFile::fake()->createWithContent('rows.csv', $csv),
        ])
        ->assertSessionHasErrors('file')
        ->assertStatus(302);

    expect(PopulationDenominator::query()->count())->toBe(0);
});

test('a blank population gets the census default source and year in a single-row create', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create(['name' => 'Matiari']);

    $this->actingAs($admin)
        ->post(route('admin.population-denominators.store'), [
            'area_type' => 'district',
            'area_id' => (string) $city->id,
            'year' => '2025',
            'population' => '',
            'source' => '',
            'source_year' => '',
            'reference_url' => '',
            'method_note' => '',
        ])
        ->assertRedirect(route('admin.population-denominators.index'))
        ->assertSessionHas('status', 'Population denominator saved.');

    $row = PopulationDenominator::query()->where('area_id', $city->id)->where('year', 2025)->sole();

    expect($row->population)->toBeNull()
        ->and($row->status->value)->toBe('placeholder')
        ->and($row->source)->toBe('PBS Census 2023')
        ->and($row->source_year)->toBe(2023)
        ->and($row->entered_by)->toBe($admin->id)
        ->and(AuditLog::query()->where('action', 'population_denominators.created')->sole()->properties['status'])->toBe('placeholder');
});

test('a district placeholder becomes confirmed once a census figure is entered', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create(['name' => 'Jacobabad']);
    $row = PopulationDenominator::factory()->placeholder($city->id)->create([
        'year' => 2025,
        'source' => 'PBS Census 2023',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.population-denominators.edit', $row))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/population-form')
            ->where('mode', 'edit')
            ->where('row.area', 'Jacobabad')
            ->where('row.year', 2025)
            ->where('row.status', 'placeholder')
            ->where('locked.year', '2025'));

    $this->actingAs($admin)
        ->patch(route('admin.population-denominators.update', $row), [
            'population' => '1250000',
            'source' => '',
            'source_year' => '2023',
            'reference_url' => '',
            'method_note' => '',
        ])
        ->assertRedirect(route('admin.population-denominators.index'));

    $row->refresh();

    expect($row->population)->toBe(1250000)
        ->and($row->status->value)->toBe('confirmed')
        ->and($row->source)->toBe('PBS Census 2023')
        ->and($row->entered_by)->toBe($admin->id)
        ->and(AuditLog::query()->where('action', 'population_denominators.updated')->sole()->properties['status'])->toBe('confirmed');
});

test('the case notification list shows rows and the notified count', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $facility = Facility::factory()->create(['code' => 'BMU-001']);
    $facility->team->update(['name' => 'West BMU']);

    CaseNotification::factory()->create([
        'facility_id' => $facility->id,
        'month' => '2025-10',
        'new_cases' => 12,
        'relapse_cases' => 3,
        'other_previously_treated' => 2,
        'history_unknown' => 1,
        'source' => 'NTMIS',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.case-notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/index')
            ->where('heading', 'KPI reference data')
            ->where('tabs.0.id', 'population_denominators')
            ->where('tabs.1.id', 'case_notifications')
            ->where('tabs.1.current', true)
            ->where('rows.0.cells.facility', 'West BMU')
            ->where('rows.0.cells.notified', '15'));
});

test('a case notification csv import upserts by facility code and month', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $facility = Facility::factory()->create(['code' => 'BMU-001']);

    CaseNotification::factory()->create(['facility_id' => $facility->id, 'month' => '2025-10', 'new_cases' => 1, 'relapse_cases' => 0]);
    $csv = "facility_code,month,new_cases,relapse_cases,other_previously_treated,history_unknown,source\n".
        "BMU-001,2025-10,12,3,1,0,NTMIS\n".
        "BMU-001,2025-11,8,2,0,1,NTMIS\n".
        'NOPE-999,2025-11,5,5,5,5,NTMIS';

    $this->actingAs($admin)
        ->post(route('admin.case-notifications.import'), [
            'file' => UploadedFile::fake()->createWithContent('rows.csv', $csv),
        ])
        ->assertRedirect()
        ->assertSessionHas('status', '1 rows created, 1 rows updated, 1 rows skipped');

    expect(CaseNotification::query()->count())->toBe(2)
        ->and(CaseNotification::query()->where('month', '2025-10')->first()->new_cases)->toBe(12)
        ->and(AuditLog::query()->where('action', 'case_notifications.imported')->sole()->properties)->toBe([
            'created' => 1,
            'updated' => 1,
            'skipped' => 1,
        ]);
});
