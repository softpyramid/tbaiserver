<?php

namespace App\Console\Commands;

use Database\Seeders\SuspectRuleSetSeeder;
use Illuminate\Console\Command;

class SeedSuspectRuleSet extends Command
{
    protected $signature = 'tbai:suspect-seed';

    protected $description = 'Add the draft suspect screening rule sets that are missing. No demo data, never overwrites an existing set.';

    public function handle(SuspectRuleSetSeeder $seeder): int
    {
        $seeder->run();
        $this->info('Draft suspect screening rule sets '.SuspectRuleSetSeeder::VERSION.' and '.SuspectRuleSetSeeder::VERSION_2.' are present.');

        return self::SUCCESS;
    }
}
