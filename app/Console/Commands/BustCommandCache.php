<?php

namespace App\Console\Commands;

use App\Support\CommandCache;
use Illuminate\Console\Command;

class BustCommandCache extends Command
{
    protected $signature = 'tbai:cache-bust';

    protected $description = 'Bump the command center cache version so the next request rebuilds every scope.';

    public function handle(): int
    {
        CommandCache::bump();
        $this->info('Command center cache version is now '.CommandCache::version().'.');

        return self::SUCCESS;
    }
}
