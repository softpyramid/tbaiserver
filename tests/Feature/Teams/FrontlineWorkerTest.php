<?php

use App\Enums\FrontlineDesignation;
use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\Country;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\Membership;
use App\Models\ProgrammeAssignment;
use App\Models\SlidePhoto;
use App\Models\SuspectAssessment;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\RoleHome;
use Database\Seeders\FrontlineWorkerSeeder;
use Database\Seeders\LoginSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableFeature('suspect_screening');
    suspectDraftRuleSet();
});

/**
 * A frontline worker (team role screener) at a facility.
 *
 * @return array{0: User, 1: Facility}
 */
function frontlineWorker(?Facility $facility = null, FrontlineDesignation $designation = FrontlineDesignation::Doctor): array
{
    [$user, $facility] = suspectStaff(TeamRole::Screener, $facility);
    $facility->team->memberships()->where('user_id', $user->id)->update(['designation' => $designation->value]);

    return [$user, $facility];
}

function frontlineStaff(Facility $facility, TeamRole $role): User
{
    $user = User::factory()->create();
    $facility->team->members()->attach($user, ['role' => $role->value]);

    return $user;
}

test('the screener role sits below technician with no team permissions', function () {
    expect(TeamRole::Screener->label())->toBe('Frontline worker')
        ->and(TeamRole::Screener->rosterLabel())->toBe('Frontline worker')
        ->and(TeamRole::Screener->rosterLabelWith(FrontlineDesignation::Lhw))->toBe('Frontline worker (LHW)')
        ->and(TeamRole::Member->rosterLabelWith(FrontlineDesignation::Lhw))->toBe('Technician')
        ->and(TeamRole::Screener->level())->toBe(0)
        ->and(TeamRole::Screener->isAtLeast(TeamRole::Member))->toBeFalse()
        ->and(TeamRole::Screener->permissions())->toBe([])
        ->and(TeamRole::Screener->canWorkReturns())->toBeFalse()
        ->and(TeamRole::Screener->canScreen())->toBeTrue()
        ->and(TeamRole::Member->canWorkReturns())->toBeTrue()
        ->and(TeamRole::Owner->canScreen())->toBeTrue()
        ->and(array_column(TeamRole::rosterOptions(), 'value'))->toBe(['admin', 'member', 'screener'])
        ->and(array_column(FrontlineDesignation::options(), 'label'))->toBe(['Doctor', 'LHW', 'CHW', 'Nurse', 'Other']);
});

test('a screener starts, answers, completes and reads a screening at their facility', function () {
    [$user, $facility] = frontlineWorker();

    $id = (string) $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), [
            'facility' => $facility->team->slug,
            'patient_name' => 'Test Patient',
            'register_number' => 'REG-TEST-9',
            'age_band' => '25_44',
            'sex' => 'male',
        ])
        ->assertCreated()
        ->json('assessment_id');

    $assessment = SuspectAssessment::query()->where('uuid', $id)->firstOrFail();
    expect($assessment->worker_role)->toBe('screener')
        ->and($assessment->facility_id)->toBe($facility->id);

    $this->actingAs($user)->getJson(route('api.suspect.rule-set'))->assertOk();

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => ['cough_current' => 'yes']])
        ->assertOk()
        ->assertJsonPath('scores.symptom', 8);

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.complete', $id))
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    $this->actingAs($user)
        ->getJson(route('api.suspect.assessments.show', $id))
        ->assertOk()
        ->assertJsonPath('status', 'completed');

    $this->actingAs($user)
        ->get(route('suspect.index', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('suspect/index')
            ->where('canScreen', true)
            ->has('rows', 1)
            ->where('rows.0.id', $id));

    $this->actingAs($user)
        ->get(route('suspect.create', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('suspect/new'));

    $this->actingAs($user)
        ->get(route('suspect.show', ['current_team' => $facility->team->slug, 'assessment' => $id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('suspect/show')
            ->where('newUrl', route('suspect.create', $facility->team)));
});

test('a screener cannot see or touch another facility screening', function () {
    [$user] = frontlineWorker();
    [$otherStaff, $other] = suspectStaff(TeamRole::Member);

    $id = (string) $this->actingAs($otherStaff)
        ->postJson(route('api.suspect.assessments.store'), [
            'facility' => $other->team->slug,
            'patient_name' => 'Test Patient',
            'register_number' => 'REG-TEST-2',
            'age_band' => '25_44',
            'sex' => 'female',
        ])
        ->assertCreated()
        ->json('assessment_id');

    $this->actingAs($user)->getJson(route('api.suspect.assessments.show', $id))->assertNotFound();
    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.answers', $id), ['answers' => ['cough_current' => 'yes']])
        ->assertNotFound();
    $this->actingAs($user)->postJson(route('api.suspect.assessments.complete', $id))->assertNotFound();

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), [
            'facility' => $other->team->slug,
            'patient_name' => 'Test Patient',
            'register_number' => 'REG-TEST-3',
            'age_band' => '25_44',
            'sex' => 'female',
        ])
        ->assertForbidden();

    $this->actingAs($user)->get(route('suspect.index', $other->team))->assertForbidden();
    $this->actingAs($user)
        ->get(route('suspect.show', ['current_team' => $other->team->slug, 'assessment' => $id]))
        ->assertForbidden();
});

test('in-charge, senior technician and technician can still screen', function (TeamRole $role) {
    [$user, $facility] = suspectStaff($role);

    $this->actingAs($user)
        ->postJson(route('api.suspect.assessments.store'), [
            'facility' => $facility->team->slug,
            'patient_name' => 'Test Patient',
            'register_number' => 'REG-TEST-4',
            'age_band' => '25_44',
            'sex' => 'female',
        ])
        ->assertCreated();

    $this->actingAs($user)->get(route('suspect.index', $facility->team))->assertOk();
    $this->actingAs($user)->get(route('suspect.create', $facility->team))->assertOk();
})->with([
    'in-charge' => TeamRole::Owner,
    'senior technician' => TeamRole::Admin,
    'technician' => TeamRole::Member,
]);

test('a screener is refused the daily return, history, slide photos and facility area', function () {
    Storage::fake('slides');
    Storage::fake('public');
    enableFeature('screening');

    [$user, $facility] = frontlineWorker();
    $team = $facility->team;
    $technician = frontlineStaff($facility, TeamRole::Member);
    $return = DailyReturn::factory()->create([
        'facility_id' => $facility->id,
        'submitted_by' => $technician->id,
        'reported_on' => now()->toDateString(),
    ]);
    $this->actingAs($technician)
        ->post(route('returns.slides.store', ['current_team' => $team->slug, 'dailyReturn' => $return->id]), [
            'photos' => [UploadedFile::fake()->image('slide.jpg', 800, 600)],
        ])
        ->assertSessionHasNoErrors();
    $photo = SlidePhoto::query()->where('daily_return_id', $return->id)->firstOrFail();

    $this->actingAs($user)->get(route('returns.today', $team))->assertForbidden();
    $this->actingAs($user)
        ->get(route('returns.today', ['current_team' => $team->slug, 'reported_on' => now()->subDays(3)->toDateString()]))
        ->assertForbidden();

    foreach ([now()->toDateString(), now()->subDays(5)->toDateString()] as $date) {
        $this->actingAs($user)
            ->put(route('returns.update', $team), [
                'reported_on' => $date,
                'suspects' => 4,
                'tested' => 3,
                'positive' => 1,
                'negative' => 2,
                'started_treatment' => 1,
                'follow_up' => 0,
            ])
            ->assertForbidden();
    }

    expect(DailyReturn::query()->where('facility_id', $facility->id)->count())->toBe(1)
        ->and($return->fresh()->submitted_by)->toBe($technician->id);

    $this->actingAs($user)->get(route('returns.history', $team))->assertForbidden();

    $this->actingAs($user)
        ->post(route('returns.slides.store', ['current_team' => $team->slug, 'dailyReturn' => $return->id]), [
            'photos' => [UploadedFile::fake()->image('slide.jpg', 800, 600)],
        ])
        ->assertForbidden();
    $this->actingAs($user)
        ->delete(route('returns.slides.destroy', ['current_team' => $team->slug, 'slidePhoto' => $photo->id]))
        ->assertForbidden();
    $this->actingAs($user)->get(route('slide-photos.show', $photo))->assertForbidden();

    expect(SlidePhoto::query()->where('daily_return_id', $return->id)->count())->toBe(1);

    $this->actingAs($user)->get(route('facilities.home', $team))->assertForbidden();
    $this->actingAs($user)->get(route('facilities.edit', $team))->assertForbidden();
    $this->actingAs($user)
        ->put(route('facilities.update', $team), ['name' => 'Renamed by screener'])
        ->assertForbidden();
    $this->actingAs($user)->get(route('facilities.members', $team))->assertForbidden();
    $this->actingAs($user)->get(route('facilities.invites', $team))->assertForbidden();
    $this->actingAs($user)->get(route('facilities.activity', $team))->assertForbidden();

    $this->actingAs($user)->get(route('screening.create', $team))->assertForbidden();
    $this->actingAs($user)
        ->post(route('screening.store', $team), ['occurred_on' => now()->toDateString()])
        ->assertForbidden();

    expect($team->fresh()->name)->not->toBe('Renamed by screener');
});

test('a screener cannot manage members or invitations', function () {
    Notification::fake();
    [$user, $facility] = frontlineWorker();
    $team = $facility->team;
    $technician = frontlineStaff($facility, TeamRole::Member);
    $invitation = TeamInvitation::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->post(route('teams.invitations.store', $team), ['email' => 'new@example.com', 'role' => 'member'])
        ->assertForbidden();
    $this->actingAs($user)
        ->delete(route('teams.invitations.destroy', ['team' => $team->slug, 'invitation' => $invitation->code]))
        ->assertForbidden();
    $this->actingAs($user)
        ->post(route('facilities.invitations.resend', ['current_team' => $team->slug, 'invitation' => $invitation->code]))
        ->assertForbidden();
    $this->actingAs($user)
        ->patch(route('teams.members.update', ['team' => $team->slug, 'user' => $technician->id]), ['role' => 'admin'])
        ->assertForbidden();
    $this->actingAs($user)
        ->delete(route('teams.members.destroy', ['team' => $team->slug, 'user' => $technician->id]))
        ->assertForbidden();
    $this->actingAs($user)
        ->post(route('facilities.members.active', ['current_team' => $team->slug, 'user' => $technician->id]), ['active' => '0'])
        ->assertForbidden();

    expect(TeamInvitation::query()->where('email', 'new@example.com')->exists())->toBeFalse()
        ->and($technician->teamRole($team))->toBe(TeamRole::Member);
});

test('a screener is refused admin and command center routes', function () {
    [$user, $facility] = frontlineWorker();

    foreach ([
        route('command.index'),
        route('command.center'),
        route('command.map'),
        route('command.alerts'),
        route('command.returns'),
        route('command.facilities'),
        route('facilities.index'),
        route('admin.users.index'),
        route('admin.daily-returns.index'),
        route('admin.revisions.index'),
        route('admin.programme-access.index'),
        route('admin.audit-log.index'),
        route('admin.suspect-rules.index'),
        route('admin.suspect-assessments.index'),
        route('admin.population-denominators.index'),
    ] as $url) {
        $this->actingAs($user)->get($url)->assertForbidden();
    }

    $this->actingAs($user)->getJson(route('api.command.frame'))->assertForbidden();
    $this->actingAs($user)->getJson(route('api.command.map'))->assertForbidden();

    $membership = Membership::query()->where('user_id', $user->id)->where('team_id', $facility->team_id)->firstOrFail();
    $this->actingAs($user)
        ->patch(route('admin.memberships.update', $membership), ['role' => 'owner'])
        ->assertForbidden();

    expect($membership->fresh()->role)->toBe(TeamRole::Screener);
});

test('an in-charge invites a frontline worker with a designation and acceptance keeps it', function () {
    Notification::fake();
    $facility = Facility::factory()->create();
    $owner = frontlineStaff($facility, TeamRole::Owner);
    $team = $facility->team;

    $this->actingAs($owner)
        ->post(route('teams.invitations.store', $team), ['email' => 'worker@example.com', 'role' => 'screener'])
        ->assertSessionHasErrors(['designation' => 'Choose a designation for a frontline worker.']);

    $this->actingAs($owner)
        ->post(route('teams.invitations.store', $team), ['email' => 'worker@example.com', 'role' => 'screener', 'designation' => 'surgeon'])
        ->assertSessionHasErrors('designation');

    $this->actingAs($owner)
        ->post(route('teams.invitations.store', $team), ['email' => 'tech@example.com', 'role' => 'member', 'designation' => 'doctor'])
        ->assertSessionHasErrors(['designation' => 'Only a frontline worker has a designation.']);

    expect(TeamInvitation::query()->count())->toBe(0);

    $this->actingAs($owner)
        ->post(route('teams.invitations.store', $team), ['email' => 'worker@example.com', 'role' => 'screener', 'designation' => 'lhw'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('facilities.invites', $team));

    $this->actingAs($owner)
        ->post(route('teams.invitations.store', $team), ['email' => 'tech@example.com', 'role' => 'member'])
        ->assertSessionHasNoErrors();

    $invitation = TeamInvitation::query()->where('email', 'worker@example.com')->firstOrFail();
    expect($invitation->role)->toBe(TeamRole::Screener)
        ->and($invitation->designation)->toBe(FrontlineDesignation::Lhw)
        ->and(TeamInvitation::query()->where('email', 'tech@example.com')->value('designation'))->toBeNull();

    $this->actingAs($owner)
        ->get(route('facilities.invites', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('area.inviteRoles.2.value', 'screener')
            ->where('area.inviteRoles.2.label', 'Frontline worker')
            ->has('area.inviteDesignations', 5)
            ->where('invitations', fn ($rows) => collect($rows)->contains('roleLabel', 'Frontline worker (LHW)')));

    $worker = User::factory()->create(['email' => 'worker@example.com']);

    $this->actingAs($worker)
        ->post(route('invitations.accept', $invitation))
        ->assertRedirect(route('dashboard'));

    $membership = Membership::query()->where('team_id', $team->id)->where('user_id', $worker->id)->firstOrFail();
    expect($membership->role)->toBe(TeamRole::Screener)
        ->and($membership->designation)->toBe(FrontlineDesignation::Lhw)
        ->and(app(RoleHome::class)->url($worker->fresh()))->toBe(route('suspect.index', $team));
});

test('a senior technician can invite a frontline worker', function () {
    Notification::fake();
    $facility = Facility::factory()->create();
    $senior = frontlineStaff($facility, TeamRole::Admin);

    $this->actingAs($senior)
        ->post(route('teams.invitations.store', $facility->team), ['email' => 'nurse@example.com', 'role' => 'screener', 'designation' => 'nurse'])
        ->assertSessionHasNoErrors();

    expect(TeamInvitation::query()->where('email', 'nurse@example.com')->firstOrFail()->designation)->toBe(FrontlineDesignation::Nurse);
});

test('the in-charge changes a member to a frontline worker and back, and the roster shows the designation', function () {
    $facility = Facility::factory()->create();
    $owner = frontlineStaff($facility, TeamRole::Owner);
    $technician = frontlineStaff($facility, TeamRole::Member);
    $team = $facility->team;
    $url = route('teams.members.update', ['team' => $team->slug, 'user' => $technician->id]);

    $this->actingAs($owner)->patch($url, ['role' => 'screener'])->assertSessionHasErrors('designation');
    $this->actingAs($owner)->patch($url, ['role' => 'member', 'designation' => 'chw'])->assertSessionHasErrors('designation');
    $this->actingAs($owner)->patch($url, ['role' => 'screener:doctor', 'designation' => 'nurse'])->assertSessionHasErrors('designation');
    $this->actingAs($owner)->patch($url, ['role' => 'owner'])->assertSessionHasErrors('role');

    $this->actingAs($owner)->patch($url, ['role' => 'screener:chw'])->assertSessionHasNoErrors();

    $membership = Membership::query()->where('team_id', $team->id)->where('user_id', $technician->id)->firstOrFail();
    expect($membership->role)->toBe(TeamRole::Screener)
        ->and($membership->designation)->toBe(FrontlineDesignation::Chw);

    $this->actingAs($owner)->patch($url, ['role' => 'screener', 'designation' => 'other'])->assertSessionHasNoErrors();
    expect($membership->fresh()->designation)->toBe(FrontlineDesignation::Other);

    $this->actingAs($owner)
        ->get(route('facilities.members', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $technician->id
                && $row['role'] === 'screener'
                && $row['roleLabel'] === 'Frontline worker (Other)'
                && collect($row['actions'])->contains(fn ($action) => $action['kind'] === 'roles'
                    && collect($action['options'])->pluck('value')->all() === [
                        'admin', 'member', 'screener:doctor', 'screener:lhw', 'screener:chw', 'screener:nurse', 'screener:other',
                    ])))
            ->where('roleOptions', fn ($options) => collect($options)->contains('value', 'screener'))
            ->where('rolesInfo.roles', fn ($roles) => collect($roles)->contains('role', 'Frontline worker')));

    $this->actingAs($owner)->patch($url, ['role' => 'member'])->assertSessionHasNoErrors();

    expect($membership->fresh()->role)->toBe(TeamRole::Member)
        ->and($membership->fresh()->designation)->toBeNull();
});

test('a tb-ai admin assigns the frontline worker role with a designation from people and access', function () {
    $admin = suspectAdmin();
    $facility = Facility::factory()->create();
    $technician = frontlineStaff($facility, TeamRole::Member);
    $membership = Membership::query()->where('team_id', $facility->team_id)->where('user_id', $technician->id)->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('admin.memberships.update', $membership), ['role' => 'screener'])
        ->assertSessionHasErrors('designation');

    $this->actingAs($admin)
        ->patch(route('admin.memberships.update', $membership), ['role' => 'screener:lhw'])
        ->assertSessionHasNoErrors();

    expect($membership->fresh()->role)->toBe(TeamRole::Screener)
        ->and($membership->fresh()->designation)->toBe(FrontlineDesignation::Lhw);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['q' => $technician->email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.cells.role', 'Frontline worker (LHW)')
            ->where('rows.0.controls.0.value', 'screener:lhw')
            ->where('rows.0.controls.0.options', fn ($options) => collect($options)->contains('label', 'Frontline worker (Doctor)')
                && collect($options)->contains('value', 'owner')));

    $this->actingAs($admin)
        ->patch(route('admin.memberships.update', $membership), ['role' => 'admin', 'designation' => 'lhw'])
        ->assertSessionHasErrors('designation');

    $this->actingAs($admin)
        ->patch(route('admin.memberships.update', $membership), ['role' => 'admin'])
        ->assertSessionHasNoErrors();

    expect($membership->fresh()->role)->toBe(TeamRole::Admin)
        ->and($membership->fresh()->designation)->toBeNull();
});

test('a screener signs in to the suspect screening list, or the account page when it is off', function () {
    [$user, $facility] = frontlineWorker();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('suspect.index', $facility->team));

    $this->get(route('login'))->assertRedirect(route('suspect.index', $facility->team));
    $this->get(route('returns.home'))->assertRedirect(route('suspect.index', $facility->team));

    config(['tbai.features.suspect_screening' => false]);

    expect(app(RoleHome::class)->url($user->fresh()))->toBe(route('profile.edit'));
});

test('a screener sees only suspect screening and account in the navigation', function () {
    [$user, $facility] = frontlineWorker();

    $this->actingAs($user)
        ->get(route('suspect.index', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.shell', 'facility')
            ->has('nav.groups', 1)
            ->where('nav.groups.0.items', fn ($items) => collect($items)->pluck('key')->all() === ['suspect_screening'])
            ->where('nav.tabs', fn ($tabs) => collect($tabs)->pluck('id')->all() === ['suspect_screening', 'account'])
            ->where('nav.tabs', fn ($tabs) => collect($tabs)->pluck('icon')->all() === ['suspect_screening', 'account'])
            ->where('nav.activeTab', 'suspect_screening')
            ->where('nav.more', [])
            ->where('registerUrl', null)
            ->where('myFacilityUrl', null)
            ->where('profileUrl', null));

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.tabs', fn ($tabs) => collect($tabs)->pluck('id')->all() === ['suspect_screening', 'account'])
            ->where('nav.activeTab', 'account'));

    config(['tbai.features.suspect_screening' => false]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.groups', [])
            ->where('nav.tabs', fn ($tabs) => collect($tabs)->pluck('id')->all() === ['account']));
});

test('a technician keeps the full facility navigation', function () {
    [$user, $facility] = suspectStaff(TeamRole::Member);

    $this->actingAs($user)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.groups.0.items', fn ($items) => collect($items)->pluck('key')->all() === ['register', 'suspect_screening', 'my_facility'])
            ->where('nav.tabs', fn ($tabs) => collect($tabs)->pluck('id')->all() === ['register', 'suspect_screening', 'facility', 'account']));
});

test('the frontline worker seeder adds screener@tbai.test once at karachi west bmu', function () {
    $this->app['env'] = 'local';
    config(['access.seed_login_password' => null]);

    $this->seed(LoginSeeder::class);
    $this->seed(FrontlineWorkerSeeder::class);

    $users = User::query()->where('email', 'screener@tbai.test')->get();
    expect($users)->toHaveCount(1);

    $worker = $users->first();
    $memberships = Membership::query()->where('user_id', $worker->id)->with('team')->get();
    $facilityMemberships = $memberships->filter(fn (Membership $membership) => ! $membership->team->is_personal);

    expect($worker->name)->toBe('Demo Frontline Worker')
        ->and($worker->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $worker->password))->toBeTrue()
        ->and($worker->personalTeam())->not->toBeNull()
        ->and($facilityMemberships)->toHaveCount(1)
        ->and($facilityMemberships->first()->team->slug)->toBe('karachi-west-bmu')
        ->and($facilityMemberships->first()->role)->toBe(TeamRole::Screener)
        ->and($facilityMemberships->first()->designation)->toBe(FrontlineDesignation::Doctor)
        ->and(app(RoleHome::class)->url($worker))->toBe(route('suspect.index', 'karachi-west-bmu'));

    // An admin edit survives a rerun; the account is not duplicated.
    $facilityMemberships->first()->update(['designation' => FrontlineDesignation::Lhw]);
    $this->seed(FrontlineWorkerSeeder::class);

    expect(User::query()->where('email', 'screener@tbai.test')->count())->toBe(1)
        ->and(Membership::query()->where('user_id', $worker->id)->count())->toBe(2)
        ->and($facilityMemberships->first()->fresh()->designation)->toBe(FrontlineDesignation::Lhw);
});

test('the frontline worker seeder skips production', function () {
    $this->app['env'] = 'production';

    (new FrontlineWorkerSeeder)->run();

    expect(User::query()->where('email', 'screener@tbai.test')->exists())->toBeFalse();
});

test('a programme viewer still cannot screen', function () {
    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::CountryViewer,
        'scope_type' => ScopeType::Country,
        'scope_id' => Country::query()->firstOrCreate(['slug' => 'pakistan'], ['name' => 'Pakistan'])->id,
    ]);
    $facility = Facility::factory()->create();

    $this->actingAs($viewer)
        ->postJson(route('api.suspect.assessments.store'), [
            'facility' => $facility->team->slug,
            'patient_name' => 'Test Patient',
            'register_number' => 'REG-TEST-5',
            'age_band' => '25_44',
            'sex' => 'female',
        ])
        ->assertForbidden();
});
