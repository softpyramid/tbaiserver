<?php

namespace Database\Seeders;

use App\Enums\FacilityKind;
use App\Enums\FacilityStatus;
use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\Facility;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class FacilitySeeder extends Seeder
{
    /**
     * Seed one public BMU so today's register can be opened.
     *
     * Safe to run again. Requires the login accounts from LoginSeeder.
     * Geography is seeded first. The admin is the facility in-charge and
     * the member account is a technician. Both accounts switch to this team.
     *
     *   php artisan db:seed --class=FacilitySeeder
     */
    public function run(): void
    {
        $admin = $this->user($this->adminEmail(), 'LoginSeeder');
        $member = $this->user('member@tbai.test', 'LoginSeeder');

        $this->call(GeographySeeder::class);

        $city = City::query()->where('slug', 'karachi-west')->first();

        if ($city === null) {
            throw new RuntimeException('Missing city [karachi-west] for facility seeding.');
        }

        $team = Team::query()->firstOrCreate(
            ['slug' => 'karachi-west-bmu'],
            [
                'name' => 'Karachi West BMU',
                'is_personal' => false,
            ],
        );

        Facility::query()->firstOrCreate(
            ['team_id' => $team->id],
            [
                'city_id' => $city->id,
                'sector' => Sector::Public,
                'kind' => FacilityKind::Bmu,
                'status' => FacilityStatus::Active,
                'latitude' => 25.0,
                'longitude' => 66.9,
            ],
        );

        $this->ensureMember($team, $admin, TeamRole::Owner);
        $this->ensureMember($team, $member, TeamRole::Member);

        $admin->switchTeam($team);
        $member->switchTeam($team);

        $this->command?->info('Facility ready: Karachi West BMU <karachi-west-bmu>.');
        $this->command?->line('  In-charge: '.$admin->email);
        $this->command?->line('  Technician: '.$member->email);
    }

    private function adminEmail(): string
    {
        $adminEmail = config('access.admin_email');
        $adminEmail = is_string($adminEmail) ? strtolower(trim($adminEmail)) : '';

        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            return 'admin@tbai.test';
        }

        return $adminEmail;
    }

    private function user(string $email, string $seeder): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw new RuntimeException("Run {$seeder} before seeding the facility. Missing {$email}.");
        }

        return $user;
    }

    private function ensureMember(Team $team, User $user, TeamRole $role): void
    {
        if ($team->members()->whereKey($user->id)->exists()) {
            return;
        }

        $team->members()->attach($user->id, [
            'role' => $role->value,
        ]);
    }
}
