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
     * City, provincial, and country viewers open the command center.
     * A TB-AI admin opens administration.
     * Anyone else opens their account page.
     */
    public function url(User $user): string
    {
        if ($user->isTbaiAdmin()) {
            return route('admin.home');
        }

        if ($user->programmeAssignments()->exists()) {
            return route('command.index');
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
