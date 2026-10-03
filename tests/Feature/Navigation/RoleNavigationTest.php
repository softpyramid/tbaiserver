<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\AccessRequest;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\Country;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\ScreeningSession;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a tb-ai admin sees the administration group and can open it', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.shell', 'sidebar')
            ->where('nav.groups.0.key', 'command')
            ->where('nav.groups.1.key', 'administration')
            ->has('nav.groups.1.items', 3)
            ->where('nav.groups.1.items.0.key', 'facilities')
            ->where('nav.groups.1.items.1.key', 'data')
            ->where('nav.groups.1.items.2.key', 'people'),
        );

    $this->actingAs($admin)->get(route('facilities.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.daily-returns.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.revisions.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.screening-sessions.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.programme-assignments.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.access-requests.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.teams.index'))->assertOk();
});

test('an in-charge sees facility links and cannot open administration', function () {
    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    joinFacility($owner, $facility, TeamRole::Owner);

    $this->actingAs($owner)
        ->get(route('facilities.home', $facility->team))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $keys = navItemKeys($page);

            expect($keys)->toBe([
                'register',
                'returns_history',
                'screening',
                'my_facility',
                'facility_profile',
                'team_members',
                'account',
            ])->and(navGroupKeys($page))->not->toContain('administration');
        });

    $this->actingAs($owner)->get(route('returns.history', $facility->team))->assertOk();
    $this->actingAs($owner)->get(route('facilities.members', $facility->team))->assertOk();
    $this->actingAs($owner)->get(route('admin.daily-returns.index'))->assertForbidden();
    $this->actingAs($owner)->get(route('admin.users.index'))->assertForbidden();
});

test('a technician sees register screening and my facility only', function () {
    $member = User::factory()->create();
    $facility = Facility::factory()->create();
    joinFacility($member, $facility, TeamRole::Member);

    $this->actingAs($member)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            expect(navItemKeys($page))->toBe([
                'register',
                'screening',
                'my_facility',
                'account',
            ])->and($page->toArray()['props']['nav']['shell'])->toBe('facility');
        });

    $this->actingAs($member)->get(route('facilities.home', $facility->team))->assertOk();
    $this->actingAs($member)->get(route('returns.history', $facility->team))->assertForbidden();
    $this->actingAs($member)->get(route('facilities.members', $facility->team))->assertForbidden();
    $this->actingAs($member)->get(route('admin.screening-sessions.index'))->assertForbidden();
    $this->actingAs($member)->get(route('command.index'))->assertForbidden();
});

test('a city officer sees only facilities in their city', function () {
    $city = City::factory()->create();
    $otherCity = City::factory()->create();
    $own = Facility::factory()->create(['city_id' => $city->id]);
    $other = Facility::factory()->create(['city_id' => $otherCity->id]);
    $own->team->update(['name' => 'West BMU']);
    $other->team->update(['name' => 'Thatta BMU']);

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
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.shell', 'sidebar')
            ->where('nav.groups.0.key', 'command')
            ->has('nav.groups', 1),
        );

    $this->actingAs($officer)
        ->get(route('command.facilities'))
        ->assertOk()
        ->assertSee('West BMU')
        ->assertDontSee('Thatta BMU');

    $this->actingAs($officer)->get(route('facilities.index'))->assertForbidden();
    $this->actingAs($officer)->get(route('admin.daily-returns.index'))->assertForbidden();
    $this->actingAs($officer)->get(route('facilities.create'))->assertForbidden();
});

test('a provincial viewer sees only facilities in their province', function () {
    $province = Province::factory()->create();
    $otherProvince = Province::factory()->create();
    $city = City::factory()->create(['province_id' => $province->id]);
    $otherCity = City::factory()->create(['province_id' => $otherProvince->id]);
    $own = Facility::factory()->create(['city_id' => $city->id]);
    $other = Facility::factory()->create(['city_id' => $otherCity->id]);
    $own->team->update(['name' => 'Sindh BMU']);
    $other->team->update(['name' => 'Punjab BMU']);

    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $province->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('command.facilities'))
        ->assertOk()
        ->assertSee('Sindh BMU')
        ->assertDontSee('Punjab BMU');

    $this->actingAs($viewer)->get(route('command.index'))->assertOk();
    $this->actingAs($viewer)->get(route('admin.teams.index'))->assertForbidden();
});

test('a country viewer sees only facilities in their country', function () {
    $country = Country::factory()->create();
    $otherCountry = Country::factory()->create();
    $province = Province::factory()->create(['country_id' => $country->id]);
    $otherProvince = Province::factory()->create(['country_id' => $otherCountry->id]);
    $city = City::factory()->create(['province_id' => $province->id]);
    $otherCity = City::factory()->create(['province_id' => $otherProvince->id]);
    $own = Facility::factory()->create(['city_id' => $city->id]);
    $other = Facility::factory()->create(['city_id' => $otherCity->id]);
    $own->team->update(['name' => 'Pakistan BMU']);
    $other->team->update(['name' => 'Other Country BMU']);

    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::CountryViewer,
        'scope_type' => ScopeType::Country,
        'scope_id' => $country->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('command.facilities'))
        ->assertOk()
        ->assertSee('Pakistan BMU')
        ->assertDontSee('Other Country BMU');

    $this->actingAs($viewer)->get(route('command.returns'))->assertOk();
    $this->actingAs($viewer)->get(route('admin.users.index'))->assertForbidden();
});

test('a senior technician sees the facility profile and not owner-only links', function () {
    $senior = User::factory()->create();
    $facility = Facility::factory()->create();
    joinFacility($senior, $facility, TeamRole::Admin);

    $this->actingAs($senior)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            expect(navItemKeys($page))->toBe([
                'register',
                'screening',
                'my_facility',
                'facility_profile',
                'team_members',
                'account',
            ])->and(navGroupKeys($page))->toBe(['facility']);
        });

    $this->actingAs($senior)->get(route('facilities.edit', $facility->team))->assertOk();
    $this->actingAs($senior)->get(route('returns.history', $facility->team))->assertForbidden();
    $this->actingAs($senior)->get(route('facilities.members', $facility->team))->assertOk();
    $this->actingAs($senior)->get(route('command.index'))->assertForbidden();
});

test('the parent of the active page is marked open', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('command.sectors.show', ['sector' => 'public']))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $registered = navEntry($page, 'command', 'registered');
            $cumulative = navEntry($page, 'command', 'command');

            expect($registered['expanded'])->toBeTrue()
                ->and($cumulative['expanded'])->toBeFalse()
                ->and(array_column($registered['children'], 'label'))->toBe(['Public', 'Private', 'PPM']);
        });

    $this->actingAs($admin)
        ->get(route('facilities.create'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            expect(navEntry($page, 'administration', 'facilities')['expanded'])->toBeTrue()
                ->and(navEntry($page, 'administration', 'data')['expanded'])->toBeFalse()
                ->and(navEntry($page, 'administration', 'people')['expanded'])->toBeFalse();
        });

    $west = City::factory()->create(['name' => 'Karachi West']);

    $this->actingAs($admin)
        ->get(route('command.districts.show', ['sector' => 'public', 'city' => $west->id]))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            expect(navEntry($page, 'command', 'registered')['expanded'])->toBeTrue();
        });
});

test('pending access requests show a count on the access requests link', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    AccessRequest::query()->create([
        'name' => 'Amina',
        'role' => 'Technician',
        'organisation' => 'Clinic',
        'district' => 'Thatta',
        'email' => 'amina@example.com',
    ]);
    AccessRequest::query()->create([
        'name' => 'Bilal',
        'role' => 'Technician',
        'organisation' => 'Clinic',
        'district' => 'Thatta',
        'email' => 'bilal@example.com',
    ]);
    $approved = AccessRequest::query()->create([
        'name' => 'Sara',
        'role' => 'Technician',
        'organisation' => 'Clinic',
        'district' => 'Thatta',
        'email' => 'sara@example.com',
    ]);
    $approved->forceFill(['status' => 'approved'])->save();

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $people = navEntry($page, 'administration', 'people');
            $access = collect($people['children'])->firstWhere('key', 'access_requests');
            $users = collect($people['children'])->firstWhere('key', 'users');

            expect($access['badge'])->toBe(2)
                ->and($users['badge'])->toBeNull()
                ->and($people['badge'])->toBeNull();
        });
});

test('hidden command and administration urls return 403', function () {
    $member = User::factory()->create();
    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    joinFacility($member, $facility, TeamRole::Member);
    joinFacility($owner, $facility, TeamRole::Owner);

    $city = City::factory()->create();
    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $city->id,
    ]);

    $this->actingAs($member)->get(route('command.sectors.show', ['sector' => 'public']))->assertForbidden();
    $this->actingAs($member)->get(route('command.districts.show', [
        'sector' => 'public',
        'city' => $facility->city_id,
    ]))->assertForbidden();
    $this->actingAs($member)->get(route('admin.access-requests.index'))->assertForbidden();
    $this->actingAs($member)->get(route('facilities.create'))->assertForbidden();

    $this->actingAs($owner)->get(route('command.sectors.show', ['sector' => 'ppm']))->assertForbidden();
    $this->actingAs($owner)->get(route('command.map'))->assertForbidden();
    $this->actingAs($owner)->get(route('admin.teams.index'))->assertForbidden();

    $this->actingAs($officer)->get(route('admin.access-requests.index'))->assertForbidden();
    $this->actingAs($officer)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($officer)->get(route('admin.programme-assignments.index'))->assertForbidden();
    $this->actingAs($officer)->get(route('facilities.index'))->assertForbidden();
    $this->actingAs($officer)->get(route('command.index'))->assertOk();
});

test('screening session admin views do not include patient identifiers', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $facility = Facility::factory()->create();
    $facility->team->update(['name' => 'Screen BMU']);

    ScreeningSession::query()->create([
        'facility_id' => $facility->id,
        'user_id' => $admin->id,
        'occurred_on' => '2026-04-02',
        'answers' => ['cough' => 'COUGH-YES'],
        'score' => 3,
        'is_suspect' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.screening-sessions.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $rows = json_encode($page->toArray()['props']['rows']);

            expect($rows)->toContain('Screen BMU')
                ->and($rows)->toContain('3')
                ->and($rows)->not->toContain('COUGH-YES')
                ->and($rows)->not->toContain('national_id')
                ->and($rows)->not->toContain('phone')
                ->and($rows)->not->toContain('answers');
        });
});

test('admin edits write an audit log without patient data', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);
    $city = City::factory()->create();
    $facility = Facility::factory()->create();
    $member = User::factory()->create();
    joinFacility($member, $facility, TeamRole::Member);
    $membership = $member->teamMemberships()->where('team_id', $facility->team_id)->first();

    $this->actingAs($admin)
        ->post(route('facilities.store'), facilityFormPayload($city, [
            'name' => 'Audit BMU',
            'code' => 'NTP-AUDIT',
        ]))
        ->assertRedirect();

    $this->actingAs($admin)
        ->patch(route('admin.memberships.update', $membership), [
            'role' => TeamRole::Admin->value,
        ])
        ->assertRedirect();

    $accessRequest = AccessRequest::query()->create([
        'name' => 'Amina',
        'role' => 'Technician',
        'organisation' => 'Clinic',
        'district' => 'Thatta',
        'email' => 'amina@example.com',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.access-requests.update', $accessRequest), [
            'status' => 'approved',
        ])
        ->assertRedirect();

    expect(AuditLog::query()->where('action', 'facility.created')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user.role_changed')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'access_request.approved')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'facility.created')->first()->properties)->not->toHaveKey('national_id');

    $return = DailyReturn::factory()->create([
        'facility_id' => $facility->id,
        'submitted_by' => $member->id,
    ]);

    $this->actingAs(officerOutside())
        ->get(route('command.returns.show', $return))
        ->assertForbidden();
});

function navItemKeys(Assert $page): array
{
    $groups = $page->toArray()['props']['nav']['groups'];

    return collect($groups)
        ->flatMap(fn (array $group): array => array_column($group['items'], 'key'))
        ->values()
        ->all();
}

function navGroupKeys(Assert $page): array
{
    return array_column($page->toArray()['props']['nav']['groups'], 'key');
}

function navEntry(Assert $page, string $groupKey, string $itemKey): array
{
    $group = collect($page->toArray()['props']['nav']['groups'])->firstWhere('key', $groupKey);

    return collect($group['items'])->firstWhere('key', $itemKey);
}

function officerOutside(): User
{
    $other = City::factory()->create();
    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $other->id,
    ]);

    return $officer;
}
