<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * LoginSeeder creates one account per role, then FacilitySeeder.
     * ProgrammeRoleSeeder still delegates to LoginSeeder for the old command.
     * Both login and facility seeders skip production. FacilitySeeder is
     * idempotent, so calling it again here does not duplicate facilities
     * or daily returns. AlertRuleSeeder uses the same environment guard.
     */
    public function run(): void
    {
        $this->call([
            LoginSeeder::class,
            FacilitySeeder::class,
            AlertRuleSeeder::class,
            CommandPrototypeSeeder::class,
        ]);
    }
}
