<?php

use App\Enums\AccessRequestStatus;
use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\AccessRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\DailyReturn;
use App\Models\DailyReturnRevision;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use App\Support\EntryDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Six people, one of each role the QA retest used.
 *
 * @return array{facility: Facility, admin: User, member: User, owner: User, city: User, province: User, country: User}
 */
function partOneCast(): array
{
    $facility = Facility::factory()->create();
    $facility->team->update(['name' => 'Karachi West BMU']);

    $cast = ['facility' => $facility];

    $cast['admin'] = User::factory()->create();
    partOneGive($cast['admin'], ProgrammeRole::TbaiAdmin);

    $cast['member'] = User::factory()->create();
    partOneJoin($cast['member'], $facility, TeamRole::Member);

    $cast['owner'] = User::factory()->create();
    partOneJoin($cast['owner'], $facility, TeamRole::Owner);

    $cast['city'] = User::factory()->create();
    partOneGive($cast['city'], ProgrammeRole::CityOfficer);

    $cast['province'] = User::factory()->create();
    partOneGive($cast['province'], ProgrammeRole::ProvincialViewer);

    $cast['country'] = User::factory()->create();
    partOneGive($cast['country'], ProgrammeRole::CountryViewer);

    return $cast;
}

/**
 * @return array<string, string>
 */
function partOneLanding(array $cast): array
{
    $register = route('returns.today', $cast['facility']->team);
    $command = route('command.index');

    return [
        'admin' => $command,
        'member' => $register,
        'owner' => $register,
        'city' => $command,
        'province' => $command,
        'country' => $command,
    ];
}

test('each role lands on its own home after login', function () {
    $cast = partOneCast();

    foreach (partOneLanding($cast) as $key => $landing) {
        Cache::flush();

        $this->post(route('login.store'), [
            'email' => $cast[$key]->email,
            'password' => 'password',
        ])->assertRedirect($landing);

        $this->post(route('logout'));
    }
});

test('a stale intended url the person may not open is ignored at login', function () {
    $cast = partOneCast();
    $landing = partOneLanding($cast);
    $slug = $cast['facility']->team->slug;

    $stale = [
        'member' => [route('admin.users.index'), route('command.index'), route('returns.history', $slug), route('facilities.members', $slug), '/does-not-exist-anywhere'],
        'owner' => [route('admin.users.index'), route('command.index')],
        'city' => [route('admin.users.index'), route('returns.today', $slug)],
        'province' => [route('admin.users.index'), route('returns.today', $slug)],
        'country' => [route('admin.access-requests.index'), route('facilities.edit', $slug)],
    ];

    foreach ($stale as $key => $urls) {
        foreach ($urls as $url) {
            Cache::flush();

            $this->withSession(['url.intended' => $url])
                ->post(route('login.store'), ['email' => $cast[$key]->email, 'password' => 'password'])
                ->assertRedirect($landing[$key]);

            $this->post(route('logout'));
        }
    }

    // A foreign host is never followed.
    $this->withSession(['url.intended' => 'https://example.org/settings/profile'])
        ->post(route('login.store'), ['email' => $cast['member']->email, 'password' => 'password'])
        ->assertRedirect($landing['member']);
});

test('an intended url the person may open is still followed', function () {
    $cast = partOneCast();
    $slug = $cast['facility']->team->slug;

    $this->withSession(['url.intended' => route('admin.users.index')])
        ->post(route('login.store'), ['email' => $cast['admin']->email, 'password' => 'password'])
        ->assertRedirect(route('admin.users.index'));
    $this->post(route('logout'));

    $this->withSession(['url.intended' => route('returns.history', $slug)])
        ->post(route('login.store'), ['email' => $cast['owner']->email, 'password' => 'password'])
        ->assertRedirect(route('returns.history', $slug));
    $this->post(route('logout'));

    $this->withSession(['url.intended' => route('profile.edit')])
        ->post(route('login.store'), ['email' => $cast['member']->email, 'password' => 'password'])
        ->assertRedirect(route('profile.edit'));
});

test('signed-in pages and redirects are never stored and keep history encrypted', function () {
    $cast = partOneCast();

    foreach (['admin', 'member', 'owner', 'city', 'province', 'country'] as $key) {
        $response = $this->actingAs($cast[$key])->get(partOneLanding($cast)[$key])
            ->assertOk();

        expect((string) $response->headers->get('Cache-Control'))->toBe('no-store, private', $key)
            ->and((string) $response->headers->get('Pragma'))->toBe('no-cache', $key);

        expect($response->original->getData()['page']['encryptHistory'])->toBeTrue();
    }

    $this->actingAs($cast['member'])
        ->get(route('profile.edit'), ['X-Inertia' => 'true'])
        ->assertHeader('cache-control', 'no-store, private');

    $login = $this->post(route('login.store'), ['email' => $cast['member']->email, 'password' => 'password']);
    expect((string) $login->headers->get('Cache-Control'))->toContain('no-store');
});

test('guest pages are not marked private and do not encrypt history', function () {
    $guest = $this->get(route('login'));

    expect($guest->original->getData()['page'])->not->toHaveKey('encryptHistory')
        ->and((string) $guest->headers->get('Cache-Control'))->not->toContain('no-store');
});

test('logout replaces the session, clears encrypted history, and goes to the login page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'))
        ->assertSessionHas(SessionKey::CLEAR_HISTORY, true);

    $this->assertGuest();

    $this->get(route('profile.edit'))->assertRedirect(route('login'));
});

test('missing pages are styled inside the app shell for every signed-in role', function () {
    $cast = partOneCast();

    foreach (['admin', 'member', 'owner', 'city', 'province', 'country'] as $key) {
        foreach (['/nonexistent-page-qa', '/team'] as $path) {
            $this->actingAs($cast[$key])
                ->get($path)
                ->assertNotFound()
                ->assertHeader('cache-control', 'no-store, private')
                ->assertInertia(fn (Assert $page) => $page
                    ->component('errors/http')
                    ->where('status', 404)
                    ->where('title', 'Page not found')
                    ->where('reason', 'This page does not exist.')
                    ->where('homeLabel', 'Back to home')
                    ->where('homeUrl', partOneLanding($cast)[$key])
                    ->where('auth.user.id', $cast[$key]->id)
                    ->has('nav.groups')
                    ->where('nav.activeTab', null));
        }
    }
});

test('missing pages are styled on the public layout when signed out', function () {
    foreach (['/nonexistent-page-qa', '/team', '/settings/nope'] as $path) {
        $this->get($path)
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/http')
                ->where('status', 404)
                ->where('homeUrl', route('home'))
                ->where('homeLabel', 'Back to home')
                ->where('auth.user', null));
    }
});

test('a url that exists for another method still answers 405', function () {
    $this->get('/access-requests')->assertStatus(405);
    $this->get('/nonexistent-page-qa')->assertNotFound();
});

test('forbidden pages are styled with a way back for every role', function () {
    $cast = partOneCast();
    $forbidden = [
        'member' => [route('admin.users.index'), route('command.index'), route('returns.history', $cast['facility']->team)],
        'owner' => [route('admin.users.index'), route('command.map')],
        'city' => [route('admin.users.index'), route('facilities.index')],
        'province' => [route('admin.access-requests.index')],
        'country' => [route('admin.daily-returns.index')],
    ];

    foreach ($forbidden as $key => $urls) {
        foreach ($urls as $url) {
            $this->actingAs($cast[$key])
                ->get($url)
                ->assertForbidden()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('errors/http')
                    ->where('status', 403)
                    ->where('homeLabel', 'Back to home')
                    ->where('homeUrl', partOneLanding($cast)[$key]));
        }
    }
});

test('exactly one phone tab is active on every page a facility role can reach', function () {
    $cast = partOneCast();
    $team = $cast['facility']->team;

    $expected = [
        route('returns.today', $team) => 'register',
        route('returns.history', $team) => 'returns',
        route('facilities.home', $team) => 'facility',
        route('facilities.edit', $team) => 'facility',
        route('facilities.members', $team) => 'facility',
        route('facilities.invites', $team) => 'facility',
        route('facilities.activity', $team) => 'facility',
        route('profile.edit') => 'account',
        route('appearance.edit') => 'account',
    ];

    $reachable = [
        'member' => [
            route('returns.today', $team), route('facilities.home', $team), route('profile.edit'), route('appearance.edit'),
        ],
        'owner' => array_keys($expected),
    ];

    foreach ($reachable as $key => $urls) {
        foreach ($urls as $url) {
            $response = $this->actingAs($cast[$key])->get($url);

            if ($response->getStatusCode() === 403) {
                continue;
            }

            $response->assertOk()->assertInertia(function (Assert $page) use ($expected, $url) {
                $props = $page->toArray()['props'];
                $visible = ['register', 'returns', 'facility', 'account'];
                $active = $props['nav']['activeTab'];

                expect($props['nav']['shell'])->toBe('facility')
                    ->and($active)->toBe($expected[$url])
                    ->and(array_values(array_filter($visible, fn (string $tab): bool => $tab === $active)))->toHaveCount(1);
            });
        }
    }

    // The account page must not light up the facility tab, and the staff page must not light up account.
    $this->actingAs($cast['member'])->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('nav.activeTab', 'account'));
    $this->actingAs($cast['owner'])->get(route('returns.history', $team))
        ->assertInertia(fn (Assert $page) => $page->where('nav.activeTab', 'returns'));
});

test('admin lands on the command center with a needs attention strip', function () {
    $cast = partOneCast();
    $admin = $cast['admin'];

    $quiet = Facility::factory()->create();
    DailyReturn::factory()->create([
        'facility_id' => $quiet->id,
        'reported_on' => EntryDate::now()->toDateString(),
    ]);
    $silent = $cast['facility'];

    AccessRequest::query()->create([
        'name' => 'Amina', 'role' => 'Technician', 'organisation' => 'Clinic',
        'district' => 'Thatta', 'email' => 'amina@example.com',
    ]);

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $attention = $page->toArray()['props']['attention'];

            expect($attention['pendingRequests'])->toBe(1)
                ->and($attention['pendingRequestsUrl'])->toBe(route('admin.access-requests.index'))
                ->and($attention['facilitiesWithoutReturn'])->toBeGreaterThanOrEqual(1)
                ->and($attention['facilitiesWithoutReturnUrl'])->toContain('return_today=missing')
                ->and($attention['allClear'])->toBeFalse();
        });

    $filtered = $this->actingAs($admin)
        ->get(route('facilities.index', ['return_today' => 'missing', 'active' => '1']))
        ->assertOk();

    $names = collect($filtered->original->getData()['page']['props']['rows'])->pluck('cells.name');
    expect($names)->toContain('Karachi West BMU')->and($names)->not->toContain($quiet->team->name);

    // The strip is for admin only.
    foreach (['city', 'province', 'country'] as $viewer) {
        $this->actingAs($cast[$viewer])->get(route('command.index'))
            ->assertInertia(fn (Assert $page) => $page->where('attention', null));
    }

    // Admin sidebar keeps All facilities one click away as a plain link.
    $this->actingAs($admin)->get(route('command.index'))
        ->assertInertia(function (Assert $page) {
            $groups = $page->toArray()['props']['nav']['groups'];
            $admin = collect($groups)->firstWhere('key', 'administration');
            $facilities = collect($admin['items'])->firstWhere('key', 'facilities');

            expect($facilities['href'])->toBe(route('facilities.index'))
                ->and($facilities['children'])->toBe([]);
        });
});

test('the strip shows all clear when nothing needs attention', function () {
    $admin = User::factory()->create();
    partOneGive($admin, ProgrammeRole::TbaiAdmin);

    $this->actingAs($admin)
        ->get(route('command.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attention.allClear', true)
            ->where('attention.pendingRequests', 0)
            ->where('attention.facilitiesWithoutReturn', 0));
});

test('pending access requests carry approve and reject actions and decided ones do not', function () {
    $admin = User::factory()->create();
    partOneGive($admin, ProgrammeRole::TbaiAdmin);

    $pending = AccessRequest::query()->create([
        'name' => 'Amina', 'role' => 'Technician', 'organisation' => 'Clinic',
        'district' => 'Thatta', 'email' => 'amina@example.com',
    ]);
    $done = AccessRequest::query()->create([
        'name' => 'Sara', 'role' => 'Technician', 'organisation' => 'Clinic',
        'district' => 'Thatta', 'email' => 'sara@example.com',
    ]);
    $done->forceFill(['status' => AccessRequestStatus::Approved])->save();

    $this->actingAs($admin)
        ->get(route('admin.access-requests.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($pending) {
            $rows = collect($page->toArray()['props']['rows']);
            $row = $rows->firstWhere('id', (string) $pending->id);
            $decided = $rows->firstWhere('cells.name', 'Sara');

            expect(collect($row['controls'])->pluck('label')->all())->toBe(['Approve', 'Reject'])
                ->and($decided['controls'])->toBe([]);
        });
});

test('facility profile has its own subtitle and staff keeps its own', function () {
    $cast = partOneCast();
    $team = $cast['facility']->team;

    $profile = $this->actingAs($cast['owner'])->get(route('facilities.edit', $team));
    $staff = $this->actingAs($cast['owner'])->get(route('facilities.members', $team));

    $profileText = $profile->original->getData()['page']['props']['area']['description'];
    $staffText = $staff->original->getData()['page']['props']['area']['description'];

    expect($profileText)->toBe('Name, sector, kind, and location of Karachi West BMU.')
        ->and($staffText)->not->toBe($profileText)
        ->and($staffText)->toContain('People who can open');
});

test('delete account asks for the typed email and routine actions are kept apart', function () {
    $cast = partOneCast();

    $this->actingAs($cast['owner'])
        ->get(route('facilities.members', $cast['facility']->team))
        ->assertInertia(function (Assert $page) {
            $actions = collect($page->toArray()['props']['members'])->pluck('actions')->flatten(1);

            foreach ($actions as $action) {
                if (($action['label'] ?? '') === 'Deactivate') {
                    expect($action['destructive'])->toBeTrue();
                }

                if (($action['kind'] ?? '') === 'roles') {
                    expect($action)->not->toHaveKey('destructive');
                }
            }
        });
});

test('admin return history shows times in the programme time zone and says so', function () {
    $admin = User::factory()->create();
    partOneGive($admin, ProgrammeRole::TbaiAdmin);
    $facility = Facility::factory()->create();
    $return = DailyReturn::factory()->create(['facility_id' => $facility->id]);

    DailyReturnRevision::query()->create([
        'daily_return_id' => $return->id,
        'changed_by' => $admin->id,
        'changed_at' => CarbonImmutable::parse('2026-10-04 00:08:00', 'Asia/Karachi'),
        'old_values' => ['suspects' => 1],
        'new_values' => ['suspects' => 2],
    ]);

    expect(config('app.timezone'))->toBe('Asia/Karachi');

    $this->actingAs($admin)
        ->get(route('admin.revisions.index'))
        ->assertInertia(function (Assert $page) {
            $when = $page->toArray()['props']['rows'][0]['cells']['when'];

            expect($when)->toBe('4 Oct 2026, 00:08 PKT');
        });
});

test('privacy and terms carry a clearly marked placeholder and no invented legal text', function () {
    $this->get(route('privacy'))->assertOk();
    $this->get(route('terms'))->assertOk();

    expect(file_get_contents(resource_path('js/pages/terms.tsx')))->toContain('Draft pending legal review')
        ->and(file_get_contents(resource_path('js/pages/privacy.tsx')))->toContain('Draft, pending legal review');
});

test('programme access scoping for the city viewer is unchanged by the landing change', function () {
    $city = City::factory()->create(['name' => 'Karachi West']);
    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $city->id,
    ]);

    $this->post(route('login.store'), ['email' => $officer->email, 'password' => 'password'])
        ->assertRedirect(route('command.index'));
    expect(parse_url(route('command.index'), PHP_URL_PATH))->toBe('/command');
});

function partOneJoin(User $user, Facility $facility, TeamRole $role): void
{
    $facility->team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($facility->team);
}

function partOneGive(User $user, ProgrammeRole $role): void
{
    $scopeType = $role->scopeType();

    $scopeId = match ($scopeType) {
        null => null,
        ScopeType::Country => Country::factory()->create()->id,
        ScopeType::Province => Province::factory()->create()->id,
        ScopeType::City => City::factory()->create()->id,
    };

    ProgrammeAssignment::factory()->create([
        'user_id' => $user->id,
        'role' => $role,
        'scope_type' => $scopeType,
        'scope_id' => $scopeId,
    ]);
}
