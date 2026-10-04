<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Models\City;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the full-screen command center boots for every viewer role', function () {
    $city = City::factory()->create();

    $users = [
        User::factory()->create(),
        User::factory()->create(),
        User::factory()->create(),
        User::factory()->create(),
    ];

    ProgrammeAssignment::factory()->create(['user_id' => $users[0]->id, 'role' => ProgrammeRole::TbaiAdmin]);
    ProgrammeAssignment::factory()->create(['user_id' => $users[1]->id, 'role' => ProgrammeRole::CountryViewer, 'scope_type' => ScopeType::Country, 'scope_id' => $city->province->country_id]);
    ProgrammeAssignment::factory()->create(['user_id' => $users[2]->id, 'role' => ProgrammeRole::ProvincialViewer, 'scope_type' => ScopeType::Province, 'scope_id' => $city->province_id]);
    ProgrammeAssignment::factory()->create(['user_id' => $users[3]->id, 'role' => ProgrammeRole::CityOfficer, 'scope_type' => ScopeType::City, 'scope_id' => $city->id]);

    foreach ($users as $user) {
        $this->actingAs($user)
            ->get(route('command.center'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('command/center')
                ->where('backUrl', route('command.index')));
    }
});

test('facility staff and guests cannot open the full-screen command center', function () {
    $facility = Facility::factory()->create();
    $staff = User::factory()->create();
    $facility->team->members()->attach($staff->id, ['role' => 'member']);

    $this->actingAs($staff)->get(route('command.center'))->assertForbidden();

    auth()->logout();
    $this->get(route('command.center'))->assertRedirect(route('login'));
});

test('the command center front end keeps to the copy and security rules', function () {
    $files = array_merge(
        glob(resource_path('js/command-center/*.{ts,tsx}'), GLOB_BRACE) ?: [],
        glob(resource_path('js/command-center/*/*.{ts,tsx}'), GLOB_BRACE) ?: [],
        [resource_path('js/pages/command/center.tsx'), resource_path('css/command-center.css')],
    );

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);

        expect($source)
            ->not->toContain("\u{2014}", "{$file} has an em dash")
            ->not->toContain("\u{2013}", "{$file} has an en dash")
            ->not->toContain('dangerouslySetInnerHTML')
            ->not->toContain('onclick')
            ->not->toContain('Assissted')
            ->not->toContain('eval(');

        expect(preg_match('/demo data/i', $source))->toBe(0, "{$file} labels data as demo");
        expect(preg_match('/\bteam\b/i', $source))->toBe(0, "{$file} says team");
    }
});
