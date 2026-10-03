<?php

namespace Database\Seeders\Concerns;

trait SkipsOutsideLocalDevStaging
{
    /**
     * Login and sample data run only in local, dev, and staging.
     */
    protected function skipsOutsideLocalDevStaging(): bool
    {
        if (app()->environment('local', 'dev', 'staging')) {
            return false;
        }

        $this->command?->warn('Skipped. This seeder runs only in local, dev, and staging.');

        return true;
    }
}
