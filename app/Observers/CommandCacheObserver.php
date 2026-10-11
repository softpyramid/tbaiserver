<?php

namespace App\Observers;

use App\Support\CommandCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Bump the command center cache version when a source row is saved or deleted.
 */
class CommandCacheObserver
{
    public function saved(Model $model): void
    {
        CommandCache::bump();
    }

    public function deleted(Model $model): void
    {
        CommandCache::bump();
    }
}
