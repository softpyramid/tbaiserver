<?php

use App\Enums\ProgrammeRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('empty daily returns, return history, and screening sessions keep column headers', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('admin.daily-returns.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 0)
            ->where('labels.empty', 'Nothing to show.')
            ->where('columns.0.label', 'Date')
            ->where('columns.1.label', 'Facility')
            ->where('columns.2.label', 'City'),
        );

    $this->actingAs($admin)
        ->get(route('admin.revisions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 0)
            ->where('labels.empty', 'Nothing to show.')
            ->where('columns.0.label', 'When')
            ->where('columns.1.label', 'Who')
            ->where('columns.2.label', 'Facility'),
        );

    $this->actingAs($admin)
        ->get(route('admin.screening-sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 0)
            ->where('labels.empty', 'Nothing to show.')
            ->where('columns.0.label', 'Facility')
            ->where('columns.1.label', 'Date')
            ->where('columns.2.label', 'Score')
            ->where('columns.3.label', 'Suspect'),
        );
});

test('users and roles names the person column Name and teams names the team column Team', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('title', 'Users and roles')
            ->where('columns.0.label', 'Name')
            ->where('columns.0.key', 'name'),
        );

    $this->actingAs($admin)
        ->get(route('admin.teams.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('title', 'Teams')
            ->where('columns.0.label', 'Team')
            ->where('columns.0.key', 'team'),
        );
});
