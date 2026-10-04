<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Staging data for the command center: the 29 Sindh districts, facilities
 * and returns that reproduce the programme headline, monthly outcome and
 * driver tables, province incidence, the AI testing fixtures, and the
 * prototype's Public, Private and PPM district tables.
 *
 * Runs only in local, dev, and staging. In any other environment (production
 * included) it writes nothing. Deterministic and safe to run again: each
 * step upserts by natural key and never edits or deletes existing users,
 * facilities, or returns.
 *
 *   php artisan db:seed --class=CommandPrototypeSeeder
 */
class CommandPrototypeSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'dev', 'staging')) {
            return;
        }

        $this->call([
            GeographySeeder::class,
            CommandGeographySeeder::class,
            CommandFacilitySeeder::class,
            CommandIndicatorSeeder::class,
            CommandSectorIndicatorSeeder::class,
        ]);
    }
}
