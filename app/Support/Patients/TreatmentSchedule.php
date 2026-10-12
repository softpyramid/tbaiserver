<?php

namespace App\Support\Patients;

use App\Enums\DiseaseSite;
use App\Enums\RegimenCategory;
use App\Enums\RegistrationType;
use App\Enums\TreatmentOutcome;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\TreatmentVisit;
use App\Models\User;
use App\Notifications\MissedVisit;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts a treatment with its monthly visits and closes it with an outcome.
 */
class TreatmentSchedule
{
    /**
     * Start a treatment and schedule visits month 1 to N, each on the start
     * date plus that many months (no overflow: 31 Jan plus one month is the
     * end of February). Refuses a second active treatment.
     *
     * @param  array{start_date: string, regimen_category: string, registration_type: string, site: string, tb03_number?: string|null, notes?: string|null}  $data
     */
    public function start(Patient $patient, array $data, User $actor): Treatment
    {
        return DB::transaction(function () use ($patient, $data, $actor): Treatment {
            Patient::query()->whereKey($patient->id)->lockForUpdate()->first();

            if ($patient->treatments()->whereNull('outcome')->exists()) {
                throw ValidationException::withMessages([
                    'start_date' => __('entry.patients_treatment_already_active'),
                ]);
            }

            $regimen = RegimenCategory::from($data['regimen_category']);
            $start = CarbonImmutable::parse($data['start_date'])->startOfDay();

            $treatment = $patient->treatments()->create([
                'start_date' => $start->toDateString(),
                'regimen_category' => $regimen,
                'registration_type' => RegistrationType::from($data['registration_type']),
                'site' => DiseaseSite::from($data['site']),
                'tb03_number' => $data['tb03_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            foreach (range(1, $regimen->visitCount()) as $month) {
                $treatment->visits()->create([
                    'month_number' => $month,
                    'scheduled_date' => $start->addMonthsNoOverflow($month)->toDateString(),
                ]);
            }

            return $treatment;
        });
    }

    /**
     * Record the outcome. Every unattended visit scheduled after the outcome
     * date is cancelled (kept, never deleted). Returns how many.
     */
    public function close(Treatment $treatment, TreatmentOutcome $outcome, string $outcomeDate, User $actor): int
    {
        return DB::transaction(function () use ($treatment, $outcome, $outcomeDate, $actor): int {
            if (! $treatment->isActive()) {
                throw ValidationException::withMessages([
                    'outcome' => __('entry.patients_treatment_already_closed'),
                ]);
            }

            $treatment->update([
                'outcome' => $outcome,
                'outcome_date' => $outcomeDate,
                'updated_by' => $actor->id,
            ]);

            return TreatmentVisit::query()
                ->where('treatment_id', $treatment->id)
                ->whereNull('attended_date')
                ->whereNull('cancelled_at')
                ->whereDate('scheduled_date', '>', $outcomeDate)
                ->update(['cancelled_at' => now(), 'updated_at' => now()]);
        });
    }

    /**
     * Once a visit is recorded as attended, its missed-visit reminders are
     * done: mark them read for everyone who got one.
     */
    public function settleReminders(TreatmentVisit $visit): void
    {
        DatabaseNotification::query()
            ->where('type', MissedVisit::class)
            ->whereNull('read_at')
            ->where('data->visit_id', $visit->id)
            ->update(['read_at' => now()]);
    }
}
