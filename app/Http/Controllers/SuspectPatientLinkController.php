<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\SuspectAssessment;
use App\Models\Team;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Patients\PatientAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Link a suspect screening to an existing patient at the same facility,
 * found by register number.
 */
class SuspectPatientLinkController extends Controller
{
    public function __invoke(Request $request, Team $current_team, SuspectAssessment $assessment, PatientAccess $access, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $facility = $access->facility($user, $current_team);
        abort_unless($user instanceof User && $assessment->facility_id === $facility->id, 404);

        $data = $request->validate([
            'register_number' => ['required', 'string', 'max:50'],
        ]);

        $patient = Patient::query()
            ->where('facility_id', $facility->id)
            ->where('register_number', trim((string) $data['register_number']))
            ->first();

        if (! $patient instanceof Patient) {
            throw ValidationException::withMessages(['register_number' => __('entry.patients_link_not_found')]);
        }

        $assessment->update(['patient_id' => $patient->id]);

        $audit->write($user, 'patient.screening_linked', $patient, [
            'screening' => $assessment->uuid,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('entry.patients_linked')]);

        return redirect()->route('suspect.show', ['current_team' => $current_team->slug, 'assessment' => $assessment->uuid]);
    }
}
