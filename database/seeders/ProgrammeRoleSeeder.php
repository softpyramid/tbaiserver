<?php

namespace Database\Seeders;

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\Country;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class ProgrammeRoleSeeder extends Seeder
{
    /**
     * Seed country, provincial, and city programme logins.
     *
     * Safe to run again. Re-running resets these accounts' passwords.
     * Geography is seeded first so each assignment has a real scope id.
     *
     *   php artisan db:seed --class=ProgrammeRoleSeeder
     *
     * Outside local and testing, set SEED_LOGIN_PASSWORD in the environment
     * before running. When config is cached, the cached value is used.
     */
    public function run(): void
    {
        $this->call(GeographySeeder::class);

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

            [$scopeType, $scopeId] = $this->scope($account['role'], $account['scope_slug']);

            ProgrammeAssignment::query()->firstOrCreate([
                'user_id' => $user->id,
                'role' => $account['role'],
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
            ]);
        }

        $this->command?->info('Programme role accounts ready:');

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
     * @return list<array{name: string, email: string, role: ProgrammeRole, scope_slug: string}>
     */
    private function accounts(): array
    {
        return [
            [
                'name' => 'Country Viewer',
                'email' => 'country@tbai.test',
                'role' => ProgrammeRole::CountryViewer,
                'scope_slug' => 'pakistan',
            ],
            [
                'name' => 'Provincial Viewer',
                'email' => 'sindh@tbai.test',
                'role' => ProgrammeRole::ProvincialViewer,
                'scope_slug' => 'sindh',
            ],
            [
                'name' => 'Karachi West Officer',
                'email' => 'karachi-west@tbai.test',
                'role' => ProgrammeRole::CityOfficer,
                'scope_slug' => 'karachi-west',
            ],
        ];
    }

    /**
     * @return array{0: ScopeType, 1: int}
     */
    private function scope(ProgrammeRole $role, string $slug): array
    {
        $scopeType = $role->scopeType();

        if ($scopeType === null) {
            throw new RuntimeException('Programme role seed accounts must have a geographic scope.');
        }

        $id = match ($scopeType) {
            ScopeType::Country => Country::query()->where('slug', $slug)->value('id'),
            ScopeType::Province => Province::query()->where('slug', $slug)->value('id'),
            ScopeType::City => City::query()->where('slug', $slug)->value('id'),
        };

        if (! is_numeric($id)) {
            throw new RuntimeException("Missing {$scopeType->value} [{$slug}] for programme role seeding.");
        }

        return [$scopeType, (int) $id];
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
