<?php

namespace App\Support\Suspect;

use App\Enums\TeamRole;
use App\Models\Facility;
use App\Models\SuspectAssessment;
use App\Models\Team;
use App\Models\User;

/**
 * Who may do what with suspect screenings. Facility staff (in-charge,
 * senior technician, technician, and frontline worker) screen at their own
 * facility; the role check is TeamRole::canScreen(). A TB-AI admin may read
 * any screening. Programme viewers screen nothing.
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

        if (! $facility instanceof Facility || ! $user->screensAt($team)) {
            return null;
        }

        return $facility;
    }

    public function isStaffAnywhere(User $user): bool
    {
        $roles = array_map(
            fn (TeamRole $role): string => $role->value,
            array_values(array_filter(TeamRole::cases(), fn (TeamRole $role): bool => $role->canScreen())),
        );

        return $user->teams()
            ->whereHas('facility')
            ->wherePivotIn('role', $roles)
            ->exists();
    }

    public function isStaff(User $user, SuspectAssessment $assessment): bool
    {
        $team = $assessment->facility->team;

        return $user->screensAt($team);
    }

    public function canRead(User $user, SuspectAssessment $assessment): bool
    {
        return $user->isTbaiAdmin() || $this->isStaff($user, $assessment);
    }
}
