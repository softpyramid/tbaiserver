<?php

namespace App\Console\Commands;

use Database\Seeders\SuspectRuleSetSeeder;
use Illuminate\Console\Command;

class SeedSuspectRuleSet extends Command
{
    protected $signature = 'tbai:suspect-seed';

    protected $description = 'Add the draft suspect screening rule set if it is missing. No demo data, never overwrites an existing set.';

    public function handle(SuspectRuleSetSeeder $seeder): int
    {
        $seeder->run();
        $this->info('Draft suspect screening rule set '.SuspectRuleSetSeeder::VERSION.' is present.');

        return self::SUCCESS;
    }
}
