<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('copy on the main pages does not say team', function () {
    $langHits = visibleTeamPhrases(trans('entry'));
    $sourceHits = frontendCopyThatSaysTeam();

    expect($langHits)->toBe([])
        ->and($sourceHits)->toBe([]);

    $owner = User::factory()->create(['name' => 'West Incharge']);
    $technician = User::factory()->create(['name' => 'Ayesha Khan']);
    $facility = Facility::factory()->create();
    $facility->team->update(['name' => 'West BMU']);
    attachToFacility($owner, $facility, TeamRole::Owner);
    attachToFacility($technician, $facility, TeamRole::Member);

    foreach ([$owner, $technician] as $person) {
        $response = $this->actingAs($person)
            ->get(route('returns.today', $facility->team))
            ->assertOk();

        expect(visibleTeamPhrases($response->original->getData()['page']['props'] ?? []))->toBe([])
            ->and(strtolower($response->getContent()))->not->toContain('personal team');
    }

    $this->actingAs($owner)
        ->get(route('facilities.members', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('labels.member', 'Name')
            ->where('labels.email', 'Email')
            ->where('labels.role', 'Role')
            ->where('labels.facility', 'Facility'));

    $profile = $this->actingAs($owner)
        ->get(route('facilities.edit', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canDeactivate', false));

    expect(visibleTeamPhrases($profile->original->getData()['page']['props'] ?? []))->toBe([]);
});

test('an in-charge cannot deactivate a facility', function () {
    $facility = Facility::factory()->create();
    $owner = User::factory()->create();
    attachToFacility($owner, $facility, TeamRole::Owner);

    $this->actingAs($owner)
        ->get(route('facilities.edit', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canDeactivate', false));

    $this->actingAs($owner)
        ->delete(route('teams.destroy', $facility->team))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('facilities.deactivate', $facility))
        ->assertForbidden();
});

test('viewers and the tb-ai admin land without a facility slug', function () {
    $country = Country::factory()->create(['name' => 'Pakistan']);
    $province = Province::factory()->create(['name' => 'Sindh', 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Karachi West', 'province_id' => $province->id]);

    $viewers = [
        [ProgrammeRole::CityOfficer, ScopeType::City, $city->id],
        [ProgrammeRole::ProvincialViewer, ScopeType::Province, $province->id],
        [ProgrammeRole::CountryViewer, ScopeType::Country, $country->id],
    ];

    foreach ($viewers as [$role, $scopeType, $scopeId]) {
        $viewer = User::factory()->create();
        ProgrammeAssignment::factory()->create([
            'user_id' => $viewer->id,
            'role' => $role,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
        ]);

        $this->post(route('login.store'), [
            'email' => $viewer->email,
            'password' => 'password',
        ])->assertRedirect(route('command.index'));

        $this->assertAuthenticatedAs($viewer);

        $home = $this->actingAs($viewer)
            ->get(route('command.index'))
            ->assertOk();

        expect($home->headers->get('Location'))->toBeNull();
        expect(parse_url(route('command.index'), PHP_URL_PATH))->toBe('/command');

        $this->post(route('logout'));
    }

    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $admin->id,
        'role' => ProgrammeRole::TbaiAdmin,
        'scope_type' => null,
        'scope_id' => null,
    ]);

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('facilities.index'));

    expect(parse_url(route('facilities.index'), PHP_URL_PATH))->toBe('/admin/facilities');
});

test('a viewer sees a scope label and no facility switcher', function () {
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
        ->assertInertia(fn (Assert $page) => $page
            ->where('scopeLabel', 'Sindh')
            ->where('teams', [])
            ->where('currentTeam', null));
});

test('old programme team urls redirect to the role home', function () {
    $country = Country::factory()->create(['name' => 'Pakistan']);
    $viewer = User::factory()->create();
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::CountryViewer,
        'scope_type' => ScopeType::Country,
        'scope_id' => $country->id,
    ]);

    Team::factory()->create([
        'name' => 'Pakistan viewers',
        'slug' => 'country-viewers-team',
        'is_personal' => true,
    ]);

    $this->actingAs($viewer)
        ->get('/country-viewers-team/dashboard')
        ->assertStatus(302)
        ->assertRedirect(route('command.index'));
});

function attachToFacility(User $user, Facility $facility, TeamRole $role): void
{
    $facility->team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($facility->team);
}

/**
 * @return list<string>
 */
function frontendCopyThatSaysTeam(): array
{
    $hits = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        resource_path('js'),
    ));

    foreach ($files as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.tsx')) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());
        $source = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;
        $source = preg_replace('#^\s*//.*$#m', '', $source) ?? $source;

        preg_match_all("/(['\"`])((?:\\\\.|(?!\\1).)*)\\1/s", $source, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $text = stripcslashes($match[2]);
            $text = preg_replace('/\$\{[^}]*\}/', '', $text) ?? $text;

            if (copySaysTeam($text)) {
                $hits[] = $file->getFilename().': '.$text;
            }
        }

        preg_match_all('#>([^<>{}]*[A-Za-z][^<>{}]*)<#', $source, $jsx);

        foreach ($jsx[1] as $text) {
            $text = trim($text);

            if (copySaysTeam($text)) {
                $hits[] = $file->getFilename().': '.$text;
            }
        }
    }

    return $hits;
}

function copySaysTeam(string $text): bool
{
    if ($text === '' || str_contains($text, '/') || str_starts_with($text, '@/') || str_starts_with($text, '.')) {
        return false;
    }

    if (! preg_match('/\s/u', $text) && (str_contains($text, '_') || str_contains($text, '-') || str_contains($text, '.'))) {
        return false;
    }

    return preg_match('/\bteams?\b/i', $text) === 1;
}

/**
 * @return list<string>
 */
function visibleTeamPhrases(mixed $value, string $path = ''): array
{
    if (is_string($value)) {
        return copySaysTeam($value) ? [$path.': '.$value] : [];
    }

    if (! is_array($value)) {
        return [];
    }

    $hits = [];

    foreach ($value as $key => $item) {
        $hits = [
            ...$hits,
            ...visibleTeamPhrases($item, $path === '' ? (string) $key : $path.'.'.$key),
        ];
    }

    return $hits;
}
