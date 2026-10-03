<?php

use App\Enums\FacilityKind;
use App\Enums\FacilityStatus;
use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\Taluka;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a tb-ai admin can create a facility and open the facilities list', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create();
    $taluka = Taluka::query()->create([
        'city_id' => $city->id,
        'name' => 'Mirpur Sakro',
        'slug' => 'mirpur-sakro',
    ]);

    $this->actingAs($admin)
        ->get(route('facilities.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('facilitiesUrl', route('facilities.index'))
            ->has('rows', 0)
            ->where('createUrl', route('facilities.create')),
        );

    $this->actingAs($admin)
        ->get(route('facilities.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kinds.1.label', 'Hospital lab')
            ->where('facilitiesUrl', route('facilities.index')),
        );

    $this->actingAs($admin)
        ->post(route('facilities.store'), facilityFormPayload($city, [
            'taluka_id' => $taluka->id,
            'kind' => FacilityKind::Hospital->value,
            'sector' => Sector::Ppm->value,
        ]))
        ->assertRedirect();

    $team = Team::query()->where('slug', 'thatta-bmu')->first();

    expect($team)->not->toBeNull()
        ->and($team->is_personal)->toBeFalse()
        ->and($team->members()->count())->toBe(0)
        ->and($team->facility->city_id)->toBe($city->id)
        ->and($team->facility->taluka_id)->toBe($taluka->id)
        ->and($team->facility->sector)->toBe(Sector::Ppm)
        ->and($team->facility->kind)->toBe(FacilityKind::Hospital)
        ->and($team->facility->code)->toBe('NTP-1')
        ->and($team->facility->status)->toBe(FacilityStatus::Active);

    $this->actingAs($admin)
        ->get(route('facilities.edit', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('facility.name', 'Thatta BMU')
            ->where('facility.kind', FacilityKind::Hospital->value)
            ->where('profileUrl', route('facilities.edit', $team))
            ->where('facilitiesUrl', route('facilities.index'))
            ->where('screeningUrl', null),
        );
});

test('a facility slug stays unique when the name is reused', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create();

    $this->actingAs($admin)->post(route('facilities.store'), facilityFormPayload($city));
    $this->actingAs($admin)->post(route('facilities.store'), facilityFormPayload($city, [
        'code' => 'NTP-2',
    ]));

    expect(Team::query()->where('slug', 'thatta-bmu')->exists())->toBeTrue()
        ->and(Team::query()->where('slug', 'thatta-bmu-1')->exists())->toBeTrue();

    $second = Team::query()->where('slug', 'thatta-bmu-1')->first();

    $this->actingAs($admin)
        ->put(route('facilities.update', $second), facilityFormPayload($city, [
            'name' => 'Badin Clinic',
            'code' => 'NTP-2',
            'kind' => FacilityKind::Clinic->value,
        ]))
        ->assertRedirect(route('facilities.edit', ['current_team' => 'badin-clinic']));

    expect(Team::query()->where('slug', 'thatta-bmu-1')->exists())->toBeFalse()
        ->and(Team::query()->where('slug', 'badin-clinic')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('facilities.edit', ['current_team' => 'thatta-bmu-1']))
        ->assertNotFound();
});

test('a tb-ai admin can edit a facility from administration and the change is audited', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $facility = Facility::factory()->create();
    $city = City::factory()->create();
    $member = User::factory()->create();
    joinFacility($member, $facility, TeamRole::Member);

    $this->actingAs($admin)
        ->get(route('facilities.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.href', route('facilities.edit', $facility->team))
            ->where('rows.0.controls.0.kind', 'link')
            ->where('rows.0.controls.0.label', 'Edit')
            ->where('rows.0.controls.0.url', route('facilities.edit', $facility->team)),
        );

    $this->actingAs($admin)
        ->get(route('facilities.edit', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', true));

    $this->actingAs($admin)
        ->put(route('facilities.update', $facility->team), facilityFormPayload($city, [
            'name' => 'Edited BMU',
            'code' => 'NTP-EDIT',
        ]))
        ->assertRedirect();

    $facility->refresh()->load('team');
    $log = AuditLog::query()->where('action', 'facility.updated')->first();

    expect($facility->team->name)->toBe('Edited BMU')
        ->and($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->subject_id)->toBe($facility->id);

    $this->actingAs($member)
        ->put(route('facilities.update', $facility->team), facilityFormPayload($city, [
            'name' => 'Edited BMU',
            'code' => 'NTP-EDIT',
        ]))
        ->assertForbidden();
});

test('a facility owner can edit the profile and cannot create one', function () {
    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    joinFacility($owner, $facility, TeamRole::Owner);
    $city = City::factory()->create();

    $this->actingAs($owner)
        ->get(route('facilities.create'))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('facilities.store'), facilityFormPayload($city))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('facilities.index'))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profileUrl', route('facilities.edit', $facility->team))
            ->where('screeningUrl', route('screening.create', $facility->team))
            ->where('facilitiesUrl', null),
        );

    $this->actingAs($owner)
        ->put(route('facilities.update', $facility->team), facilityFormPayload($city, [
            'name' => 'Updated BMU',
            'code' => null,
            'active' => '0',
        ]))
        ->assertRedirect(route('facilities.edit', ['current_team' => 'updated-bmu']));

    $facility->refresh()->load('team');

    expect($facility->team->name)->toBe('Updated BMU')
        ->and($facility->team->slug)->toBe('updated-bmu')
        ->and($facility->code)->toBeNull()
        ->and($facility->status)->toBe(FacilityStatus::Active)
        ->and($facility->city_id)->toBe($city->id);
});

test('a technician cannot edit or create a facility profile', function () {
    $member = User::factory()->create();
    $facility = Facility::factory()->create();
    joinFacility($member, $facility, TeamRole::Member);

    $this->actingAs($member)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profileUrl', null)
            ->where('screeningUrl', route('screening.create', $facility->team))
            ->where('facilitiesUrl', null),
        );

    $this->actingAs($member)
        ->get(route('facilities.edit', $facility->team))
        ->assertForbidden();

    $this->actingAs($member)
        ->put(route('facilities.update', $facility->team), facilityFormPayload($facility->city))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('facilities.create'))
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('facilities.store'), facilityFormPayload($facility->city))
        ->assertForbidden();
});

test('a senior technician cannot edit a facility profile', function () {
    $senior = User::factory()->create();
    $facility = Facility::factory()->create();
    joinFacility($senior, $facility, TeamRole::Admin);

    $this->actingAs($senior)
        ->get(route('facilities.edit', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', false));

    $this->actingAs($senior)
        ->put(route('facilities.update', $facility->team), facilityFormPayload($facility->city))
        ->assertForbidden();
});

test('programme viewers cannot create or edit a facility profile', function (ProgrammeRole $role) {
    $user = User::factory()->create();
    giveProgrammeRole($user, $role);
    $facility = Facility::factory()->create();

    $this->actingAs($user)
        ->get(route('facilities.edit', $facility->team))
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('facilities.update', $facility->team), facilityFormPayload($facility->city))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('facilities.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('facilities.store'), facilityFormPayload($facility->city))
        ->assertForbidden();
})->with([
    'city officer' => ProgrammeRole::CityOfficer,
    'provincial viewer' => ProgrammeRole::ProvincialViewer,
    'country viewer' => ProgrammeRole::CountryViewer,
]);

test('an owner cannot edit another facility', function () {
    $owner = User::factory()->create();
    $own = Facility::factory()->create();
    $other = Facility::factory()->create();
    joinFacility($owner, $own, TeamRole::Owner);

    $this->actingAs($owner)
        ->get(route('facilities.edit', $other->team))
        ->assertForbidden();
});

test('a programme code stays unique and a taluka must belong to the city', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create();
    $otherCity = City::factory()->create();
    $taluka = Taluka::query()->create([
        'city_id' => $otherCity->id,
        'name' => 'Other taluka',
        'slug' => 'other-taluka',
    ]);

    $this->actingAs($admin)->post(route('facilities.store'), facilityFormPayload($city, [
        'name' => 'First BMU',
        'code' => 'NTP-9',
    ]));

    $this->actingAs($admin)
        ->post(route('facilities.store'), facilityFormPayload($city, [
            'name' => 'Second BMU',
            'code' => 'NTP-9',
        ]))
        ->assertSessionHasErrors('code');

    $this->actingAs($admin)
        ->post(route('facilities.store'), facilityFormPayload($city, [
            'name' => 'Second BMU',
            'code' => 'NTP-10',
            'taluka_id' => $taluka->id,
        ]))
        ->assertSessionHasErrors('taluka_id');

    expect(Facility::query()->count())->toBe(1);

    $this->actingAs($admin)
        ->post(route('facilities.store'), facilityFormPayload($city, [
            'name' => 'Second BMU',
            'code' => null,
        ]))
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('facilities.store'), facilityFormPayload($city, [
            'name' => 'Third BMU',
            'code' => null,
        ]))
        ->assertRedirect();

    expect(Facility::query()->whereNull('code')->count())->toBe(2);
});

test('public signup does not create a facility', function () {
    $this->get('/register')->assertRedirect('/request-access');

    $this->post(route('facilities.store'), [])->assertRedirect(route('login'));

    expect(Facility::query()->count())->toBe(0);
});

function joinFacility(User $user, Facility $facility, TeamRole $role): void
{
    $facility->team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($facility->team);
}

function giveProgrammeRole(User $user, ProgrammeRole $role): void
{
    $scopeType = $role->scopeType();

    $scopeId = match ($scopeType) {
        null => null,
        ScopeType::Country => Country::factory()->create()->id,
        ScopeType::Province => Province::factory()->create()->id,
        ScopeType::City => City::factory()->create()->id,
    };

    ProgrammeAssignment::factory()->create([
        'user_id' => $user->id,
        'role' => $role,
        'scope_type' => $scopeType,
        'scope_id' => $scopeId,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function facilityFormPayload(City $city, array $overrides = []): array
{
    return [
        'name' => 'Thatta BMU',
        'sector' => Sector::Public->value,
        'kind' => FacilityKind::Bmu->value,
        'city_id' => $city->id,
        'taluka_id' => null,
        'code' => 'NTP-1',
        'active' => '1',
        ...$overrides,
    ];
}
