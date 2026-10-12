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
use Database\Seeders\Concerns\ResolvesSeedLoginPassword;
use Database\Seeders\Concerns\SkipsOutsideLocalDevStaging;
use Illuminate\Database\Seeder;
use RuntimeException;

class LoginSeeder extends Seeder
{
    use ResolvesSeedLoginPassword;
    use SkipsOutsideLocalDevStaging;

    /**
     * Seed one verified account per role for local, dev, and staging.
     *
     * Safe to run again. Re-running resets these accounts' passwords and
     * does not remove other users. Skips production and every environment
     * other than local, dev, and staging.
     *
     *   php artisan db:seed --class=LoginSeeder
     *
     * Password is "password" unless SEED_LOGIN_PASSWORD is set.
     * When config is cached, the cached value is used.
     * Facility ownership and sample returns are seeded next by FacilitySeeder,
     * then FrontlineWorkerSeeder adds the frontline worker at Karachi West BMU.
     */
    public function run(): void
    {
        if ($this->skipsOutsideLocalDevStaging()) {
            return;
        }

        $password = $this->password();

        $this->call(GeographySeeder::class);

        foreach ($this->accounts() as $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $password,
                ],
            );

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $this->ensurePersonalTeam($user);

            if ($account['programme_admin']) {
                $this->ensureProgrammeAdmin($user);
            }

            if ($account['role'] instanceof ProgrammeRole && is_string($account['scope_slug'])) {
                $this->ensureScopedAssignment($user, $account['role'], $account['scope_slug']);
            }
        }

        $this->command?->info('Login accounts ready:');

        foreach ($this->accounts() as $account) {
            $this->command?->line("  {$account['name']} <{$account['email']}>");
        }

        if ($this->usesDefaultPassword()) {
            $this->command?->comment('Sign in with password: password');
        } else {
            $this->command?->comment('Sign in with the SEED_LOGIN_PASSWORD value from the server environment.');
        }

        $this->call(FacilitySeeder::class);
        $this->call(FrontlineWorkerSeeder::class);
    }

    /**
     * @return list<array{name: string, email: string, programme_admin: bool, role: ProgrammeRole|null, scope_slug: string|null}>
     */
    private function accounts(): array
    {
        $accounts = [
            [
                'name' => 'TB-AI Admin',
                'email' => 'admin@tbai.test',
                'programme_admin' => true,
                'role' => null,
                'scope_slug' => null,
            ],
            [
                'name' => 'TB-AI Member',
                'email' => 'member@tbai.test',
                'programme_admin' => false,
                'role' => null,
                'scope_slug' => null,
            ],
            [
                'name' => 'Karachi West In-charge',
                'email' => 'incharge@tbai.test',
                'programme_admin' => false,
                'role' => null,
                'scope_slug' => null,
            ],
            [
                'name' => 'Country Viewer',
                'email' => 'country@tbai.test',
                'programme_admin' => false,
                'role' => ProgrammeRole::CountryViewer,
                'scope_slug' => 'pakistan',
            ],
            [
                'name' => 'Provincial Viewer',
                'email' => 'sindh@tbai.test',
                'programme_admin' => false,
                'role' => ProgrammeRole::ProvincialViewer,
                'scope_slug' => 'sindh',
            ],
            [
                'name' => 'Karachi West Officer',
                'email' => 'karachi-west@tbai.test',
                'programme_admin' => false,
                'role' => ProgrammeRole::CityOfficer,
                'scope_slug' => 'karachi-west',
            ],
        ];

        $configured = $this->configuredAdminEmail();

        if ($configured !== null && $configured !== 'admin@tbai.test') {
            $accounts[] = [
                'name' => 'TB-AI Admin',
                'email' => $configured,
                'programme_admin' => true,
                'role' => null,
                'scope_slug' => null,
            ];
        }

        return $accounts;
    }

    private function configuredAdminEmail(): ?string
    {
        $adminEmail = config('access.admin_email');
        $adminEmail = is_string($adminEmail) ? strtolower(trim($adminEmail)) : '';

        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $adminEmail;
    }

    private function ensurePersonalTeam(User $user): void
    {
        $group = $this->programmeGroup($user->email);
        $existing = $user->personalTeam();

        if ($existing !== null) {
            if ($group !== null && $existing->name !== $group['name']) {
                Team::withoutEvents(function () use ($existing, $group): void {
                    $existing->forceFill(['name' => $group['name']])->save();
                });
            }

            return;
        }

        $team = new Team([
            'name' => $group['name'] ?? $user->name."'s Team",
            'is_personal' => true,
        ]);

        if ($group !== null) {
            $team->slug = $group['slug'];
        }

        $team->save();

        $team->members()->attach($user, [
            'role' => TeamRole::Owner->value,
        ]);

        $user->switchTeam($team);
    }

    /**
     * Display name for a seeded programme group. The slug stays on the old address.
     *
     * @return array{name: string, slug: string}|null
     */
    private function programmeGroup(string $email): ?array
    {
        return match ($email) {
            'admin@tbai.test' => ['name' => 'TB-AI Admin group', 'slug' => 'tb-ai-admins-team'],
            'country@tbai.test' => ['name' => 'Pakistan viewers', 'slug' => 'country-viewers-team'],
            'sindh@tbai.test' => ['name' => 'Sindh viewers', 'slug' => 'provincial-viewers-team'],
            'karachi-west@tbai.test' => ['name' => 'Karachi West officers', 'slug' => 'karachi-west-officers-team'],
            default => null,
        };
    }

    /**
     * Keep an existing TB-AI admin assignment. Do not remove it.
     */
    private function ensureProgrammeAdmin(User $user): void
    {
        ProgrammeAssignment::query()->firstOrCreate([
            'user_id' => $user->id,
            'role' => ProgrammeRole::TbaiAdmin,
            'scope_type' => null,
            'scope_id' => null,
        ]);
    }

    private function ensureScopedAssignment(User $user, ProgrammeRole $role, string $slug): void
    {
        [$scopeType, $scopeId] = $this->scope($role, $slug);

        ProgrammeAssignment::query()->firstOrCreate([
            'user_id' => $user->id,
            'role' => $role,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
        ]);
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
}
