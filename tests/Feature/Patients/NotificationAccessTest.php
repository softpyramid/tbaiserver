<?php

use App\Enums\ProgrammeRole;
use App\Enums\TeamRole;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableFeature('patients');
});

function notifyAccessReminder(User $user): string
{
    $id = (string) Str::uuid();

    DatabaseNotification::query()->create([
        'id' => $id,
        'type' => 'App\Notifications\MissedVisit',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => ['patient_uuid' => (string) Str::uuid(), 'month_number' => 1, 'scheduled_date' => '2026-09-01'],
    ]);

    return $id;
}

function notifyAccessViewer(ProgrammeRole $role): User
{
    $user = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $user->id,
        'role' => $role,
        'scope_type' => $role->scopeType(),
        'scope_id' => $role->scopeType() === null ? null : Province::factory()->create()->id,
    ]);

    return $user;
}

test('programme viewers cannot open or mark notifications and get no bell', function () {
    $viewer = notifyAccessViewer(ProgrammeRole::ProvincialViewer);
    // A reminder left over from an earlier facility role.
    $id = notifyAccessReminder($viewer);

    $this->actingAs($viewer)->get(route('notifications.index'))->assertForbidden();
    $this->actingAs($viewer)->post(route('notifications.read', ['notification' => $id]))->assertForbidden();
    $this->actingAs($viewer)->post(route('notifications.read-all'))->assertForbidden();

    expect($viewer->unreadNotifications()->count())->toBe(1);

    $this->actingAs($viewer)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications', null)
            ->where('nav.groups', fn ($groups) => ! collect($groups)->flatMap(fn ($group) => $group['items'])->contains('key', 'notifications')));
});

test('a signed-in account with no facility and no programme role is refused', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('notifications.index'))->assertForbidden();
    $this->actingAs($user)->post(route('notifications.read-all'))->assertForbidden();
});

test('facility staff of every role and tb-ai admins keep notifications', function () {
    $facility = Facility::factory()->create();

    foreach ([TeamRole::Owner, TeamRole::Admin, TeamRole::Member, TeamRole::Screener] as $role) {
        [$staff] = suspectStaff($role, $facility);
        $id = notifyAccessReminder($staff);

        $this->actingAs($staff)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/index')
                ->where('unread', 1)
                ->where('notifications.unread', 1));

        $this->actingAs($staff)->post(route('notifications.read', ['notification' => $id]))->assertRedirect();
        expect($staff->unreadNotifications()->count())->toBe(0);
    }

    $admin = suspectAdmin();
    notifyAccessReminder($admin);

    $this->actingAs($admin)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 1));

    $this->actingAs($admin)->post(route('notifications.read-all'))->assertRedirect();
    expect($admin->unreadNotifications()->count())->toBe(0);
});
