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
     * Facility staff who work the return may open the facility home. Saving
     * stays on update. Frontline workers only screen, so they may not.
     */
    public function view(User $user, Facility $facility): bool
    {
        if ($user->isTbaiAdmin()) {
            return true;
        }

        return $user->worksReturnsAt($facility->team);
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
     * Only a TB-AI admin may take a facility out of use.
     */
    public function deactivate(User $user, Facility $facility): bool
    {
        return $user->isTbaiAdmin();
    }

    /**
     * The older flat screening feeds the daily register (its suspects show
     * on the return), so it stays with the staff who work the return.
     * Frontline workers use the suspect screening instead.
     */
    public function screen(User $user, Facility $facility): bool
    {
        return $user->worksReturnsAt($facility->team);
    }
}
