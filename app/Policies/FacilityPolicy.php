<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Facility;
use App\Models\User;

class FacilityPolicy
{
    /**
     * The facilities list is a TB-AI admin setup screen.
     */
    public function viewAny(User $user): bool
    {
        return $user->isTbaiAdmin();
    }

    /**
     * Facility staff may open the facility home. Saving stays on update.
     */
    public function view(User $user, Facility $facility): bool
    {
        if ($user->isTbaiAdmin()) {
            return true;
        }

        return $user->belongsToTeam($facility->team);
    }

    /**
     * The facility profile tab is for the in-charge, senior technician, and TB-AI admin.
     */
    public function openProfile(User $user, Facility $facility): bool
    {
        if ($user->isTbaiAdmin()) {
            return true;
        }

        $role = $user->teamRole($facility->team);

        return $role === TeamRole::Owner || $role === TeamRole::Admin;
    }

    /**
     * Creating a facility is a TB-AI admin action. There is no public signup path.
     */
    public function create(User $user): bool
    {
        return $user->isTbaiAdmin();
    }

    /**
     * The facility in-charge (team owner) and a TB-AI admin may edit the profile.
     * Technicians, senior technicians, and programme viewers may not.
     */
    public function update(User $user, Facility $facility): bool
    {
        if ($user->isTbaiAdmin()) {
            return true;
        }

        return $user->teamRole($facility->team) === TeamRole::Owner;
    }

    /**
     * Any member of the facility team may record a screening.
     */
    public function screen(User $user, Facility $facility): bool
    {
        return $user->belongsToTeam($facility->team);
    }
}
