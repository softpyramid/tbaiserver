<?php

namespace App\Support\Patients;

use App\Enums\DiseaseSite;
use App\Enums\PatientGender;
use App\Enums\RegimenCategory;
use App\Enums\RegistrationType;
use App\Enums\SputumResult;
use App\Enums\TreatmentOutcome;
use App\Models\Patient;
use App\Models\SuspectAssessment;
use App\Models\Team;
use App\Models\Treatment;
use App\Models\TreatmentVisit;
use App\Support\EntryDate;
use App\Support\ResourceResponse;

/**
 * Page props for patient records. Only call it for a user who may see the
 * patient (PatientAccess): the payload carries the decrypted identifiers.
 */
class PatientPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function details(Patient $patient): array
    {
        return [
            'id' => $patient->uuid,
            'name' => $patient->name,
            'cnic' => PatientIdentifiers::formatCnic($patient->cnic),
            'phone' => $patient->phone,
            'gender' => $patient->gender->value,
            'genderLabel' => $patient->gender->label(),
            'age' => $patient->age,
            'registerNumber' => $patient->register_number,
            'informedAt' => $patient->informed_at === null ? null : EntryDate::dateTime($patient->informed_at),
        ];
    }

    /**
     * Short treatment status for a list row.
     *
     * @return array{status: string, missed: int}
     */
    public function listStatus(Patient $patient, string $today): array
    {
        $active = $patient->activeTreatment;

        if ($active instanceof Treatment) {
            $visits = $active->visits;
            $attended = $visits->filter(fn (TreatmentVisit $visit): bool => $visit->attended_date !== null)->count();
            $missed = $visits->filter(fn (TreatmentVisit $visit): bool => $visit->statusOn($today) === TreatmentVisit::STATUS_MISSED)->count();

            return [
                'status' => __('entry.patients_status_on_treatment', [
                    'attended' => $attended,
                    'total' => $visits->count(),
                ]),
                'missed' => $missed,
            ];
        }

        $last = $patient->treatments->sortByDesc('start_date')->first();

        return [
            'status' => $last instanceof Treatment && $last->outcome !== null
                ? $last->outcome->label()
                : __('entry.patients_status_not_on_treatment'),
            'missed' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function treatment(Treatment $treatment, Patient $patient, Team $team, string $today): array
    {
        return [
            'id' => $treatment->id,
            'active' => $treatment->isActive(),
            'startDate' => EntryDate::display($treatment->start_date),
            'startDateIso' => $treatment->start_date->toDateString(),
            'regimenLabel' => $treatment->regimen_category->label(),
            'registrationTypeLabel' => $treatment->registration_type->label(),
            'siteLabel' => $treatment->site->label(),
            'tb03Number' => $treatment->tb03_number,
            'notes' => $treatment->notes,
            'outcomeLabel' => $treatment->outcome?->label(),
            'outcomeDate' => $treatment->outcome_date === null ? null : EntryDate::display($treatment->outcome_date),
            'outcomeUrl' => route('patients.treatments.outcome', [
                'current_team' => $team->slug,
                'patient' => $patient->uuid,
                'treatment' => $treatment->id,
            ]),
            'visits' => $treatment->visits->map(fn (TreatmentVisit $visit): array => $this->visit($visit, $patient, $team, $today))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function visit(TreatmentVisit $visit, Patient $patient, Team $team, string $today): array
    {
        $status = $visit->statusOn($today);

        return [
            'id' => $visit->id,
            'month' => $visit->month_number,
            'scheduledDate' => EntryDate::display($visit->scheduled_date),
            'attendedDate' => $visit->attended_date?->toDateString(),
            'attendedLabel' => $visit->attended_date === null ? null : EntryDate::display($visit->attended_date),
            'sputumResult' => $visit->sputum_result?->value,
            'sputumLabel' => $visit->sputum_result?->label(),
            'notes' => $visit->notes,
            'status' => $status,
            'statusLabel' => ResourceResponse::line('entry.patients_visit_status_'.$status),
            'editable' => $status !== TreatmentVisit::STATUS_CANCELLED,
            'updateUrl' => route('patients.visits.update', [
                'current_team' => $team->slug,
                'patient' => $patient->uuid,
                'visit' => $visit->id,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function screening(SuspectAssessment $assessment, Team $team): array
    {
        return [
            'id' => $assessment->uuid,
            'date' => $assessment->created_at === null ? '' : EntryDate::dateTime($assessment->created_at),
            'completed' => $assessment->isCompleted(),
            'score' => $assessment->isCompleted() ? $assessment->score : null,
            'priorityLabel' => $assessment->priority === null ? null : ResourceResponse::line('entry.suspect_priority_'.$assessment->priority),
            'refer' => $assessment->refer_for_testing,
            'href' => route('suspect.show', ['current_team' => $team->slug, 'assessment' => $assessment->uuid]),
        ];
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    public function options(): array
    {
        return [
            'genders' => PatientGender::options(),
            'regimens' => RegimenCategory::options(),
            'registrationTypes' => RegistrationType::options(),
            'sites' => DiseaseSite::options(),
            'outcomes' => TreatmentOutcome::options(),
            'sputumResults' => SputumResult::options(),
        ];
    }
}
