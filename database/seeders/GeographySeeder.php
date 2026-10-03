<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GeographySeeder extends Seeder
{
    /**
     * Seed Pakistan, Sindh, and the seven pitch cities. Talukas stay empty.
     *
     * Safe to run again. Existing rows are left in place.
     *
     *   php artisan db:seed --class=GeographySeeder
     *
     * City coordinates come from the command-center demo, where each site is
     * name, longitude, latitude. `code` repeats the slug because the cities
     * table requires a unique code and NTP codes are not confirmed yet.
     */
    public function run(): void
    {
        $pakistan = Country::query()->firstOrCreate(
            ['slug' => 'pakistan'],
            ['name' => 'Pakistan'],
        );

        $sindh = Province::query()->firstOrCreate(
            ['slug' => 'sindh'],
            [
                'country_id' => $pakistan->id,
                'name' => 'Sindh',
            ],
        );

        foreach ($this->cities() as $city) {
            $slug = Str::slug($city['name']);

            City::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'province_id' => $sindh->id,
                    'name' => $city['name'],
                    'code' => $slug,
                    'latitude' => $city['latitude'],
                    'longitude' => $city['longitude'],
                ],
            );
        }

        $this->command?->info('Geography ready: Pakistan, Sindh, and the seven pitch cities.');
    }

    /**
     * @return list<array{name: string, latitude: float, longitude: float}>
     */
    private function cities(): array
    {
        return [
            ['name' => 'Thatta', 'latitude' => 24.95, 'longitude' => 67.92],
            ['name' => 'Tharparkar', 'latitude' => 24.7, 'longitude' => 69.8],
            ['name' => 'Larkana', 'latitude' => 27.56, 'longitude' => 68.21],
            ['name' => 'Badin', 'latitude' => 24.66, 'longitude' => 68.84],
            ['name' => 'Qamber Shahdadkot', 'latitude' => 27.59, 'longitude' => 67.99],
            ['name' => 'Jamshoro', 'latitude' => 25.5, 'longitude' => 68.28],
            ['name' => 'Karachi West', 'latitude' => 25.0, 'longitude' => 66.9],
        ];
    }
}
