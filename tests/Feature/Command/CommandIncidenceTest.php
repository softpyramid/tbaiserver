<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\IncidenceTarget;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => enableFeature('incidence'));

test('with no targets the empty state shows and no series is returned', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('command.incidence'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('command/incidence')
            ->where('title', 'Incidence')
            ->where('empty', 'No incidence targets have been entered.')
            ->where('series', [])
            ->where('targets', [])
            ->where('yearOptions', [2026, 2027, 2028, 2029, 2030])
            ->where('canEdit', true)
            ->missing('months'));

    expect(Schema::hasColumn('incidence_targets', 'patient_id'))->toBeFalse()
        ->and(Schema::getColumnListing('incidence_targets'))->not->toContain('user_id');
});

test('a tb-ai admin can save and edit a 2026 to 2030 target', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $province = City::factory()->create()->province;

    $this->actingAs($admin)
        ->post(route('command.incidence.store'), [
            'province_id' => $province->id,
            'year' => 2026,
            'value' => 90,
        ])
        ->assertRedirect();

    expect(IncidenceTarget::query()->count())->toBe(1)
        ->and(IncidenceTarget::query()->first()->month)->toBeNull();

    $this->actingAs($admin)
        ->get(route('command.incidence'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('empty', null)
            ->has('series', 1)
            ->where('series.0.year', 2026)
            ->where('series.0.value', '90')
            ->where('series.0.province', $province->name)
            ->missing('series.0.month')
            ->has('targets', 1)
            ->where('targets.0.year', 2026)
            ->where('targets.0.value', '90')
            ->where('targets.0.province', $province->name));

    $this->actingAs($admin)
        ->post(route('command.incidence.store'), [
            'province_id' => $province->id,
            'year' => 2026,
            'value' => 70,
        ])
        ->assertRedirect();

    expect(IncidenceTarget::query()->count())->toBe(1);

    $this->actingAs($admin)
        ->post(route('command.incidence.store'), [
            'province_id' => $province->id,
            'year' => 2030,
            'value' => 25,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('command.incidence'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $years = array_column($page->toArray()['props']['series'], 'year');

            expect($years)->toBe([2026, 2030])
                ->and($years)->not->toContain(2025)
                ->and($years)->not->toContain(2027)
                ->and($years)->not->toContain(2031);

            $page->where('targets.0.value', '70')
                ->where('targets.1.year', 2030)
                ->where('targets.1.value', '25');
        });

    $this->actingAs($admin)
        ->from(route('command.incidence'))
        ->post(route('command.incidence.store'), [
            'province_id' => $province->id,
            'year' => 2025,
            'value' => 10,
        ])
        ->assertSessionHasErrors('year');

    $this->actingAs($admin)
        ->from(route('command.incidence'))
        ->post(route('command.incidence.store'), [
            'province_id' => $province->id,
            'year' => 2031,
            'value' => 10,
        ])
        ->assertSessionHasErrors('year');

    expect(IncidenceTarget::query()->whereIn('year', [2025, 2031])->exists())->toBeFalse()
        ->and(IncidenceTarget::query()->pluck('year')->sort()->values()->all())->toBe([2026, 2030]);
});

test('a non admin cannot save an incidence target', function () {
    $province = Province::factory()->create();
    $city = City::factory()->create(['province_id' => $province->id]);
    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $city->id,
    ]);
    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $province->id,
    ]);

    $payload = [
        'province_id' => $province->id,
        'year' => 2026,
        'value' => 90,
    ];

    $this->actingAs($officer)->post(route('command.incidence.store'), $payload)->assertForbidden();
    $this->actingAs($viewer)->post(route('command.incidence.store'), $payload)->assertForbidden();

    $facility = Facility::factory()->create(['city_id' => $city->id]);
    $member = User::factory()->create();
    $owner = User::factory()->create();

    joinFacility($member, $facility, TeamRole::Member);
    joinFacility($owner, $facility, TeamRole::Owner);

    $this->actingAs($member)->get(route('command.incidence'))->assertForbidden();
    $this->actingAs($owner)->post(route('command.incidence.store'), $payload)->assertForbidden();

    expect(IncidenceTarget::query()->count())->toBe(0);
});

test('command roles see incidence targets only inside their province scope', function () {
    $country = Country::factory()->create();
    $otherCountry = Country::factory()->create();
    $sindh = Province::factory()->create(['country_id' => $country->id, 'name' => 'Sindh Scope']);
    $punjab = Province::factory()->create(['country_id' => $country->id, 'name' => 'Punjab Scope']);
    $abroad = Province::factory()->create(['country_id' => $otherCountry->id, 'name' => 'Abroad Scope']);
    $west = City::factory()->create(['province_id' => $sindh->id, 'name' => 'Karachi West']);
    City::factory()->create(['province_id' => $punjab->id, 'name' => 'Lahore']);
    City::factory()->create(['province_id' => $abroad->id, 'name' => 'Other City']);

    IncidenceTarget::query()->create(['province_id' => $sindh->id, 'year' => 2026, 'month' => null, 'value' => 90]);
    IncidenceTarget::query()->create(['province_id' => $punjab->id, 'year' => 2026, 'month' => null, 'value' => 918273]);
    IncidenceTarget::query()->create(['province_id' => $abroad->id, 'year' => 2026, 'month' => null, 'value' => 777001]);

    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $west->id,
    ]);

    $this->actingAs($officer)
        ->get(route('command.incidence', ['province' => $punjab->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('storeUrl', null)
            ->has('targets', 1)
            ->where('targets.0.province', 'Sindh Scope')
            ->where('targets.0.value', '90')
            ->where('series.0.value', '90')
            ->missing('targets.1'));

    $this->actingAs($officer)
        ->get(route('command.incidence', ['province' => $punjab->id]))
        ->assertDontSee('918273')
        ->assertDontSee('777001')
        ->assertDontSee('Punjab Scope')
        ->assertDontSee('Abroad Scope');

    $provincial = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $provincial->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $sindh->id,
    ]);

    $this->actingAs($provincial)
        ->get(route('command.incidence'))
        ->assertOk()
        ->assertDontSee('918273')
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->has('targets', 1)
            ->where('targets.0.province', 'Sindh Scope'));

    $countryViewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $countryViewer->id,
        'role' => ProgrammeRole::CountryViewer,
        'scope_type' => ScopeType::Country,
        'scope_id' => $country->id,
    ]);

    $this->actingAs($countryViewer)
        ->get(route('command.incidence'))
        ->assertOk()
        ->assertDontSee('777001')
        ->assertDontSee('Abroad Scope')
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->has('targets', 2));
});
