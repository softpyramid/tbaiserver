<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\User;

test('the manifest is an installable home screen shortcut to today', function () {
    $this->get(route('manifest'))
        ->assertOk()
        ->assertHeader('content-type', 'application/manifest+json')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('start_url', '/today')
        ->assertJsonPath('theme_color', '#14294c')
        ->assertJsonPath('icons.0.sizes', '192x192')
        ->assertJsonPath('icons.1.sizes', '512x512');

    expect(file_exists(public_path('sw.js')))->toBeTrue()
        ->and(file_exists(public_path('icons/icon-192.png')))->toBeTrue()
        ->and(file_exists(public_path('icons/icon-512.png')))->toBeTrue()
        ->and(file_get_contents(public_path('sw.js')))->toContain("addEventListener('fetch'");
});

test('the home screen shortcut opens today register for facility staff', function () {
    $member = User::factory()->create();
    $facility = Facility::factory()->create();
    $facility->team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($facility->team);

    $this->actingAs($member)
        ->get(route('returns.home'))
        ->assertRedirect(route('returns.today', $facility->team));
});

test('a command center role keeps the sidebar shell', function () {
    $officer = User::factory()->create();
    $city = City::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $city->id,
    ]);

    $this->actingAs($officer)
        ->get(route('dashboard', $officer->currentTeam))
        ->assertRedirect(route('command.index'));

    $this->actingAs($officer)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('nav.shell', 'sidebar')
            ->where('scope', $city->name),
        );
});
