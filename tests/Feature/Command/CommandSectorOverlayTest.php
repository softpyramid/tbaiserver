<?php

use App\Models\City;
use App\Support\DistrictMatch;
use Database\Seeders\Support\CommandPrototypeData;
use Illuminate\Support\Facades\DB;

/**
 * What the prototype prints for a rate: JavaScript toFixed on the raw number.
 */
function overlayPrinted(float $value, int $decimals): string
{
    return number_format(round($value, $decimals), $decimals, '.', '');
}

test('staging sector pages equal the prototype Public, Private and PPM tables exactly', function () {
    $users = commandApiUsers();

    foreach (['public', 'private', 'ppm'] as $sector) {
        $response = $this->actingAs($users['admin'])->getJson("/api/command/sectors/{$sector}")->assertOk();
        $rows = collect($response->json('data.rows'))->keyBy('match_key');
        $proto = CommandPrototypeData::dataset($sector);

        expect($rows)->toHaveCount(29);

        foreach ($proto as $p) {
            $r = $rows->get(DistrictMatch::key((string) $p['name']));

            expect($r)->not->toBeNull()
                ->and([$r['suspects'], $r['tested'], $r['positive'], $r['mdr_rr'], $r['ai_tests'], $r['bmus']])
                ->toBe([$p['sus'], $p['tested'], $p['pos'], $p['mdr'], $p['ai'], $p['bmu']], "{$sector} {$p['name']}")
                ->and([
                    overlayPrinted($r['positivity'], 1),
                    overlayPrinted($r['started_pct'], 0),
                    overlayPrinted($r['success_pct'], 0),
                    overlayPrinted($r['ltfu_pct'], 1),
                    overlayPrinted($r['tat_days'], 1),
                ])->toBe([
                    overlayPrinted($p['pr'], 1),
                    overlayPrinted($p['pos'] > 0 ? $p['st'] / $p['pos'] * 100 : 0, 0),
                    overlayPrinted($p['su'], 0),
                    overlayPrinted($p['ltfu'], 1),
                    overlayPrinted($p['tat'], 1),
                ], "{$sector} {$p['name']}");
        }

        // The total row is the prototype's: sums for counts, plain means for rates.
        $total = $response->json('data.total');
        $mean = fn (string $key): float => array_sum(array_column($proto, $key)) / count($proto);

        expect([$total['suspects'], $total['tested'], $total['positive'], $total['mdr_rr'], $total['ai_tests'], $total['bmus']])
            ->toBe([
                array_sum(array_column($proto, 'sus')), array_sum(array_column($proto, 'tested')), array_sum(array_column($proto, 'pos')),
                array_sum(array_column($proto, 'mdr')), array_sum(array_column($proto, 'ai')), array_sum(array_column($proto, 'bmu')),
            ])
            ->and([overlayPrinted($total['positivity'], 1), overlayPrinted($total['success_pct'], 0), overlayPrinted($total['ltfu_pct'], 1), overlayPrinted($total['tat_days'], 1)])
            ->toBe([overlayPrinted($mean('pr'), 1), overlayPrinted($mean('su'), 0), overlayPrinted($mean('ltfu'), 1), overlayPrinted($mean('tat'), 1)], $sector);
    }
});

test('All, Intelligence and the map do not read the sector overlay', function () {
    $users = commandApiUsers();
    $paths = ['/api/command/cumulative', '/api/command/intelligence', '/api/command/map', '/api/command/map?sector=public'];
    $before = array_map(fn (string $path): array => $this->actingAs($users['admin'])->getJson($path)->assertOk()->json('data'), $paths);

    DB::table('sector_indicators')->delete();

    $after = array_map(fn (string $path): array => $this->actingAs($users['admin'])->getJson($path)->assertOk()->json('data'), $paths);

    expect($after)->toBe($before);
});

test('without overlay rows a sector page is computed from daily returns, and a partial overlay mixes both', function () {
    $users = commandApiUsers();
    $west = City::query()->where('slug', 'karachi-west')->firstOrFail();
    $sukkur = City::query()->where('slug', 'sukkur')->firstOrFail();

    $seeded = $this->actingAs($users['admin'])->getJson('/api/command/sectors/public')->assertOk();
    DB::table('sector_indicators')->where('sector', 'public')->where('city_id', $west->id)->delete();
    $mixed = $this->actingAs($users['admin'])->getJson('/api/command/sectors/public')->assertOk();
    $row = fn ($response, City $city) => collect($response->json('data.rows'))->firstWhere('city_id', $city->id);

    // The district without an overlay row is computed; the others keep the published figures.
    expect($row($mixed, $sukkur))->toBe($row($seeded, $sukkur))
        ->and($row($mixed, $west)['suspects'])->not->toBe($row($seeded, $west)['suspects'])
        ->and($mixed->json('data.total.suspects'))->not->toBe($seeded->json('data.total.suspects'));

    DB::table('sector_indicators')->delete();
    $computed = $this->actingAs($users['admin'])->getJson('/api/command/sectors/public')->assertOk();
    $map = $this->actingAs($users['admin'])->getJson('/api/command/map?sector=public')->assertOk();

    expect($row($computed, $west)['suspects'])->toBe($row($mixed, $west)['suspects'])
        ->and(collect($map->json('data.districts'))->firstWhere('city_id', $west->id)['suspects'])->toBe($row($computed, $west)['suspects']);
});

test('a district officer gets only their district from the overlay, and the BMU modal adds up to the row', function () {
    $users = commandApiUsers();
    $west = City::query()->where('slug', 'karachi-west')->firstOrFail();
    $sukkur = City::query()->where('slug', 'sukkur')->firstOrFail();

    foreach (['public', 'private', 'ppm'] as $sector) {
        $officer = $this->actingAs($users['karachi_west'])->getJson("/api/command/sectors/{$sector}")->assertOk();
        $proto = collect(CommandPrototypeData::dataset($sector))->firstWhere('name', 'Karachi West');

        expect($officer->json('data.rows'))->toHaveCount(1)
            ->and($officer->json('data.rows.0.suspects'))->toBe($proto['sus'])
            ->and($officer->json('data.total.suspects'))->toBe($proto['sus'])
            ->and(json_encode($officer->json()))->not->toContain('Sukkur');
    }

    $this->actingAs($users['karachi_west'])->getJson("/api/command/sectors/public/districts/{$sukkur->id}/bmus")->assertForbidden();

    foreach (['public', 'private'] as $sector) {
        $row = collect($this->actingAs($users['admin'])->getJson("/api/command/sectors/{$sector}")->json('data.rows'))->firstWhere('city_id', $west->id);
        $modal = $this->actingAs($users['karachi_west'])->getJson("/api/command/sectors/{$sector}/districts/{$west->id}/bmus")->assertOk();
        $items = collect($modal->json('data.bmus'));

        expect($items->sum('suspects'))->toBe($row['suspects'])
            ->and($items->sum('tested'))->toBe($row['tested'])
            ->and($items->sum('positive'))->toBe($row['positive'])
            ->and($modal->json('data.summary.positive'))->toBe($row['positive'])
            ->and($items->every(fn (array $b): bool => collect($b['months'])->sum('positive') === $b['positive'] && collect($b['months'])->sum('tested') === $b['tested']))->toBeTrue();
    }
});
