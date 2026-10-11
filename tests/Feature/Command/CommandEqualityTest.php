<?php

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\Sector;
use App\Models\City;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\ReloadRequest;

/**
 * Fixed fixture. These figures are the contract for the SQL rewrite and the cache.
 */
test('command figures match across a cold cache and a warm cache for each scope', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-20 12:00:00', 'Asia/Karachi'));
    enableFeature('drivers', 'incidence', 'ai_tests');
    config(['tbai.command.default_period' => 'ytd']);

    $sindh = Province::factory()->create(['name' => 'Sindh', 'slug' => 'sindh']);
    $west = City::factory()->create(['province_id' => $sindh->id, 'name' => 'Karachi West', 'slug' => 'karachi-west']);
    $hyderabad = City::factory()->create(['province_id' => $sindh->id, 'name' => 'Hyderabad', 'slug' => 'hyderabad']);
    $thatta = City::factory()->create(['province_id' => $sindh->id, 'name' => 'Thatta', 'slug' => 'thatta']);

    $facility = function (City $city, Sector $sector, string $name): Facility {
        $created = Facility::factory()->create([
            'city_id' => $city->id,
            'sector' => $sector,
        ]);
        $created->team->update(['name' => $name]);

        return $created;
    };

    $store = function (Facility $site, string $date, int $suspects, int $tested, int $positive, int $negative, int $started, int $followUp, ?float $tat) {
        DailyReturn::factory()->create([
            'facility_id' => $site->id,
            'reported_on' => $date,
            'suspects' => $suspects,
            'tested' => $tested,
            'positive' => $positive,
            'negative' => $negative,
            'started_treatment' => $started,
            'follow_up' => $followUp,
            'median_tat_days' => $tat,
        ]);
    };

    $westPublic = $facility($west, Sector::Public, 'West Public');
    $westPrivate = $facility($west, Sector::Private, 'West Private');
    $hyderabadPublic = $facility($hyderabad, Sector::Public, 'Hyderabad Public');
    $thattaPrivate = $facility($thatta, Sector::Private, 'Thatta Private');

    $store($westPublic, '2026-01-31', 10, 8, 2, 6, 1, 1, 1.5);
    $store($westPublic, '2026-02-01', 6, 4, 1, 3, 1, 0, 2.5);
    $store($westPrivate, '2026-02-28', 4, 0, 0, 0, 0, 2, 9);
    $store($westPrivate, '2026-03-02', 5, 5, 2, 3, 1, 1, null);
    $store($hyderabadPublic, '2026-03-15', 12, 10, 3, 7, 2, 1, 4);
    $store($thattaPrivate, '2026-01-10', 8, 6, 1, 5, 1, 0, 3);
    $store($thattaPrivate, '2026-04-01', 9, 7, 2, 5, 2, 1, 1);

    $admin = User::factory()->create(['email' => 'admin-equality@tbai.test']);
    ProgrammeAssignment::factory()->create([
        'user_id' => $admin->id,
        'role' => ProgrammeRole::TbaiAdmin,
        'scope_type' => null,
        'scope_id' => null,
    ]);

    $viewer = User::factory()->create(['email' => 'sindh-equality@tbai.test']);
    ProgrammeAssignment::factory()->create([
        'user_id' => $viewer->id,
        'role' => ProgrammeRole::ProvincialViewer,
        'scope_type' => ScopeType::Province,
        'scope_id' => $sindh->id,
    ]);

    $officer = User::factory()->create(['email' => 'west-equality@tbai.test']);
    ProgrammeAssignment::factory()->create([
        'user_id' => $officer->id,
        'role' => ProgrammeRole::CityOfficer,
        'scope_type' => ScopeType::City,
        'scope_id' => $west->id,
    ]);

    $paths = [
        '/command',
        '/command/map',
        '/command/alerts',
        '/command/sectors/public',
        '/command/sectors/public/districts/'.$west->id,
        '/api/command/frame',
        '/api/command/cumulative',
        '/api/command/intelligence',
        '/api/command/sectors/public',
        '/api/command/sectors/private',
        '/api/command/sectors/ppm',
        '/api/command/sectors/public/districts/'.$west->id.'/bmus',
        '/api/command/map',
        '/api/command/drivers',
        '/api/command/ai-tests',
        '/api/command/incidence',
    ];

    $snapshot = [];

    foreach (['admin' => $admin, 'sindh' => $viewer, 'karachi_west' => $officer] as $role => $user) {
        foreach ($paths as $path) {
            Cache::flush();
            $cold = commandPayload($this->actingAs($user)->get($path));
            Cache::flush();
            $rebuilt = commandPayload($this->actingAs($user)->get($path));
            $warm = commandPayload($this->actingAs($user)->get($path));

            expect($rebuilt)->toEqual($cold)
                ->and($warm)->toEqual($cold);

            $snapshot[$role][$path] = $cold;
        }
    }

    expect($snapshot['admin']['/command'])->not->toEqual($snapshot['sindh']['/command'])
        ->and($snapshot['sindh']['/command'])->not->toEqual($snapshot['karachi_west']['/command'])
        ->and($snapshot['admin']['/api/command/cumulative'])->not->toEqual($snapshot['karachi_west']['/api/command/cumulative']);

    expect(commandSort($snapshot))->toMatchSnapshot();
});

/**
 * @return array<string, mixed>
 */
function commandPayload(TestResponse $response): array
{
    $response->assertSuccessful();
    $type = (string) $response->headers->get('content-type');

    if (str_contains($type, 'application/json')) {
        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        if (isset($payload['meta']) && is_array($payload['meta'])) {
            unset($payload['meta']['generated_at']);
        }

        return $payload;
    }

    $page = json_decode(json_encode($response->viewData('page')), true);
    $props = is_array($page['props'] ?? null) ? $page['props'] : [];
    $deferred = [];

    foreach ($page['deferredProps'] ?? [] as $group) {
        if (is_array($group)) {
            array_push($deferred, ...$group);
        }
    }

    if ($deferred !== []) {
        $reload = (new ReloadRequest(
            (string) ($page['url'] ?? '/command'),
            (string) ($page['component'] ?? 'command/index'),
            (string) ($page['version'] ?? ''),
            implode(',', $deferred),
        ))();
        $extra = $reload->headers->get('content-type') && str_contains((string) $reload->headers->get('content-type'), 'json')
            ? $reload->json('props')
            : (json_decode(json_encode($reload->viewData('page')), true)['props'] ?? null);

        if (is_array($extra)) {
            $props = array_merge($props, $extra);
        }
    }

    return commandFigureProps($props);
}

/**
 * @param  array<string, mixed>  $props
 * @return array<string, mixed>
 */
function commandFigureProps(array $props): array
{
    $keys = [
        'scope',
        'totals',
        'kpis',
        'cascade',
        'months',
        'notification',
        'estimate',
        'hasReturns',
        'rows',
        'facilities',
        'districts',
        'alerts',
        'rules',
        'top',
        'hiddenMatches',
        'categories',
        'indicator',
        'selectedId',
    ];

    $out = [];

    foreach ($keys as $key) {
        if (array_key_exists($key, $props)) {
            $out[$key] = $props[$key];
        }
    }

    return $out;
}

/**
 * @param  array<string, mixed>  $value
 * @return array<string, mixed>
 */
function commandSort(array $value): array
{
    foreach ($value as $key => $item) {
        if (is_array($item)) {
            $value[$key] = commandSort($item);
        }
    }

    if (! array_is_list($value)) {
        ksort($value);
    }

    return $value;
}
