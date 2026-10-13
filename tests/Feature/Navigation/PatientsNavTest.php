<?php

use App\Enums\ProgrammeRole;
use App\Enums\TeamRole;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\LoginSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableFeature('patients', 'suspect_screening');
});

/**
 * @param  array<string, mixed>  $page
 * @return array<string, mixed>|null
 */
function patientsNavItem(array $page): ?array
{
    return collect($page['props']['nav']['groups'])
        ->flatMap(fn (array $group): array => $group['items'])
        ->firstWhere('key', 'patients');
}

/**
 * @param  array<string, mixed>  $page
 * @return array<string, mixed>|null
 */
function patientsPhoneTab(array $page): ?array
{
    return collect($page['props']['nav']['tabs'])->firstWhere('id', 'patients');
}

dataset('facility staff', [
    'in-charge' => [TeamRole::Owner, 'returns.today'],
    'senior technician' => [TeamRole::Admin, 'returns.today'],
    'technician' => [TeamRole::Member, 'returns.today'],
    'frontline worker' => [TeamRole::Screener, 'suspect.index'],
]);

test('the patients link opens the facility patient list and marks itself current', function (TeamRole $role, string $home) {
    $facility = Facility::factory()->create();
    [$user] = suspectStaff($role, $facility);
    $href = route('patients.index', $facility->team);

    $landing = $this->actingAs($user)->get(route($home, $facility->team))->assertOk()->viewData('page');

    expect(patientsNavItem($landing))->toMatchArray(['href' => $href, 'current' => false])
        ->and(patientsPhoneTab($landing))->toMatchArray(['href' => $href, 'icon' => 'patients', 'current' => false]);

    $this->actingAs($user)
        ->get($href)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/index')
            ->where('nav.activeTab', 'patients')
            ->where('nav.groups', fn ($groups) => collect($groups)->flatMap(fn ($group) => $group['items'])->firstWhere('key', 'patients')['current'] === true));
})->with('facility staff');

test('programme viewers get no patients link', function (ProgrammeRole $role) {
    $facility = Facility::factory()->create();
    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => $role,
        'scope_type' => $role->scopeType(),
        'scope_id' => Province::factory()->create()->id,
    ]);

    $page = $this->actingAs($viewer)->get(route('command.index'))->assertOk()->viewData('page');

    expect(patientsNavItem($page))->toBeNull()
        ->and(patientsPhoneTab($page))->toBeNull()
        ->and(collect($page['props']['nav']['more'])->pluck('id')->all())->not->toContain('admin_patients');

    $this->actingAs($viewer)->get(route('patients.index', $facility->team))->assertNotFound();
})->with([ProgrammeRole::ProvincialViewer, ProgrammeRole::CountryViewer]);

test('the tb-ai admin patients link opens the admin list', function () {
    $admin = suspectAdmin();

    $page = $this->actingAs($admin)->get(route('facilities.index'))->assertOk()->viewData('page');

    expect(patientsNavItem($page)['href'])->toBe(route('admin.patients.index'));

    $this->actingAs($admin)->get(route('admin.patients.index'))->assertOk();
});

test('the demo staff accounts at karachi west bmu reach patients from the nav', function () {
    $this->app['env'] = 'local';
    config(['access.seed_login_password' => 'password']);
    $this->seed(LoginSeeder::class);
    $this->app['env'] = 'testing';

    $team = Team::query()->where('slug', 'karachi-west-bmu')->firstOrFail();
    $href = route('patients.index', $team);

    foreach (['incharge@tbai.test', 'senior@tbai.test', 'member@tbai.test', 'screener@tbai.test'] as $email) {
        $user = User::query()->where('email', $email)->firstOrFail();
        $page = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->viewData('page');

        expect(patientsNavItem($page)['href'] ?? null)->toBe($href, $email)
            ->and(patientsPhoneTab($page)['href'] ?? null)->toBe($href, $email);

        $this->actingAs($user)->get($href)->assertOk();
    }

    foreach (['sindh@tbai.test', 'country@tbai.test', 'karachi-west@tbai.test'] as $email) {
        $page = $this->actingAs(User::query()->where('email', $email)->firstOrFail())->get(route('profile.edit'))->assertOk()->viewData('page');

        expect(patientsNavItem($page))->toBeNull()
            ->and(patientsPhoneTab($page))->toBeNull();
    }
});
