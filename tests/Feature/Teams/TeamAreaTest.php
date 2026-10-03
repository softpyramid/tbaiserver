<?php

use App\Enums\ProgrammeRole;
use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('team tabs follow the role and hidden urls stay forbidden', function () {
    $owner = User::factory()->create();
    $senior = User::factory()->create();
    $technician = User::factory()->create();
    $officer = User::factory()->create();
    $facility = Facility::factory()->create();
    joinFacility($owner, $facility, TeamRole::Owner);
    joinFacility($senior, $facility, TeamRole::Admin);
    joinFacility($technician, $facility, TeamRole::Member);
    giveProgrammeRole($officer, ProgrammeRole::CityOfficer);

    $tabs = ['members', 'invites', 'roles', 'activity', 'profile'];

    $this->actingAs($owner)
        ->get(route('facilities.members', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('area.tabs.0.id', 'members')
            ->where('area.tabs.0.current', true)
            ->where('area.tabs.1.id', 'invites')
            ->where('area.tabs.2.id', 'roles')
            ->where('area.tabs.3.id', 'activity')
            ->where('area.tabs.4.id', 'profile')
            ->where('area.canInvite', true)
            ->has('area.tabs', count($tabs)),
        );

    $this->actingAs($senior)
        ->get(route('facilities.invites', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('area.tabs', 5)
            ->where('area.tabs.4.id', 'profile')
            ->where('area.canInvite', true),
        );

    foreach ([
        'facilities.members',
        'facilities.invites',
        'facilities.roles',
        'facilities.activity',
        'facilities.edit',
    ] as $name) {
        $this->actingAs($technician)->get(route($name, $facility->team))->assertForbidden();
        $this->actingAs($officer)->get(route($name, $facility->team))->assertForbidden();
    }
});

test('an email invite does not create a user and writes an audit row', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    joinFacility($owner, $facility, TeamRole::Owner);
    $before = User::query()->count();

    $this->actingAs($owner)
        ->post(route('teams.invitations.store', $facility->team), [
            'email' => 'new.technician@example.com',
            'role' => TeamRole::Member->value,
        ])
        ->assertRedirect(route('facilities.invites', $facility->team));

    expect(User::query()->count())->toBe($before)
        ->and(User::query()->where('email', 'new.technician@example.com')->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'team.invitation.sent')->where('properties->team_id', $facility->team_id)->exists())->toBeTrue();

    Notification::assertSentOnDemand(TeamInvitationNotification::class);
});

test('changing a role writes an audit row and the last owner is protected', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $technician = User::factory()->create();
    $facility = Facility::factory()->create();
    joinFacility($owner, $facility, TeamRole::Owner);
    joinFacility($otherOwner, $facility, TeamRole::Owner);
    joinFacility($technician, $facility, TeamRole::Member);

    $this->actingAs($owner)
        ->patch(route('teams.members.update', [$facility->team, $owner]), [
            'role' => TeamRole::Admin->value,
        ])
        ->assertForbidden();

    expect($owner->fresh()->teamRole($facility->team))->toBe(TeamRole::Owner);

    $this->actingAs($owner)
        ->patch(route('teams.members.update', [$facility->team, $otherOwner]), [
            'role' => TeamRole::Member->value,
        ])
        ->assertRedirect(route('facilities.members', $facility->team));

    expect($otherOwner->fresh()->teamRole($facility->team))->toBe(TeamRole::Member)
        ->and(AuditLog::query()->where('action', 'team.member.role_updated')->where('properties->team_id', $facility->team_id)->exists())->toBeTrue();

    $this->actingAs($owner)
        ->patch(route('teams.members.update', [$facility->team, $owner]), [
            'role' => TeamRole::Admin->value,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('facilities.members.active', [$facility->team, $owner]), [
            'active' => '0',
        ])
        ->assertForbidden();

    expect($owner->fresh()->deactivated_at)->toBeNull()
        ->and($owner->fresh()->teamRole($facility->team))->toBe(TeamRole::Owner);

    $this->actingAs($owner)
        ->post(route('facilities.members.active', [$facility->team, $technician]), [
            'active' => '0',
        ])
        ->assertRedirect();

    expect($technician->fresh()->deactivated_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'team.member.deactivated')->exists())->toBeTrue();
});

test('the members page shows returns this month and last active', function () {
    $this->travelTo(now()->startOfMinute());

    $owner = User::factory()->create(['name' => 'Amina Owner']);
    $technician = User::factory()->create(['name' => 'Bilal Technician']);
    $facility = Facility::factory()->create();
    $facility->team->update(['name' => 'West BMU']);
    joinFacility($owner, $facility, TeamRole::Owner);
    joinFacility($technician, $facility, TeamRole::Member);
    $technician->forceFill(['last_seen_at' => now()->subMinutes(30)])->save();

    DailyReturn::factory()->create([
        'facility_id' => $facility->id,
        'submitted_by' => $technician->id,
        'reported_on' => now()->toDateString(),
    ]);

    $this->actingAs($owner)
        ->get(route('facilities.members', $facility->team))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($technician) {
            $row = collect($page->toArray()['props']['members'])->firstWhere('id', $technician->id);

            expect($row['roleLabel'])->toBe('Technician')
                ->and($row['returnsThisMonth'])->toBe(1)
                ->and($row['lastActive'])->toBe('30 mins ago')
                ->and($row['activeNow'])->toBeFalse()
                ->and($page->toArray()['props']['cards'][0]['label'])->toBe('Total members');
        });
});

test('administration teams filter by district and open the members tab', function () {
    $admin = User::factory()->create(['name' => 'Programme Admin']);
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $west = City::factory()->create(['name' => 'Karachi West']);
    $thatta = City::factory()->create(['name' => 'Thatta']);
    $facility = Facility::factory()->create([
        'city_id' => $west->id,
        'sector' => Sector::Public,
    ]);
    $facility->team->update(['name' => 'West BMU']);
    $owner = User::factory()->create(['name' => 'West Incharge']);
    joinFacility($owner, $facility, TeamRole::Owner);

    $other = Facility::factory()->create([
        'city_id' => $thatta->id,
        'sector' => Sector::Private,
    ]);
    $other->team->update(['name' => 'Thatta BMU']);

    $this->actingAs($admin)
        ->get(route('admin.teams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('title', 'Teams')
            ->where('rows', fn ($rows) => collect($rows)->contains(
                fn (array $row): bool => $row['team'] === 'West BMU'
                    && $row['district'] === 'Karachi West'
                    && $row['href'] === route('facilities.members', $facility->team),
            )),
        );

    $this->actingAs($admin)
        ->get(route('admin.teams.index', ['district' => $thatta->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows', fn ($rows) => collect($rows)->contains(fn (array $row): bool => $row['team'] === 'Thatta BMU')
                && collect($rows)->doesntContain(fn (array $row): bool => $row['team'] === 'West BMU')),
        );

    $this->actingAs($admin)
        ->get(route('admin.teams.index', ['sector' => Sector::Private->value]))
        ->assertOk()
        ->assertDontSee('West BMU');

    $this->actingAs($admin)
        ->get(route('admin.teams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.1.key', 'type')
            ->where('columns.1.label', 'Type')
            ->where('rows', fn ($rows) => collect($rows)->contains(
                fn (array $row): bool => $row['team'] === "Programme Admin's Team"
                    && $row['type'] === 'Personal'
                    && $row['href'] === null,
            ) && collect($rows)->contains(
                fn (array $row): bool => $row['team'] === 'West BMU' && $row['type'] === 'Facility',
            )),
        );
});
