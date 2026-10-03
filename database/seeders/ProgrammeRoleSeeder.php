<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProgrammeRoleSeeder extends Seeder
{
    /**
     * Seed country, provincial, and city programme logins.
     *
     * Those accounts now live in LoginSeeder, which also skips production.
     * This class remains so the existing seed command still works.
     *
     *   php artisan db:seed --class=ProgrammeRoleSeeder
     */
    public function run(): void
    {
        $this->call(LoginSeeder::class);
    }
}
