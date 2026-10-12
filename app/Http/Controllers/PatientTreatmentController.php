<?php

namespace App\Http\Controllers;

use App\Enums\DiseaseSite;
use App\Enums\RegimenCategory;
use App\Enums\RegistrationType;
use App\Enums\SputumResult;
use App\Enums\TreatmentOutcome;
use App\Models\Patient;
use App\Models\Team;
use App\Models\Treatment;
use App\Models\TreatmentVisit;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\EntryDate;
use App\Support\Patients\PatientAccess;
use App\Support\Patients\TreatmentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Treatment start (with its monthly visit schedule), outcome, and visit
 * records for one patient. Same access as the patient record.
 */
class PatientTreatmentController extends Controller
{
    public function __construct(
        private readonly PatientAccess $access,
        private readonly TreatmentSchedule $schedule,
    ) {}

    public function store(Request $request, Team $current_team, Patient $patient, AuditLogger $audit): RedirectResponse
    {
        $user = $this->owned($request, $current_team, $patient);

        /** @var array{start_date: string, regimen_category: string, registration_type: string, site: string, tb03_number?: string|null, notes?: string|null} $data */
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.EntryDate::now()->toDateString()],
            'regimen_category' => ['required', 'string', Rule::enum(RegimenCategory::class)],
            'registration_type' => ['required', 'string', Rule::enum(RegistrationType::class)],
            'site' => ['required', 'string', Rule::enum(DiseaseSite::class)],
            'tb03_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $treatment = $this->schedule->start($patient, $data, $user);

        $audit->write($user, 'treatment.started', $patient, [
            'treatment_id' => $treatment->id,
            'regimen_category' => $treatment->regimen_category->value,
            'visits' => $treatment->visits()->count(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('entry.patients_treatment_started')]);

        return $this->back($current_team, $patient);
    }

    public function outcome(Request $request, Team $current_team, Patient $patient, Treatment $treatment, AuditLogger $audit): RedirectResponse
    {
        $user = $this->owned($request, $current_team, $patient);
        abort_unless($treatment->patient_id === $patient->id, 404);

        $data = $request->validate([
            'outcome' => ['required', 'string', Rule::enum(TreatmentOutcome::class)],
            'outcome_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$treatment->start_date->toDateString(),
                'before_or_equal:'.EntryDate::now()->toDateString(),
            ],
        ]);

        $outcome = TreatmentOutcome::from((string) $data['outcome']);
        $cancelled = $this->schedule->close($treatment, $outcome, (string) $data['outcome_date'], $user);

        $audit->write($user, 'treatment.outcome_recorded', $patient, [
            'treatment_id' => $treatment->id,
            'outcome' => $outcome->value,
            'cancelled_visits' => $cancelled,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('entry.patients_outcome_saved')]);

        return $this->back($current_team, $patient);
    }

    public function updateVisit(Request $request, Team $current_team, Patient $patient, TreatmentVisit $visit, AuditLogger $audit): RedirectResponse
    {
        $user = $this->owned($request, $current_team, $patient);
        $treatment = $visit->treatment;
        abort_unless($treatment->patient_id === $patient->id, 404);

        if ($visit->cancelled_at !== null) {
            throw ValidationException::withMessages(['attended_date' => __('entry.patients_visit_cancelled')]);
        }

        $data = $request->validate([
            'attended_date' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:'.$treatment->start_date->toDateString(),
                'before_or_equal:'.EntryDate::now()->toDateString(),
            ],
            'sputum_result' => ['nullable', 'string', Rule::enum(SputumResult::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $visit->fill([
            'attended_date' => $data['attended_date'] ?? null,
            'sputum_result' => $data['sputum_result'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $fields = array_keys($visit->getDirty());

        if ($fields !== []) {
            $visit->updated_by = $user->id;
            $visit->save();

            $audit->write($user, 'treatment_visit.updated', $patient, [
                'treatment_id' => $treatment->id,
                'visit_id' => $visit->id,
                'month' => $visit->month_number,
                'fields' => $fields,
            ]);
        }

        if ($visit->attended_date !== null) {
            $this->schedule->settleReminders($visit);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('entry.patients_visit_saved')]);

        return $this->back($current_team, $patient);
    }

    private function owned(Request $request, Team $team, Patient $patient): User
    {
        $user = $request->user();
        $facility = $this->access->facility($user, $team);
        abort_unless($user instanceof User && $patient->facility_id === $facility->id, 404);

        return $user;
    }

    private function back(Team $team, Patient $patient): RedirectResponse
    {
        return redirect()->route('patients.show', ['current_team' => $team->slug, 'patient' => $patient->uuid]);
    }
}
