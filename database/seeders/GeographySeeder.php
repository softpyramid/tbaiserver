<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\Taluka;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class GeographySeeder extends Seeder
{
    /**
     * Seed Pakistan, Sindh, the seven pitch cities, and Sindh test districts.
     *
     * Talukas are seeded for the districts used by FacilitySeeder. Safe to run
     * again. Existing rows are left in place.
     *
     *   php artisan db:seed --class=GeographySeeder
     *
     * City coordinates come from the command-center demo, where each site is
     * name, longitude, latitude. Extra test districts use the same shape.
     * `code` repeats the slug because the cities table requires a unique code
     * and NTP codes are not confirmed yet.
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

        $cities = [];

        foreach ($this->cities() as $city) {
            $slug = Str::slug($city['name']);

            $cities[$slug] = City::query()->firstOrCreate(
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

        foreach ($this->talukas() as $citySlug => $names) {
            $city = $cities[$citySlug] ?? null;

            if ($city === null) {
                throw new RuntimeException("Missing city [{$citySlug}] for taluka seeding.");
            }

            foreach ($names as $name) {
                Taluka::query()->firstOrCreate(
                    [
                        'city_id' => $city->id,
                        'slug' => Str::slug($name),
                    ],
                    ['name' => $name],
                );
            }
        }

        $this->command?->info('Geography ready: Pakistan, Sindh, pitch cities, and Sindh test districts.');
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
            ['name' => 'Karachi East', 'latitude' => 24.86, 'longitude' => 67.07],
            ['name' => 'Hyderabad', 'latitude' => 25.4, 'longitude' => 68.36],
            ['name' => 'Sukkur', 'latitude' => 27.7, 'longitude' => 68.86],
            ['name' => 'Mirpurkhas', 'latitude' => 25.53, 'longitude' => 69.01],
        ];
    }

    /**
     * Real talukas for the Sindh districts that FacilitySeeder uses.
     *
     * @return array<string, list<string>>
     */
    private function talukas(): array
    {
        return [
            'karachi-west' => ['Orangi', 'Baldia', 'SITE'],
            'karachi-east' => ['Gulshan-e-Iqbal', 'Jamshed', 'Ferozabad'],
            'hyderabad' => ['Latifabad', 'Qasimabad'],
            'sukkur' => ['Sukkur City', 'Rohri', 'New Sukkur'],
            'larkana' => ['Ratodero', 'Dokri'],
            'mirpurkhas' => ['Digri', 'Hussain Bux Mari'],
        ];
    }
}
