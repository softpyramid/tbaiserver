<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * @return array<string, User>
 */
function accountCast(): array
{
    $facility = Facility::factory()->create();
    $cast = [];

    foreach (['admin', 'country', 'sindh', 'karachi-west', 'incharge', 'technician'] as $key) {
        $cast[$key] = User::factory()->create();
    }

    giveProgrammeRole($cast['admin'], ProgrammeRole::TbaiAdmin);

    foreach ([
        'country' => [ProgrammeRole::CountryViewer, ScopeType::Country, Country::factory()->create()->id],
        'sindh' => [ProgrammeRole::ProvincialViewer, ScopeType::Province, Province::factory()->create()->id],
        'karachi-west' => [ProgrammeRole::CityOfficer, ScopeType::City, City::factory()->create()->id],
    ] as $key => [$role, $scope, $scopeId]) {
        ProgrammeAssignment::factory()->create([
            'user_id' => $cast[$key]->id,
            'role' => $role,
            'scope_type' => $scope,
            'scope_id' => $scopeId,
        ]);
    }

    joinFacility($cast['incharge'], $facility, TeamRole::Owner);
    joinFacility($cast['technician'], $facility, TeamRole::Member);

    return $cast;
}

test('there is no self-delete route and DELETE settings/profile is unavailable for every role', function () {
    expect(Route::has('profile.destroy'))->toBeFalse();

    foreach (accountCast() as $key => $user) {
        $status = $this->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password', 'confirm_email' => $user->email])
            ->getStatusCode();

        expect($status)->toBeIn([404, 405], "role {$key}");
        expect($user->fresh())->not->toBeNull();
        $this->assertAuthenticatedAs($user);
    }
});

test('the account page opens for every role', function () {
    foreach (accountCast() as $user) {
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }
});

test('the settings pages carry no log out button or delete account section', function () {
    foreach (['pages/settings/profile.tsx', 'pages/settings/security.tsx', 'pages/settings/appearance.tsx', 'layouts/settings/layout.tsx'] as $file) {
        $source = file_get_contents(resource_path("js/{$file}"));

        expect($source)->not->toContain('logout')
            ->and($source)->not->toContain('Log out')
            ->and($source)->not->toContain('DeleteUser')
            ->and($source)->not->toContain('Danger zone');
    }

    expect(file_exists(resource_path('js/components/delete-user.tsx')))->toBeFalse();
});

test('phone widths get a top bar with the same user menu as the sidebar', function () {
    $layout = file_get_contents(resource_path('js/layouts/app/app-sidebar-layout.tsx'));
    $bar = file_get_contents(resource_path('js/components/phone-top-bar.tsx'));

    expect($layout)->toContain('<PhoneTopBar />')
        ->and($bar)->toContain('UserMenuContent')
        ->and($bar)->toContain('md:hidden');

    $menu = file_get_contents(resource_path('js/components/user-menu-content.tsx'));
    expect($menu)->toContain('Log out')->toContain('Account')->toContain('showEmail');
});
