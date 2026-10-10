<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\IncidenceEstimate;
use Illuminate\Database\Seeder;

/**
 * The national TB incidence reference for Pakistan, 292 per 100,000 in 2026.
 * Registered for every environment, production included, because the estimate
 * card depends on a row existing to answer anything other than "No approved
 * estimate entered". Keyed on the country area (not the year) and never
 * updated once present, so admin edits to its source, method or year survive
 * a reseed.
 */
class IncidenceEstimateSeeder extends Seeder
{
    public function run(): void
    {
        $pakistan = Country::query()->firstOrCreate(
            ['slug' => 'pakistan'],
            ['name' => 'Pakistan'],
        );

        $estimate = IncidenceEstimate::query()
            ->where('area_type', 'country')
            ->whereNull('area_id')
            ->first();

        if ($estimate === null) {
            IncidenceEstimate::query()->create([
                'area_type' => 'country',
                'area_id' => null,
                'year' => 2026,
                'rate_per_100k' => 292.00,
                'source' => 'Pakistan estimate, 292 per 100,000, source to be confirmed by Hira',
                'method' => 'To be confirmed by Hira',
                'entered_by' => null,
            ]);
        }
    }
}
