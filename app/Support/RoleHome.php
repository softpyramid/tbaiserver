<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;

class RoleHome
{
    /**
     * Where this person should land after sign-in.
     *
     * A TB-AI admin opens the facilities list (light and paginated; the
     * command center stays one click away in the sidebar). City, provincial,
     * and country viewers open the command center; when command center
     * landing is on, that home is the executive view. Technicians and the
     * facility in-charge open today's register. A frontline worker opens the
     * facility's suspect screening list (their account page when suspect
     * screening is switched off). Anyone else opens their account page.
     *
     * This is the single source for every "home": sign-in responses, the
     * signed-in redirect away from guest pages such as /login, the shared
     * homeUrl prop (logo and home links) and error pages.
     */
    public function url(User $user): string
    {
        if ($user->isTbaiAdmin()) {
            return route('facilities.index');
        }

        $commandHome = config('tbai.command_center.landing')
            ? route('command.center')
            : route('command.index');

        if ($user->programmeAssignments()->exists()) {
            return $commandHome;
        }

        $facilityTeam = $this->facilityTeam($user);

        if ($facilityTeam !== null) {
            if ($user->worksReturnsAt($facilityTeam)) {
                return route('returns.today', $facilityTeam);
            }

            if (config('tbai.features.suspect_screening') && $user->screensAt($facilityTeam)) {
                return route('suspect.index', $facilityTeam);
            }
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
