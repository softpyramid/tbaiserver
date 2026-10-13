<?php

namespace App\Policies;

use App\Models\Diagnosis;
use App\Models\User;
use App\Support\Patients\PatientAccess;

class DiagnosisPolicy
{
    public function __construct(private readonly PatientAccess $access) {}

    /**
     * Same people as the patient record: TB-AI admins, and staff of the
     * patient's facility (frontline workers included).
     */
    public function view(User $user, Diagnosis $diagnosis): bool
    {
        return $this->access->canSee($user, $diagnosis->patient);
    }
}
