<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Province;
use App\Support\DistrictMatch;
use Database\Seeders\Support\CommandPrototypeData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Adds the Sindh districts the command center needs and the programme does
 * not have yet. A district that already exists (matched by the same key the
 * map uses) is left exactly as it is.
 */
class CommandGeographySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'dev', 'staging')) {
            return;
        }

        $sindh = Province::query()->where('slug', 'sindh')->first();

        if ($sindh === null) {
            throw new RuntimeException('Run GeographySeeder before CommandGeographySeeder. Sindh is missing.');
        }

        $known = City::query()
            ->where('province_id', $sindh->id)
            ->get()
            ->mapWithKeys(fn (City $city): array => [DistrictMatch::key($city->name) => $city]);

        $created = 0;

        foreach (CommandPrototypeData::SITES as $site) {
            $key = DistrictMatch::key($site[0]);

            if ($known->has($key)) {
                continue;
            }

            $slug = Str::slug($site[0]);

            City::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'province_id' => $sindh->id,
                    'name' => $site[0],
                    'code' => $slug,
                    'latitude' => $site[2],
                    'longitude' => $site[1],
                ],
            );
            $created++;
        }
    }
}
