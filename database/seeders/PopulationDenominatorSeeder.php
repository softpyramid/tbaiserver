<?php

namespace Database\Seeders;

use App\Enums\AreaType;
use App\Enums\PopulationStatus;
use App\Models\City;
use App\Models\PopulationDenominator;
use App\Models\Province;
use App\Support\DistrictMatch;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the Sindh population denominators the command centre divides case
 * notifications by, from the Pakistan Bureau of Statistics Census 2023.
 *
 * The province row carries the verified census total (55,696,147) and is
 * confirmed. Each of the 29 Sindh districts gets a placeholder row with no
 * figure yet, so no rate is silently divided until an admin enters the
 * district's PBS figure. Rows are keyed by area type, area and reporting
 * year, so running the seeder again never overwrites an admin's figures.
 *
 *   php artisan db:seed --class=PopulationDenominatorSeeder
 */
class PopulationDenominatorSeeder extends Seeder
{
    public function run(): void
    {
        $sindh = Province::query()->where('slug', 'sindh')->first();

        $districts = [];

        if ($sindh !== null) {
            $districts = City::query()
                ->where('province_id', $sindh->id)
                ->get()
                ->mapWithKeys(fn (City $city): array => [DistrictMatch::key($city->name) => $city->id])
                ->all();
        }

        foreach ($this->rows() as $row) {
            $areaId = $row['area_type'] === AreaType::Province
                ? $sindh?->id
                : ($districts[DistrictMatch::key($row['area'])] ?? null);

            if ($areaId === null) {
                $this->command?->warn("PopulationDenominatorSeeder: no [{$row['area']}] to attach a {$row['year']} denominator to; skipped.");

                continue;
            }

            $population = $row['population'] === '' ? null : (int) $row['population'];

            PopulationDenominator::query()->firstOrCreate(
                [
                    'area_type' => $row['area_type'],
                    'area_id' => $areaId,
                    'year' => $row['year'],
                ],
                [
                    'population' => $population,
                    'status' => $population === null ? PopulationStatus::Placeholder : PopulationStatus::Confirmed,
                    'source' => $row['source'],
                    'source_year' => $row['source_year'],
                    'reference_url' => $row['reference_url'],
                ],
            );
        }
    }

    /**
     * @return list<array{area_type: AreaType, area: string, year: int, population: string, source: string, source_year: int|null, reference_url: string}>
     */
    private function rows(): array
    {
        $handle = fopen(__DIR__.'/data/population_denominators.csv', 'rb');

        if ($handle === false) {
            throw new RuntimeException('PopulationDenominatorSeeder: data CSV is missing.');
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers) || $headers[0] !== 'area_type') {
            fclose($handle);
            throw new RuntimeException('PopulationDenominatorSeeder: data CSV header is invalid.');
        }

        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) < 5) {
                continue;
            }

            $type = AreaType::tryFrom($line[0] ?? '');

            if ($type === null || trim((string) ($line[1] ?? '')) === '') {
                continue;
            }

            $rows[] = [
                'area_type' => $type,
                'area' => trim($line[1]),
                'population' => trim((string) ($line[2] ?? '')),
                'source' => trim((string) ($line[3] ?? '')),
                'source_year' => trim((string) ($line[4] ?? '')) === '' ? null : (int) $line[4],
                'reference_url' => trim((string) ($line[5] ?? '')),
                'year' => 2023,
            ];
        }

        fclose($handle);

        return $rows;
    }
}
