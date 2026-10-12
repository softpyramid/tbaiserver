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
     * PopulationDenominatorSeeder runs last because it piggybacks on the
     * Sindh districts CommandPrototypeSeeder creates. IncidenceEstimateSeeder
     * is registered for every environment, production included, because the
     * national reference row must exist for the estimate card to answer.
     * SuspectRuleSetSeeder is also safe everywhere: it only adds the draft
     * suspect screening rule set, which production never serves.
     * DemoPatientSeeder (two fake patients) runs here only in local; staging
     * runs it explicitly with --class.
     */
    public function run(): void
    {
        $this->call([
            LoginSeeder::class,
            FacilitySeeder::class,
            AlertRuleSeeder::class,
            CommandPrototypeSeeder::class,
            PopulationDenominatorSeeder::class,
            IncidenceEstimateSeeder::class,
            SuspectRuleSetSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call(DemoPatientSeeder::class);
        }
    }
}
