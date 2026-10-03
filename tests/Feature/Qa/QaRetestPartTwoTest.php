<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\AccessRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function p2Facility(): Facility
{
    return Facility::factory()->create();
}

function p2Person(string $kind, ?Facility $facility = null): User
{
    $user = User::factory()->create();

    switch ($kind) {
        case 'technician':
            joinFacility($user, $facility ?? p2Facility(), TeamRole::Member);
            break;
        case 'in_charge':
            joinFacility($user, $facility ?? p2Facility(), TeamRole::Owner);
            break;
        case 'admin':
            giveProgrammeRole($user, ProgrammeRole::TbaiAdmin);
            break;
        case 'viewer':
            $country = Country::factory()->create();
            ProgrammeAssignment::factory()->create([
                'user_id' => $user->id,
                'role' => ProgrammeRole::CountryViewer,
                'scope_type' => ScopeType::Country,
                'scope_id' => $country->id,
            ]);
            break;
    }

    return $user;
}

/**
 * @return array{ids: list<string>, labels: list<string>, current: list<string>, more: list<string>}
 */
function p2Tabs(Assert $page): array
{
    $nav = $page->toArray()['props']['nav'];
    $tabs = collect($nav['tabs']);

    return [
        'ids' => $tabs->pluck('id')->all(),
        'labels' => $tabs->pluck('label')->all(),
        'current' => $tabs->where('current', true)->pluck('id')->all(),
        'more' => array_column($nav['more'], 'id'),
    ];
}

test('phone tabs for a technician are Register, Facility, Account', function () {
    $facility = p2Facility();
    $technician = p2Person('technician', $facility);

    $this->actingAs($technician)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $tabs = p2Tabs($page);

            expect($tabs['ids'])->toBe(['register', 'facility', 'account'])
                ->and($tabs['labels'])->toBe(['Register', 'Facility', 'Account'])
                ->and($tabs['current'])->toBe(['register']);
        });
});

test('phone tabs for an in-charge are Register, Returns, Facility, Account', function () {
    $facility = p2Facility();
    $owner = p2Person('in_charge', $facility);

    $this->actingAs($owner)
        ->get(route('returns.history', $facility->team))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $tabs = p2Tabs($page);

            expect($tabs['ids'])->toBe(['register', 'returns', 'facility', 'account'])
                ->and($tabs['labels'])->toBe(['Register', 'Returns', 'Facility', 'Account'])
                ->and($tabs['current'])->toBe(['returns']);
        });
});

test('exactly one phone tab is current for facility roles on every page, and Staff maps to Facility', function () {
    $facility = p2Facility();
    $owner = p2Person('in_charge', $facility);
    $technician = p2Person('technician', $facility);

    $expected = [
        route('returns.today', $facility->team) => 'register',
        route('returns.history', $facility->team) => 'returns',
        route('facilities.home', $facility->team) => 'facility',
        route('facilities.edit', $facility->team) => 'facility',
        route('facilities.members', $facility->team) => 'facility',
        route('facilities.invites', $facility->team) => 'facility',
        route('facilities.activity', $facility->team) => 'facility',
        route('profile.edit') => 'account',
        route('appearance.edit') => 'account',
    ];

    foreach ($expected as $url => $tab) {
        $this->actingAs($owner)->get($url)->assertOk()->assertInertia(function (Assert $page) use ($tab, $url) {
            expect(p2Tabs($page)['current'])->toBe([$tab], $url);
        });
    }

    foreach ([route('returns.today', $facility->team) => 'register', route('facilities.home', $facility->team) => 'facility', route('profile.edit') => 'account'] as $url => $tab) {
        $this->actingAs($technician)->get($url)->assertOk()->assertInertia(function (Assert $page) use ($tab, $url) {
            expect(p2Tabs($page)['current'])->toBe([$tab], $url);
        });
    }
});

test('phone tabs for a viewer are the top four plus More', function () {
    $viewer = p2Person('viewer');

    $this->actingAs($viewer)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $tabs = p2Tabs($page);

            expect($tabs['ids'])->toBe(['overview', 'registered', 'map', 'alerts', 'more'])
                ->and($tabs['labels'])->toBe(['Overview', 'Registered', 'Map', 'Alerts', 'More'])
                ->and($tabs['more'])->toBe(['daily_returns', 'account'])
                ->and($tabs['current'])->toBe(['overview']);
        });

    $expected = [
        route('command.map') => 'map',
        route('command.alerts') => 'alerts',
        route('command.sectors.show', ['sector' => 'public']) => 'registered',
        route('command.returns') => 'more',
        route('profile.edit') => 'more',
    ];

    foreach ($expected as $url => $tab) {
        $this->actingAs($viewer)->get($url)->assertOk()->assertInertia(function (Assert $page) use ($tab, $url) {
            expect(p2Tabs($page)['current'])->toBe([$tab], $url);
        });
    }
});

test('phone tabs for an admin are Overview, Facilities, Returns, People, More', function () {
    $admin = p2Person('admin');

    $expected = [
        route('command.index') => 'overview',
        route('facilities.index') => 'facilities',
        route('admin.daily-returns.index') => 'returns',
        route('admin.users.index') => 'people',
        route('admin.access-requests.index') => 'people',
        route('admin.audit-log.index') => 'more',
    ];

    foreach ($expected as $url => $tab) {
        $this->actingAs($admin)->get($url)->assertOk()->assertInertia(function (Assert $page) use ($tab, $url) {
            $tabs = p2Tabs($page);

            expect($tabs['ids'])->toBe(['overview', 'facilities', 'returns', 'people', 'more'])
                ->and($tabs['more'])->toBe(['audit_log', 'account'])
                ->and($tabs['current'])->toBe([$tab], $url);
        });
    }
});

test('the old facility addresses redirect to the merged Facility pages', function () {
    $facility = p2Facility();
    $owner = p2Person('in_charge', $facility);
    $slug = $facility->team->slug;

    $targets = [
        'my-facility' => route('facilities.home', $facility->team),
        'profile' => route('facilities.edit', $facility->team),
        'members' => route('facilities.members', $facility->team),
        'invites' => route('facilities.invites', $facility->team),
        'roles' => route('facilities.members', $facility->team),
        'activity' => route('facilities.activity', $facility->team),
    ];

    foreach ($targets as $legacy => $target) {
        $this->actingAs($owner)
            ->get("/{$slug}/{$legacy}")
            ->assertStatus(301)
            ->assertRedirect($target);
    }
});

test('the Facility area has one set of tabs and the technician sees Overview only', function () {
    $facility = p2Facility();
    $owner = p2Person('in_charge', $facility);
    $technician = p2Person('technician', $facility);

    $this->actingAs($owner)
        ->get(route('facilities.home', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('facilities/home')
            ->where('area.title', $facility->team->name)
            ->where('area.tabs', fn ($tabs) => collect($tabs)->pluck('label')->all() === ['Overview', 'Profile', 'Staff', 'Invites', 'Activity']
                && collect($tabs)->where('current', true)->pluck('id')->all() === ['overview'])
            ->has('tiles')
            ->where('action.label', "Open today's register"));

    $this->actingAs($technician)
        ->get(route('facilities.home', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('area.tabs', fn ($tabs) => collect($tabs)->pluck('id')->all() === ['overview']));
});

test('the Staff tab carries Roles and access instead of a separate roles page', function () {
    $facility = p2Facility();
    $owner = p2Person('in_charge', $facility);

    $this->actingAs($owner)
        ->get(route('facilities.members', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rolesInfo.heading', 'Roles and access')
            ->has('rolesInfo.roles'));

    expect(Route::has('facilities.roles'))->toBeFalse();
});

test('the audit log is admin only, read only, and shows an empty state when it has nothing', function () {
    $admin = p2Person('admin');
    $owner = p2Person('in_charge');
    $viewer = p2Person('viewer');

    $this->actingAs($owner)->get(route('admin.audit-log.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.audit-log.index'))->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.audit-log.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resources/index')
            ->where('title', 'Audit log')
            ->has('description')
            ->where('createUrl', null));
});

test('people and access is one page with four tabs and an empty state on access requests', function () {
    $admin = p2Person('admin');

    $this->actingAs($admin)
        ->get(route('admin.access-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows', [])
            ->where('empty.title', fn ($title) => is_string($title) && $title !== '')
            ->where('tabs', fn ($tabs) => collect($tabs)->pluck('id')->all() === ['users', 'access_requests', 'programme_assignments', 'programme_access']
                && collect($tabs)->where('current', true)->pluck('id')->all() === ['access_requests']));

    AccessRequest::query()->create([
        'name' => 'Amina', 'role' => 'Technician', 'organisation' => 'Clinic',
        'district' => 'Thatta', 'email' => 'amina@example.com',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.access-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1));

    foreach ([
        'admin.users.index' => 'users',
        'admin.programme-assignments.index' => 'programme_assignments',
        'admin.programme-access.index' => 'programme_access',
    ] as $name => $current) {
        $this->actingAs($admin)->get(route($name))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tabs', fn ($tabs) => collect($tabs)->where('current', true)->pluck('id')->all() === [$current]));
    }
});

test('returns is one admin item with Daily returns and Edit history tabs', function () {
    $admin = p2Person('admin');

    $this->actingAs($admin)
        ->get(route('admin.revisions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tabs', fn ($tabs) => collect($tabs)->pluck('label')->all() === ['Daily returns', 'Edit history']
                && collect($tabs)->where('current', true)->pluck('id')->all() === ['revisions']));
});

test('districts with no figures are left off the map unless picked', function () {
    $admin = p2Person('admin');
    $busy = City::factory()->create(['name' => 'Karachi West']);
    $quiet = City::factory()->create(['name' => 'Tharparkar']);
    $busyFacility = Facility::factory()->create(['city_id' => $busy->id, 'sector' => Sector::Public]);
    Facility::factory()->create(['city_id' => $quiet->id, 'sector' => Sector::Public]);
    DailyReturn::factory()->create([
        'facility_id' => $busyFacility->id,
        'reported_on' => now()->subDays(3)->toDateString(),
        'suspects' => 10, 'tested' => 5, 'positive' => 1, 'negative' => 4, 'started_treatment' => 1, 'follow_up' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('command.map', ['from' => now()->subDays(30)->toDateString(), 'to' => now()->toDateString()]))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];
            $names = array_column($props['districts'], 'name');

            expect($names)->toContain('Karachi West')
                ->and($names)->not->toContain('Tharparkar')
                ->and($props['hiddenMatches'])->not->toBeEmpty()
                ->and($props['hiddenNote'])->toBe('Districts with no returns in this period are hidden.');
        });

    $this->actingAs($admin)
        ->get(route('command.map', ['district' => $quiet->id, 'from' => now()->subDays(30)->toDateString(), 'to' => now()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('districts', fn ($districts) => in_array('Tharparkar', array_column(json_decode(json_encode($districts), true), 'name'), true)));
});

test('the sector filter changes the command center figures and the scope line', function () {
    $admin = p2Person('admin');
    $city = City::factory()->create();
    $public = Facility::factory()->create(['city_id' => $city->id, 'sector' => Sector::Public]);
    $private = Facility::factory()->create(['city_id' => $city->id, 'sector' => Sector::Private]);
    $date = now()->subDays(2)->toDateString();

    foreach ([[$public, 30], [$private, 7]] as [$facility, $suspects]) {
        DailyReturn::factory()->create([
            'facility_id' => $facility->id, 'reported_on' => $date,
            'suspects' => $suspects, 'tested' => 1, 'positive' => 0, 'negative' => 1, 'started_treatment' => 0, 'follow_up' => 0,
        ]);
    }

    $range = ['from' => now()->subDays(30)->toDateString(), 'to' => now()->toDateString()];

    $this->actingAs($admin)->get(route('command.index', $range))->assertInertia(fn (Assert $page) => $page
        ->where('totals.suspects', 37)
        ->where('filters.sector', ''));

    $this->actingAs($admin)->get(route('command.index', [...$range, 'sector' => 'public']))->assertInertia(fn (Assert $page) => $page
        ->where('totals.suspects', 30)
        ->where('filters.sector', 'public')
        ->where('scope', fn ($scope) => str_ends_with($scope, ', Public')));

    $this->actingAs($admin)->get(route('command.index', [...$range, 'sector' => 'private']))->assertInertia(fn (Assert $page) => $page
        ->where('totals.suspects', 7));
});

test('the user menu names the account page Account', function () {
    $source = file_get_contents(resource_path('js/components/user-menu-content.tsx'));

    expect($source)->toContain('Account')->and($source)->not->toMatch('/^\s*Settings\s*$/m');
});

test('the admin gets a tab strip on command pages and nobody else does', function () {
    $admin = p2Person('admin');
    $viewer = p2Person('viewer');

    $this->actingAs($admin)
        ->get(route('command.map'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $tabs = collect($page->toArray()['props']['nav']['commandTabs']);

            expect($tabs->pluck('label')->all())->toBe(['Overview', 'Registered', 'Map', 'Alerts'])
                ->and($tabs->where('current', true)->pluck('id')->all())->toBe(['map']);
        });

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->where('nav.commandTabs', []));

    $this->actingAs($viewer)
        ->get(route('command.map'))
        ->assertInertia(fn (Assert $page) => $page->where('nav.commandTabs', []));
});
