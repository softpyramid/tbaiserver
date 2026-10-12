<?php

namespace App\Support\Patients;

use App\Models\Facility;
use App\Models\Patient;
use App\Models\Team;
use App\Models\User;
use App\Support\Suspect\SuspectAccess;

/**
 * Who may see and edit patient records. Facility staff (every facility role,
 * frontline workers included, through TeamRole::canScreen()) at their own
 * facility, and TB-AI admins at every facility. Nobody else, programme
 * viewers included, ever gets patient-level data; their requests answer
 * 404 so a URL does not reveal that a record exists.
 */
class PatientAccess
{
    public function __construct(private readonly SuspectAccess $suspects) {}

    public function isStaff(User $user, Team $team): bool
    {
        return $team->facility instanceof Facility && $user->screensAt($team);
    }

    public function canManage(User $user, Team $team): bool
    {
        return $team->facility instanceof Facility
            && ($user->isTbaiAdmin() || $user->screensAt($team));
    }

    public function canSee(User $user, Patient $patient): bool
    {
        if ($user->isTbaiAdmin()) {
            return true;
        }

        $team = $patient->facility->team;

        return $user->screensAt($team);
    }

    /**
     * The facility behind the URL when this user may manage its patients.
     * Anyone else gets a 404.
     */
    public function facility(?User $user, Team $team): Facility
    {
        abort_unless($user instanceof User && $this->canManage($user, $team), 404);

        $facility = $team->facility;
        abort_unless($facility instanceof Facility, 404);

        return $facility;
    }

    /**
     * Staff at any facility: they get the Patients link and the reminder bell.
     */
    public function isStaffAnywhere(User $user): bool
    {
        return $this->suspects->isStaffAnywhere($user);
    }
}
