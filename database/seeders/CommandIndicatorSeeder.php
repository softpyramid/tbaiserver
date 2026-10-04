<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\IncidenceTarget;
use App\Models\Province;
use App\Support\DistrictMatch;
use Database\Seeders\Support\CommandPrototypeData as Proto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * District driver indicators, province incidence, and the invented AI
 * testing example rows. The four example records are fiction, not patients.
 */
class CommandIndicatorSeeder extends Seeder
{
    /** Sindh incidence by month, JAN to OCT 2026. */
    private const INCIDENCE = [95, 90, 88, 82, 79, 71, 52, 50, 49, 45];

    /** Yearly incidence path, 2026 to 2030. */
    private const INCIDENCE_PATH = [90, 80, 70, 50, 25];

    public function run(): void
    {
        if (! app()->environment('local', 'dev', 'staging')) {
            return;
        }

        $sindh = Province::query()->where('slug', 'sindh')->firstOrFail();
        $cities = City::query()->where('province_id', $sindh->id)->get()
            ->mapWithKeys(fn (City $city): array => [DistrictMatch::key($city->name) => $city]);

        $this->districtIndicators($cities->all());
        $this->incidence($sindh);
        $this->fixtures($cities->all());
    }

    /**
     * @param  array<string, City>  $cities
     */
    private function districtIndicators(array $cities): void
    {
        $now = now()->toDateTimeString();
        $rows = [];

        foreach (Proto::SITES as $i => $site) {
            $city = $cities[DistrictMatch::key($site[0])] ?? null;

            if ($city === null) {
                continue;
            }

            $karachi = str_starts_with($site[0], 'Karachi') || in_array($site[0], ['Korangi', 'Malir'], true);
            $seed = $i + 1;
            $rows[] = [
                'city_id' => $city->id,
                'year' => CommandFacilitySeeder::ANCHOR_YEAR,
                'population' => Proto::round($site[3] * 1_150_000),
                'density_per_km2' => round($karachi ? 2400 + Proto::rnd($seed, 21) * 9600 : 45 + Proto::rnd($seed, 21) * 420, 2),
                'malnutrition_pct' => round(14 + Proto::rnd($seed, 22) * 30, 2),
                'socioeconomic_index' => round(($karachi ? 15 : 30) + Proto::rnd($seed, 23) * 55, 2),
                'avg_distance_km' => round(($karachi ? 2 : 6) + Proto::rnd($seed, 24) * ($karachi ? 8 : 24), 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('district_indicators')->upsert(
            $rows,
            ['city_id', 'year'],
            ['population', 'density_per_km2', 'malnutrition_pct', 'socioeconomic_index', 'avg_distance_km', 'updated_at'],
        );
    }

    private function incidence(Province $sindh): void
    {
        $now = now()->toDateTimeString();
        $rows = [];

        foreach (self::INCIDENCE as $m => $value) {
            $rows[] = [
                'province_id' => $sindh->id,
                'month' => sprintf('%04d-%02d', CommandFacilitySeeder::ANCHOR_YEAR, $m + 1),
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('incidence_monthly')->upsert($rows, ['province_id', 'month'], ['value', 'updated_at']);

        foreach (self::INCIDENCE_PATH as $offset => $value) {
            IncidenceTarget::query()->firstOrCreate(
                ['province_id' => $sindh->id, 'year' => IncidenceTarget::MIN_YEAR + $offset, 'month' => null],
                ['value' => $value],
            );
        }
    }

    /**
     * @param  array<string, City>  $cities
     */
    private function fixtures(array $cities): void
    {
        $now = now()->toDateTimeString();
        $records = [
            ['P-1042', 'M. Ali · 46 M', 'Karachi West · BMU 14', 'Cough > 3 weeks, night sweats, weight loss', 92, 'Xpert MTB/RIF: MTB detected (high); rifampicin resistance not detected', 'No previous TB treatment · household contact of a TB case (2025)', 'karachi-west'],
            ['P-1043', 'S. Bibi · 29 F', 'Larkana · BMU 6', 'Persistent cough, low-grade fever', 67, 'Smear microscopy 1+ AFB · Xpert pending', 'Treated for pneumonia two months ago', 'larkana'],
            ['P-1044', 'R. Kumar · 8 M', 'Tharparkar · BMU 3', 'Weight loss, contact with a TB case', 41, 'Chest X-ray: possible hilar lymphadenopathy', 'BCG vaccinated · household contact under evaluation', 'tharparkar'],
            ['P-1045', 'A. Shaikh · 58 M', 'Sukkur · BMU 9', 'Chronic cough, diabetes', 18, 'Xpert MTB/RIF: MTB not detected', 'TB treatment completed in 2019', 'sukkur'],
        ];
        $rows = [];

        foreach ($records as $sort => $record) {
            $rows[] = [
                'code' => $record[0],
                'summary' => $record[1],
                'location' => $record[2],
                'symptoms' => $record[3],
                'probability' => $record[4],
                'laboratory' => $record[5],
                'history' => $record[6],
                'city_id' => ($cities[$record[7]] ?? null)?->id,
                'sort' => $sort + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('ai_test_fixtures')->upsert(
            $rows,
            ['code'],
            ['summary', 'location', 'symptoms', 'probability', 'laboratory', 'history', 'city_id', 'sort', 'updated_at'],
        );
    }
}
