<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

trait ResolvesSeedLoginPassword
{
    /**
     * SEED_LOGIN_PASSWORD when set, else "password" in local, dev, and staging.
     */
    private function password(): string
    {
        $configured = config('access.seed_login_password');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if ($this->usesDefaultPassword()) {
            return 'password';
        }

        throw new RuntimeException('Set SEED_LOGIN_PASSWORD before seeding login accounts.');
    }

    private function usesDefaultPassword(): bool
    {
        $configured = config('access.seed_login_password');

        return (! is_string($configured) || $configured === '')
            && app()->environment('local', 'dev', 'staging');
    }
}
