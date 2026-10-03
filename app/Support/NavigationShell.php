<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;

class NavigationShell
{
    public const FACILITY = 'facility';

    public const SIDEBAR = 'sidebar';

    /**
     * Facility staff get a bottom tab bar on small screens.
     * TB-AI admin and command-center roles keep the sidebar at every width.
     */
    public function for(?User $user, ?Team $facilityTeam): string
    {
        if ($user === null) {
            return self::SIDEBAR;
        }

        if ($user->programmeAssignments()->exists()) {
            return self::SIDEBAR;
        }

        if ($facilityTeam !== null && $user->belongsToTeam($facilityTeam)) {
            return self::FACILITY;
        }

        return self::SIDEBAR;
    }
}
