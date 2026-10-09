<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Province;
use App\Support\DistrictMatch;
use Database\Seeders\Support\CommandPrototypeData as Proto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The founder prototype's Public, Private and PPM district tables, stored as
 * they are drawn so the sector pages of a staging site match them exactly.
 * The All table, Intelligence and the map still come from daily returns.
 *
 * Runs only in local, dev, and staging. Deterministic and safe to run again:
 * it upserts by district and sector and never touches returns or users.
 */
class CommandSectorIndicatorSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'dev', 'staging')) {
            return;
        }

        $sindh = Province::query()->where('slug', 'sindh')->first();

        if ($sindh === null) {
            return;
        }

        $cities = City::query()->where('province_id', $sindh->id)->get()
            ->mapWithKeys(fn (City $city): array => [DistrictMatch::key($city->name) => $city->id]);
        $now = now();
        $rows = [];

        foreach (['public', 'private', 'ppm'] as $sector) {
            foreach (Proto::dataset($sector) as $row) {
                $cityId = $cities->get(DistrictMatch::key((string) $row['name']));

                if ($cityId === null) {
                    continue;
                }

                $rows[] = [
                    'city_id' => $cityId,
                    'sector' => $sector,
                    'suspects' => $row['sus'],
                    'tested' => $row['tested'],
                    'positive' => $row['pos'],
                    'started_treatment' => $row['st'],
                    // Stored pooled from the counts so it always matches the
                    // positivity the app computes from positive ÷ tested.
                    'positivity' => $row['tested'] > 0 ? round($row['pos'] / $row['tested'] * 100, 4) : 0.0,
                    'success_pct' => $row['su'],
                    'ltfu_pct' => $row['ltfu'],
                    'tat_days' => $row['tat'],
                    'mdr_rr' => $row['mdr'],
                    'ai_tests' => $row['ai'],
                    'bmus' => $row['bmu'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('sector_indicators')->upsert(
                $chunk,
                ['city_id', 'sector'],
                ['suspects', 'tested', 'positive', 'started_treatment', 'positivity', 'success_pct', 'ltfu_pct', 'tat_days', 'mdr_rr', 'ai_tests', 'bmus', 'updated_at'],
            );
        }
    }
}
