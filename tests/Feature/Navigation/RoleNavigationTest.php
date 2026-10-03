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
            ->where('nav.groups.1.key', 'administration')
            ->has('nav.groups.1.items', 8),
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
        ->assertInertia(fn (Assert $page) => $page->where('nav.shell', 'sidebar'));

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
