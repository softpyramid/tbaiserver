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

    $westPublic = commandFacility($west, Sector::Public, 'West Public');
    $westPrivate = commandFacility($west, Sector::Private, 'West Private');
    $hyderabadPublic = commandFacility($hyderabad, Sector::Public, 'Hyderabad Public');
    $thattaPrivate = commandFacility($thatta, Sector::Private, 'Thatta Private');

    commandReturn($westPublic, '2026-01-31', suspects: 10, tested: 8, positive: 2, negative: 6, started: 1, followUp: 1, tat: 1.5);
    commandReturn($westPublic, '2026-02-01', suspects: 6, tested: 4, positive: 1, negative: 3, started: 1, followUp: 0, tat: 2.5);
    commandReturn($westPrivate, '2026-02-28', suspects: 4, tested: 0, positive: 0, negative: 0, started: 0, followUp: 2, tat: 9);
    commandReturn($westPrivate, '2026-03-02', suspects: 5, tested: 5, positive: 2, negative: 3, started: 1, followUp: 1, tat: null);
    commandReturn($hyderabadPublic, '2026-03-15', suspects: 12, tested: 10, positive: 3, negative: 7, started: 2, followUp: 1, tat: 4);
    commandReturn($thattaPrivate, '2026-01-10', suspects: 8, tested: 6, positive: 1, negative: 5, started: 1, followUp: 0, tat: 3);
    commandReturn($thattaPrivate, '2026-04-01', suspects: 9, tested: 7, positive: 2, negative: 5, started: 2, followUp: 1, tat: 1);

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

    expect($snapshot)->toMatchSnapshot();
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

    return commandFigureProps(is_array($page['props'] ?? null) ? $page['props'] : []);
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
        'hasReturns',
        'notification',
        'estimate',
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

    return array_intersect_key($props, array_flip($keys));
}
