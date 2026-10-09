<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\AccessRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use App\Support\IntendedUrl;
use App\Support\RoleHome;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;

test('programme roles open command center from executive view and detailed reports', function () {
    $country = Country::factory()->create();
    $sindh = Province::factory()->create(['name' => 'Sindh', 'country_id' => $country->id]);
    $west = City::factory()->create(['name' => 'Karachi West', 'province_id' => $sindh->id]);

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $countryViewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $countryViewer->id,
        'role' => ProgrammeRole::CountryViewer,
        'scope_type' => ScopeType::Country,
        'scope_id' => $country->id,
    ]);

    $provincial = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $provincial->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $sindh->id,
    ]);

    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $west->id,
    ]);

    $pages = [
        route('command.index') => ['executive' => false, 'reports' => true, 'registered' => false],
        route('command.center') => ['executive' => true, 'reports' => false, 'registered' => false],
        route('command.sectors.show', ['sector' => 'public']) => ['executive' => false, 'reports' => true, 'registered' => true],
    ];

    foreach ([$admin, $countryViewer, $provincial, $officer] as $user) {
        foreach ($pages as $url => $flags) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertInertia(function (Assert $page) use ($flags, $url, $user) {
                    $group = collect($page->toArray()['props']['nav']['groups'])->firstWhere('key', 'command');
                    $first = $group['items'][0];
                    $keys = array_column($group['items'], 'key');

                    expect($group['label'])->toBe('Command Center', $url)
                        ->and($first['key'])->toBe('command_center')
                        ->and($first['href'])->toBeNull()
                        ->and($first['expanded'])->toBeTrue()
                        ->and($keys)->not->toContain('command')
                        ->and(array_column($first['children'], 'key'))->toBe([
                            'command_center_executive',
                            'command_center_reports',
                        ])
                        ->and(array_column($first['children'], 'label'))->toBe([
                            'Executive view',
                            'Detailed reports',
                        ])
                        ->and(array_column($first['children'], 'href'))->toBe([
                            route('command.center'),
                            route('command.index'),
                        ])
                        ->and($first['children'][0]['current'])->toBe($flags['executive'])
                        ->and($first['children'][0]['blank'])->toBeTrue()
                        ->and($first['children'][1]['current'])->toBe($flags['reports'])
                        ->and($first['children'][1])->not->toHaveKey('blank');

                    if ($user->isTbaiAdmin()) {
                        expect($keys)->toBe(['command_center']);

                        return;
                    }

                    expect(array_slice($keys, 0, 5))->toBe([
                        'command_center',
                        'registered',
                        'command_map',
                        'command_alerts',
                        'daily_returns',
                    ])->and(navEntry($page, 'command', 'registered')['expanded'])->toBe($flags['registered']);
                });
        }
    }
});

test('facility roles have no command center parent and cannot open the executive view', function () {
    $facility = Facility::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();
    joinFacility($owner, $facility, TeamRole::Owner);
    joinFacility($member, $facility, TeamRole::Member);

    $this->actingAs($owner)
        ->get(route('facilities.home', $facility->team))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            expect(navItemKeys($page))->not->toContain('command_center')
                ->and(navGroupKeys($page))->toBe(['facility'])
                ->and(navItemKeys($page))->toBe([
                    'register',
                    'returns_history',
                    'my_facility',
                ]);
        });

    $this->actingAs($member)
        ->get(route('returns.today', $facility->team))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            expect(navItemKeys($page))->not->toContain('command_center')
                ->and(navItemKeys($page))->toBe([
                    'register',
                    'my_facility',
                ]);
        });

    $this->actingAs($owner)->get(route('command.center'))->assertForbidden();
    $this->actingAs($member)->get(route('command.center'))->assertForbidden();
});

test('the executive view hides admin command tabs and keeps administration in order', function () {
    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    AccessRequest::query()->create([
        'name' => 'Amina',
        'role' => 'Technician',
        'organisation' => 'Clinic',
        'district' => 'Thatta',
        'email' => 'amina@example.com',
    ]);

    $this->actingAs($admin)
        ->get(route('command.center'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];
            $items = collect($props['nav']['groups'])->flatMap(fn (array $group): array => $group['items']);
            $administration = collect($props['nav']['groups'])->firstWhere('key', 'administration');

            expect($props['nav']['commandTabs'])->toBe([])
                ->and($items->pluck('key')->all())->not->toContain('registered')
                ->and($items->pluck('key')->all())->not->toContain('command_map')
                ->and($items->pluck('key')->all())->not->toContain('command_alerts')
                ->and($items->pluck('key')->all())->not->toContain('command_drivers')
                ->and($items->pluck('key')->all())->not->toContain('command_incidence')
                ->and($items->pluck('key')->all())->not->toContain('daily_returns')
                ->and(array_column($administration['items'], 'key'))->toBe([
                    'facilities',
                    'returns',
                    'people',
                    'kpis',
                    'audit_log',
                ])
                ->and(collect($administration['items'])->firstWhere('key', 'people')['badge'])->toBe(1);
        });
});

test('drivers and incidence stay beside the command center parent when their flags are on', function () {
    $province = Province::factory()->create(['name' => 'Sindh']);
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
        ->assertInertia(function (Assert $page) {
            expect(navItemKeys($page))->toBe([
                'command_center',
                'registered',
                'command_map',
                'command_alerts',
                'daily_returns',
            ])->and(navItemKeys($page))->not->toContain('command_drivers')
                ->and(navItemKeys($page))->not->toContain('command_incidence');
        });

    enableFeature('drivers', 'incidence');

    $this->actingAs($viewer)
        ->get(route('command.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            expect(navItemKeys($page))->toBe([
                'command_center',
                'registered',
                'command_map',
                'command_alerts',
                'command_drivers',
                'command_incidence',
                'daily_returns',
            ]);
        });
});

test('command center landing sends programme roles to the executive view only when the flag is on', function () {
    $country = Country::factory()->create();
    $province = Province::factory()->create(['country_id' => $country->id]);
    $city = City::factory()->create(['province_id' => $province->id]);
    $facility = Facility::factory()->create(['city_id' => $city->id]);

    $admin = User::factory()->create();
    giveProgrammeRole($admin, ProgrammeRole::TbaiAdmin);

    $countryViewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $countryViewer->id,
        'role' => ProgrammeRole::CountryViewer,
        'scope_type' => ScopeType::Country,
        'scope_id' => $country->id,
    ]);

    $provincial = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $provincial->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $province->id,
    ]);

    $officer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $city->id,
    ]);

    $member = User::factory()->create();
    joinFacility($member, $facility, TeamRole::Member);

    $home = app(RoleHome::class);
    $register = route('returns.today', $facility->team);

    expect(config('tbai.command_center.landing'))->toBeFalse()
        ->and(config('tbai.area_defaults'))->toBe([
            'local' => true,
            'dev' => true,
            'staging' => true,
            'production' => false,
        ])
        ->and($home->url($admin))->toBe(route('command.index'))
        ->and($home->url($countryViewer))->toBe(route('command.index'))
        ->and($home->url($provincial))->toBe(route('command.index'))
        ->and($home->url($officer))->toBe(route('command.index'))
        ->and($home->url($member))->toBe($register);

    Config::set('tbai.command_center.landing', true);

    expect($home->url($admin))->toBe(route('command.center'))
        ->and($home->url($countryViewer))->toBe(route('command.center'))
        ->and($home->url($provincial))->toBe(route('command.center'))
        ->and($home->url($officer))->toBe(route('command.center'))
        ->and($home->url($member))->toBe($register);
});

test('an intended command center url is kept for programme roles and dropped for facility staff', function () {
    $province = Province::factory()->create();
    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $province->id,
    ]);

    $facility = Facility::factory()->create();
    $member = User::factory()->create();
    joinFacility($member, $facility, TeamRole::Member);

    $intended = app(IntendedUrl::class);

    expect($intended->permits($viewer, '/command/center'))->toBeTrue()
        ->and($intended->permits($member, '/command/center'))->toBeFalse();
});
