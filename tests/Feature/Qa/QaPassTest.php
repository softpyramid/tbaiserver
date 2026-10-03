<?php

use App\Enums\AccessRequestStatus;
use App\Enums\FacilityStatus;
use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\AccessRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('each role lands on its home and the empty dashboard is gone', function () {
    $technician = User::factory()->create();
    $facility = Facility::factory()->create();
    qaJoinFacility($technician, $facility, TeamRole::Member);

    $this->actingAs($technician)
        ->get(route('dashboard'))
        ->assertRedirect(route('returns.today', $facility->team));

    $country = Country::factory()->create(['name' => 'Pakistan']);
    $province = Province::factory()->create(['name' => 'Sindh', 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Karachi West', 'province_id' => $province->id]);

    $countryViewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $countryViewer->id,
        'role' => ProgrammeRole::CountryViewer,
        'scope_type' => ScopeType::Country,
        'scope_id' => $country->id,
    ]);

    $this->actingAs($countryViewer)
        ->get(route('dashboard'))
        ->assertRedirect(route('command.index'));

    $this->actingAs($countryViewer)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('scope', 'Pakistan')
            ->where('title', fn (string $title) => $title !== ''));

    $provinceViewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $provinceViewer->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $province->id,
    ]);

    $this->actingAs($provinceViewer)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('scope', 'Sindh'));

    $cityViewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $cityViewer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $city->id,
    ]);

    $this->actingAs($cityViewer)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('scope', 'Karachi West'));

    $admin = User::factory()->create();
    qaGiveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('command.index'));
});

test('viewers can open daily returns for their area', function () {
    $province = Province::factory()->create(['name' => 'Sindh']);
    $otherProvince = Province::factory()->create(['name' => 'Punjab']);
    $city = City::factory()->create(['name' => 'Karachi West', 'province_id' => $province->id]);
    $otherCity = City::factory()->create(['name' => 'Lahore', 'province_id' => $otherProvince->id]);
    $own = Facility::factory()->create(['city_id' => $city->id]);
    $other = Facility::factory()->create(['city_id' => $otherCity->id]);
    $own->team->update(['name' => 'West BMU']);
    $other->team->update(['name' => 'Lahore BMU']);

    DailyReturn::factory()->create(['facility_id' => $own->id]);
    DailyReturn::factory()->create(['facility_id' => $other->id]);

    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $province->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.groups.0.items', fn ($items) => collect($items)->contains(
                fn (array $item): bool => $item['key'] === 'daily_returns'
                    && $item['label'] === 'Daily returns'
                    && $item['href'] === route('command.returns'),
            ) && collect($items)->contains(
                fn (array $item): bool => $item['key'] === 'account'
                    && $item['label'] === 'Account'
                    && $item['href'] === route('profile.edit'),
            )));

    $this->actingAs($viewer)
        ->get(route('command.returns'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('title', 'Daily returns')
            ->where('rows', fn ($rows) => collect($rows)->contains(
                fn (array $row): bool => ($row['cells']['facility'] ?? '') === 'West BMU',
            ) && collect($rows)->doesntContain(
                fn (array $row): bool => ($row['cells']['facility'] ?? '') === 'Lahore BMU',
            )));
});

test('return history links the whole row and formats the date', function () {
    $owner = User::factory()->create();
    $facility = Facility::factory()->create();
    qaJoinFacility($owner, $facility, TeamRole::Owner);

    $return = DailyReturn::factory()->create([
        'facility_id' => $facility->id,
        'submitted_by' => $owner->id,
        'reported_on' => '2026-10-04',
    ]);

    $this->actingAs($owner)
        ->get(route('returns.history', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('title', 'Return history')
            ->where('rows.0.cells.date', '4 Oct 2026')
            ->where('rows.0.href', route('returns.today', [
                'current_team' => $facility->team,
                'reported_on' => '2026-10-04',
            ]))
            ->where('rows.0.controls', [])
            ->where('rows.0.id', (string) $return->id));
});

test('users and access requests use the person labels and hide self deactivate', function () {
    $admin = User::factory()->create(['name' => 'Programme Admin']);
    qaGiveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $facility = Facility::factory()->create();
    $facility->team->update(['name' => 'West BMU']);
    $technician = User::factory()->create(['name' => 'Ayesha Khan']);
    qaJoinFacility($technician, $facility, TeamRole::Member);

    AccessRequest::query()->create([
        'name' => 'Ayesha Khan',
        'role' => 'Lab technician',
        'organisation' => 'West BMU',
        'district' => 'Karachi West',
        'email' => 'ayesha@example.test',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($admin, $technician) {
            $rows = collect($page->toArray()['props']['rows']);
            $self = $rows->firstWhere('id', (string) $admin->id);
            $person = $rows->firstWhere('id', (string) $technician->id);

            expect($person['cells']['role'])->toBe('Technician')
                ->and($person['cells']['facility'])->toBe('West BMU')
                ->and($person['cells']['role'])->not->toBe('Not available')
                ->and(collect($self['controls'])->contains(
                    fn (array $control): bool => ($control['label'] ?? '') === 'Deactivate',
                ))->toBeFalse()
                ->and(collect($person['controls'])->contains(
                    fn (array $control): bool => ($control['label'] ?? '') === 'Deactivate'
                        && ($control['person'] ?? '') === 'Ayesha Khan'
                        && ($control['confirm'] ?? false) === true,
                ))->toBeTrue();
        });

    $this->actingAs($admin)
        ->get(route('admin.access-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('columns.0.label', 'Requester')
            ->where('rows.0.cells.name', 'Ayesha Khan')
            ->where('rows.0.controls.0.person', 'Ayesha Khan')
            ->where('rows.0.controls.0.confirm', true));
});

test('only a tb-ai admin can deactivate a facility', function () {
    $facility = Facility::factory()->create();
    $owner = User::factory()->create();
    $senior = User::factory()->create();
    qaJoinFacility($owner, $facility, TeamRole::Owner);
    $facility->team->members()->attach($senior, ['role' => TeamRole::Admin->value]);

    $admin = User::factory()->create();
    qaGiveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($owner)
        ->get(route('facilities.edit', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canDeactivate', false));

    $this->actingAs($senior)
        ->get(route('facilities.edit', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canDeactivate', false));

    $this->actingAs($owner)
        ->delete(route('teams.destroy', $facility->team))
        ->assertForbidden();

    $this->actingAs($senior)
        ->delete(route('teams.destroy', $facility->team))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('facilities.deactivate', $facility))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('facilities.edit', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canDeactivate', true));

    $this->actingAs($admin)
        ->post(route('facilities.deactivate', $facility))
        ->assertRedirect();

    expect($facility->refresh()->status)->toBe(FacilityStatus::Inactive);
});

test('missing forbidden and expired pages stay inside the app', function () {
    $member = User::factory()->create();
    $facility = Facility::factory()->create();
    qaJoinFacility($member, $facility, TeamRole::Member);

    $this->actingAs($member)
        ->get(route('admin.users.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/http')
            ->where('title', 'Not allowed')
            ->where('homeLabel', 'Back to home')
            ->where('homeUrl', route('returns.today', $facility->team)));

    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/http')
            ->where('status', 404)
            ->where('reason', 'This page does not exist.')
            ->where('homeLabel', 'Back to home'));

    Route::get('/qa-session-expired', fn () => abort(419));

    $this->actingAs($member)
        ->get('/qa-session-expired')
        ->assertStatus(419)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/http')
            ->where('status', 419)
            ->where('homeLabel', 'Back to home'));
});

test('authenticated pages and logout tell the browser not to store them', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertHeader('cache-control', 'no-store, private')
        ->assertHeader('pragma', 'no-cache');

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect()
        ->assertHeader('cache-control', 'no-store, private');

    $guest = $this->get(route('home'));

    expect((string) $guest->headers->get('Cache-Control'))->not->toContain('no-store');
});

test('the staging cleanup command refuses every environment except staging', function () {
    $this->artisan('tbai:cleanup-qa-pass', ['--force' => true])
        ->assertFailed();
});

test('the staging cleanup deletes only the matching return and pending request', function () {
    $this->app['env'] = 'staging';

    $facility = Facility::factory()->create();
    $technician = User::factory()->create();
    qaJoinFacility($technician, $facility, TeamRole::Member);

    $match = DailyReturn::factory()->create([
        'facility_id' => $facility->id,
        'submitted_by' => $technician->id,
        'reported_on' => '2026-10-04',
    ]);
    DB::table('daily_returns')->where('id', $match->id)->update(['id' => 790]);

    $decoy = DailyReturn::factory()->create([
        'facility_id' => $facility->id,
        'submitted_by' => $technician->id,
        'reported_on' => '2026-10-03',
    ]);

    $pending = AccessRequest::query()->create([
        'name' => 'QA Tester',
        'role' => 'Technician',
        'organisation' => 'Test site',
        'district' => 'Karachi',
        'email' => 'qa-test-request@example.invalid',
    ]);

    $approved = AccessRequest::query()->create([
        'name' => 'Already reviewed',
        'role' => 'Technician',
        'organisation' => 'Test site',
        'district' => 'Karachi',
        'email' => 'qa-test-request@example.invalid',
    ]);
    $approved->forceFill(['status' => AccessRequestStatus::Approved->value])->save();

    $other = AccessRequest::query()->create([
        'name' => 'Someone else',
        'role' => 'Technician',
        'organisation' => 'Test site',
        'district' => 'Karachi',
        'email' => 'other@example.invalid',
    ]);

    $this->artisan('tbai:cleanup-qa-pass')->assertSuccessful();

    expect(DailyReturn::query()->whereKey(790)->exists())->toBeTrue()
        ->and(AccessRequest::query()->whereKey($pending->id)->exists())->toBeTrue();

    $this->artisan('tbai:cleanup-qa-pass', ['--force' => true])->assertSuccessful();

    expect(DailyReturn::query()->whereKey(790)->exists())->toBeFalse()
        ->and(DailyReturn::query()->whereKey($decoy->id)->exists())->toBeTrue()
        ->and(AccessRequest::query()->whereKey($pending->id)->exists())->toBeFalse()
        ->and(AccessRequest::query()->whereKey($approved->id)->exists())->toBeTrue()
        ->and(AccessRequest::query()->whereKey($other->id)->exists())->toBeTrue();
});

test('the about page is part of this site', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('about'));
});

function qaJoinFacility(User $user, Facility $facility, TeamRole $role): void
{
    $facility->team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($facility->team);
}

function qaGiveProgrammeRole(User $user, ProgrammeRole $role): void
{
    ProgrammeAssignment::factory()->create([
        'user_id' => $user->id,
        'role' => $role,
        'scope_type' => null,
        'scope_id' => null,
    ]);
}
