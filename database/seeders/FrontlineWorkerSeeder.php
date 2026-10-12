<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Enums\FrontlineDesignation;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\Concerns\ResolvesSeedLoginPassword;
use Database\Seeders\Concerns\SkipsOutsideLocalDevStaging;
use Illuminate\Database\Seeder;
use RuntimeException;

class FrontlineWorkerSeeder extends Seeder
{
    use ResolvesSeedLoginPassword;
    use SkipsOutsideLocalDevStaging;

    public const EMAIL = 'screener@tbai.test';

    public const TEAM_SLUG = 'karachi-west-bmu';

    /**
     * Demo frontline worker (team role screener, designation doctor) at
     * Karachi West BMU. Test account, no patient data.
     *
     * Safe to run on its own and to run again: creates the account once,
     * resets its password like LoginSeeder does, and adds the membership
     * only when missing (an admin's later role or designation edit stays).
     * Needs Karachi West BMU, so run LoginSeeder (which calls this) or
     * FacilitySeeder first on a fresh database. Skips production and every
     * environment other than local, dev, and staging.
     *
     *   php artisan db:seed --class=FrontlineWorkerSeeder
     *
     * Password is "password" unless SEED_LOGIN_PASSWORD is set.
     */
    public function run(): void
    {
        if ($this->skipsOutsideLocalDevStaging()) {
            return;
        }

        $team = Team::query()->where('slug', self::TEAM_SLUG)->whereHas('facility')->first();

        if (! $team instanceof Team) {
            throw new RuntimeException('Run FacilitySeeder before FrontlineWorkerSeeder. Missing Karachi West BMU.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Demo Frontline Worker',
                'password' => $this->password(),
            ],
        );

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        // Every account has a personal team, as after a normal sign-up.
        if ($user->personalTeam() === null) {
            app(CreateTeam::class)->handle($user, $user->name."'s Team", isPersonal: true);
        }

        $membership = $team->memberships()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => TeamRole::Screener, 'designation' => FrontlineDesignation::Doctor],
        );

        if ($membership->wasRecentlyCreated) {
            $user->switchTeam($team);
        }

        $this->command?->line('  Frontline worker: '.$user->email.' (Doctor) at Karachi West BMU.');
    }
}
