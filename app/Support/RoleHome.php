<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;

class RoleHome
{
    /**
     * Where this person should land after sign-in.
     *
     * Technicians and facility in-charge open today's register.
     * Programme viewers and TB-AI admins open the command center.
     * Anyone else opens their account page.
     */
    public function url(User $user): string
    {
        if ($user->isTbaiAdmin() || $user->programmeAssignments()->exists()) {
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
