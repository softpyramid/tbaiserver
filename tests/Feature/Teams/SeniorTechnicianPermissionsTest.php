<?php

use App\Enums\FrontlineDesignation;
use App\Enums\ProgrammeRole;
use App\Enums\TeamRole;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Karachi West style facility with one person per facility role, a
 * technician and a frontline worker to act on, and two open invitations.
 *
 * @return array<string, mixed>
 */
function permFacility(): array
{
    $facility = Facility::factory()->create();
    $join = function (TeamRole $role, ?FrontlineDesignation $designation = null) use ($facility): User {
        $user = User::factory()->create();
        $facility->team->memberships()->create(['user_id' => $user->id, 'role' => $role, 'designation' => $designation]);
        $user->switchTeam($facility->team);

        return $user;
    };

    $owner = $join(TeamRole::Owner);

    $invite = fn (string $email, TeamRole $role, ?FrontlineDesignation $designation = null): TeamInvitation => $facility->team->invitations()->create([
        'email' => $email,
        'role' => $role,
        'designation' => $designation,
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(3),
    ]);

    return [
        'facility' => $facility,
        'owner' => $owner,
        'senior' => $join(TeamRole::Admin),
        'technician' => $join(TeamRole::Member),
        'screener' => $join(TeamRole::Screener, FrontlineDesignation::Doctor),
        'targetTechnician' => $join(TeamRole::Member),
        'targetScreener' => $join(TeamRole::Screener, FrontlineDesignation::Doctor),
        'technicianInvite' => $invite('pending.technician@example.com', TeamRole::Member),
        'seniorInvite' => $invite('pending.senior@example.com', TeamRole::Admin),
    ];
}

function permActor(string $actor, array $world): User
{
    if (isset($world[$actor]) && $world[$actor] instanceof User) {
        return $world[$actor];
    }

    $user = User::factory()->create();

    if ($actor === 'other_facility') {
        $other = Facility::factory()->create();
        $other->team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $user->switchTeam($other->team);
    }

    if ($actor === 'programme_viewer') {
        ProgrammeAssignment::factory()->create([
            'user_id' => $user->id,
            'role' => ProgrammeRole::ProvincialViewer,
            'scope_type' => ProgrammeRole::ProvincialViewer->scopeType(),
            'scope_id' => Province::factory()->create()->id,
        ]);
    }

    if ($actor === 'tbai_admin') {
        ProgrammeAssignment::factory()->create([
            'user_id' => $user->id,
            'role' => ProgrammeRole::TbaiAdmin,
            'scope_type' => null,
            'scope_id' => null,
        ]);
    }

    return $user;
}

/**
 * Each action: the request, and a check that its change happened.
 *
 * @return array<string, array{0: callable(array<string, mixed>): array{0: string, 1: string, 2: array<string, string>}, 1: callable(array<string, mixed>): bool}>
 */
function permActions(): array
{
    $invite = fn (string $role, ?string $designation = null) => [
        fn (array $w): array => ['post', route('teams.invitations.store', $w['facility']->team), array_filter([
            'email' => 'new.'.$role.'@example.com',
            'role' => $role,
            'designation' => $designation,
        ])],
        fn (array $w): bool => $w['facility']->team->invitations()->where('email', 'new.'.$role.'@example.com')->exists(),
    ];

    return [
        'invite_technician' => $invite(TeamRole::Member->value),
        'invite_screener' => $invite(TeamRole::Screener->value, FrontlineDesignation::Lhw->value),
        'invite_senior' => $invite(TeamRole::Admin->value),
        'invite_owner' => $invite(TeamRole::Owner->value),
        'change_role' => [
            fn (array $w): array => ['patch', route('teams.members.update', [$w['facility']->team, $w['targetTechnician']]), ['role' => TeamRole::Admin->value]],
            fn (array $w): bool => $w['targetTechnician']->fresh()->teamRole($w['facility']->team) === TeamRole::Admin,
        ],
        'change_designation' => [
            fn (array $w): array => ['patch', route('teams.members.update', [$w['facility']->team, $w['targetScreener']]), ['role' => 'screener:'.FrontlineDesignation::Lhw->value]],
            fn (array $w): bool => $w['facility']->team->memberships()->where('user_id', $w['targetScreener']->id)->value('designation') === FrontlineDesignation::Lhw,
        ],
        'remove_member' => [
            fn (array $w): array => ['delete', route('teams.members.destroy', [$w['facility']->team, $w['targetTechnician']]), []],
            fn (array $w): bool => ! $w['facility']->team->memberships()->where('user_id', $w['targetTechnician']->id)->exists(),
        ],
        'deactivate_member' => [
            fn (array $w): array => ['post', route('facilities.members.active', [$w['facility']->team, $w['targetTechnician']]), ['active' => '0']],
            fn (array $w): bool => $w['targetTechnician']->fresh()->deactivated_at !== null,
        ],
        'cancel_technician_invitation' => [
            fn (array $w): array => ['delete', route('teams.invitations.destroy', [$w['facility']->team, $w['technicianInvite']]), []],
            fn (array $w): bool => ! TeamInvitation::query()->whereKey($w['technicianInvite']->id)->exists(),
        ],
        'cancel_senior_invitation' => [
            fn (array $w): array => ['delete', route('teams.invitations.destroy', [$w['facility']->team, $w['seniorInvite']]), []],
            fn (array $w): bool => ! TeamInvitation::query()->whereKey($w['seniorInvite']->id)->exists(),
        ],
    ];
}

/**
 * ok: done; forbidden: 403 and nothing changed; invalid: validation error
 * on the role and nothing changed.
 *
 * @return array<string, array<string, string>>
 */
function permExpectations(): array
{
    $none = array_fill_keys(array_keys(permActions()), 'forbidden');

    return [
        'owner' => [
            'invite_technician' => 'ok',
            'invite_screener' => 'ok',
            'invite_senior' => 'ok',
            'invite_owner' => 'invalid',
            'change_role' => 'ok',
            'change_designation' => 'ok',
            'remove_member' => 'ok',
            'deactivate_member' => 'ok',
            'cancel_technician_invitation' => 'ok',
            'cancel_senior_invitation' => 'ok',
        ],
        'senior' => [
            ...$none,
            'invite_technician' => 'ok',
            'invite_screener' => 'ok',
            'invite_owner' => 'invalid',
            'cancel_technician_invitation' => 'ok',
        ],
        'technician' => $none,
        'screener' => $none,
        'other_facility' => $none,
        'programme_viewer' => $none,
        // Admin powers sit in Administration, People; on the facility roster
        // a TB-AI admin may deactivate an account, as before.
        'tbai_admin' => [...$none, 'deactivate_member' => 'ok'],
    ];
}

dataset('permission matrix', function () {
    foreach (permExpectations() as $actor => $actions) {
        foreach ($actions as $action => $outcome) {
            yield "{$actor} {$action} is {$outcome}" => [$actor, $action, $outcome];
        }
    }
});

test('facility staff permissions', function (string $actor, string $action, string $outcome) {
    Notification::fake();

    $world = permFacility();
    $user = permActor($actor, $world);
    [$request, $changed] = permActions()[$action];
    [$method, $url, $data] = $request($world);
    $invitations = TeamInvitation::query()->count();

    $response = $this->actingAs($user)->{$method}($url, $data);

    match ($outcome) {
        'ok' => $response->assertRedirect()->assertSessionHasNoErrors(),
        'forbidden' => $response->assertForbidden(),
        'invalid' => $response->assertRedirect()->assertSessionHasErrors('role'),
    };

    expect($changed($world))->toBe($outcome === 'ok');

    if ($outcome !== 'ok') {
        expect(TeamInvitation::query()->count())->toBe($invitations)
            ->and($world['targetTechnician']->fresh()->deactivated_at)->toBeNull()
            ->and($world['targetTechnician']->fresh()->teamRole($world['facility']->team))->toBe(TeamRole::Member);
    }
})->with('permission matrix');

test('nobody can invite an in-charge', function () {
    $world = permFacility();

    expect(TeamInvitation::query()->where('role', TeamRole::Owner->value)->exists())->toBeFalse();

    foreach (['owner', 'senior'] as $actor) {
        $this->actingAs($world[$actor])
            ->post(route('teams.invitations.store', $world['facility']->team), ['email' => "boss.{$actor}@example.com", 'role' => TeamRole::Owner->value])
            ->assertSessionHasErrors('role');
    }

    expect(TeamInvitation::query()->where('role', TeamRole::Owner->value)->exists())->toBeFalse();
});

test('a senior technician cannot resend an invitation for a senior technician', function () {
    Notification::fake();
    $world = permFacility();

    $this->actingAs($world['senior'])
        ->post(route('facilities.invitations.resend', [$world['facility']->team, $world['seniorInvite']]))
        ->assertForbidden();

    $this->actingAs($world['senior'])
        ->post(route('facilities.invitations.resend', [$world['facility']->team, $world['technicianInvite']]))
        ->assertRedirect();

    Notification::assertSentOnDemandTimes(TeamInvitationNotification::class, 1);
});

test('the staff and invites pages hide controls a senior technician may not use', function () {
    $world = permFacility();
    $team = $world['facility']->team;
    $actionKinds = fn (array $member): array => collect($member['actions'])->pluck('label')->all();

    $this->actingAs($world['senior'])
        ->get(route('facilities.members', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('area.canInvite', true)
            ->where('area.inviteRoles', fn ($roles) => collect($roles)->pluck('value')->all() === [TeamRole::Member->value, TeamRole::Screener->value])
            ->where('members', fn ($members) => collect($members)->every(
                fn (array $member): bool => ! in_array(__('entry.change_role'), $actionKinds($member), true)
                    && ! in_array(__('entry.deactivate'), $actionKinds($member), true),
            )));

    $this->actingAs($world['senior'])
        ->get(route('facilities.invites', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('invitations', fn ($rows) => collect($rows)->firstWhere('email', 'pending.senior@example.com')['actions'] === []
                && count(collect($rows)->firstWhere('email', 'pending.technician@example.com')['actions']) === 2));

    $this->actingAs($world['owner'])
        ->get(route('facilities.members', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('area.inviteRoles', fn ($roles) => collect($roles)->pluck('value')->all() === [TeamRole::Admin->value, TeamRole::Member->value, TeamRole::Screener->value])
            ->where('members', fn ($members) => in_array(__('entry.deactivate'), $actionKinds(collect($members)->firstWhere('id', $world['targetTechnician']->id)), true)
                && in_array(__('entry.change_role'), $actionKinds(collect($members)->firstWhere('id', $world['targetTechnician']->id)), true)));
});
