<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class LoginSeeder extends Seeder
{
    /**
     * Seed verified accounts that can sign in.
     *
     * Safe to run again. Re-running resets these accounts' passwords.
     *
     *   php artisan db:seed --class=LoginSeeder
     *
     * Outside local and testing, set SEED_LOGIN_PASSWORD in the environment
     * before running. When config is cached, the cached value is used.
     */
    public function run(): void
    {
        $password = $this->password();

        foreach ($this->accounts() as $account) {
            $user = User::query()->firstOrNew([
                'email' => $account['email'],
            ]);

            $user->name = $account['name'];
            $user->password = $password;
            $user->email_verified_at ??= now();
            $user->save();

            $this->ensurePersonalTeam($user);
        }

        $this->command?->info('Login accounts ready:');

        foreach ($this->accounts() as $account) {
            $this->command?->line("  {$account['name']} <{$account['email']}>");
        }

        if ($this->usesDefaultPassword()) {
            $this->command?->comment('Sign in with password: password');

            return;
        }

        $this->command?->comment('Sign in with the SEED_LOGIN_PASSWORD value from the server environment.');
    }

    /**
     * @return list<array{name: string, email: string}>
     */
    private function accounts(): array
    {
        $adminEmail = config('access.admin_email');
        $adminEmail = is_string($adminEmail) ? strtolower(trim($adminEmail)) : '';

        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $adminEmail = 'admin@tbai.test';
        }

        $accounts = [
            ['name' => 'TB-AI Admin', 'email' => $adminEmail],
            ['name' => 'TB-AI Member', 'email' => 'member@tbai.test'],
        ];

        $seen = [];

        return array_values(array_filter(
            $accounts,
            function (array $account) use (&$seen): bool {
                if (isset($seen[$account['email']])) {
                    return false;
                }

                $seen[$account['email']] = true;

                return true;
            },
        ));
    }

    private function password(): string
    {
        $configured = config('access.seed_login_password');
        $password = is_string($configured) ? $configured : '';

        if ($password !== '') {
            return $password;
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
            && app()->environment('local', 'testing');
    }

    private function ensurePersonalTeam(User $user): void
    {
        if ($user->personalTeam() !== null) {
            return;
        }

        $team = Team::query()->create([
            'name' => $user->name."'s Team",
            'is_personal' => true,
        ]);

        $team->members()->attach($user, [
            'role' => TeamRole::Owner->value,
        ]);

        $user->switchTeam($team);
    }
}
