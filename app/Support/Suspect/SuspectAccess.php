<?php

namespace App\Support\Suspect;

use App\Models\Facility;
use App\Models\SuspectAssessment;
use App\Models\Team;
use App\Models\User;

/**
 * Who may do what with suspect screenings. Facility staff (in-charge,
 * senior technician, technician) screen at their own facility. A TB-AI
 * admin may read any screening. Programme viewers screen nothing.
 */
class SuspectAccess
{
    /**
     * The facility of this team when the user is on its staff.
     */
    public function staffFacility(User $user, ?Team $team): ?Facility
    {
        if ($team === null || $team->is_personal) {
            return null;
        }

        $facility = $team->facility;

        if (! $facility instanceof Facility || ! $user->belongsToTeam($team)) {
            return null;
        }

        return $facility;
    }

    public function isStaffAnywhere(User $user): bool
    {
        return $user->teams()->whereHas('facility')->exists();
    }

    public function isStaff(User $user, SuspectAssessment $assessment): bool
    {
        $team = $assessment->facility->team;

        return $user->belongsToTeam($team);
    }

    public function canRead(User $user, SuspectAssessment $assessment): bool
    {
        return $user->isTbaiAdmin() || $this->isStaff($user, $assessment);
    }
}
