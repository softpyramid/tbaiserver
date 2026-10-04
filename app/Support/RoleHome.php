<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;

class RoleHome
{
    /**
     * Where this person should land after sign-in.
     *
     * Technicians and the facility in-charge open today's register.
     * City, provincial, and country viewers, and a TB-AI admin, open the
     * command center. When command center landing is on, that home is the
     * executive view. Anyone else opens their account page.
     */
    public function url(User $user): string
    {
        $commandHome = config('tbai.command_center.landing')
            ? route('command.center')
            : route('command.index');

        if ($user->isTbaiAdmin()) {
            return $commandHome;
        }

        if ($user->programmeAssignments()->exists()) {
            return $commandHome;
        }

        $facilityTeam = $this->facilityTeam($user);

        if ($facilityTeam !== null) {
            return route('returns.today', $facilityTeam);
        }

        return route('profile.edit');
    }

    private function facilityTeam(User $user): ?Team
    {
        $current = $user->currentTeam;
        $current?->loadMissing('facility');

        if ($current?->facility !== null && $user->belongsToTeam($current)) {
            return $current;
        }

        $team = $user->teams()->whereHas('facility')->first();

        return $team instanceof Team ? $team : null;
    }
}
