<?php

namespace App\Models;

use App\Enums\DiseaseSite;
use App\Enums\RegimenCategory;
use App\Enums\RegistrationType;
use App\Enums\TreatmentOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One course of TB treatment (TB01 card, TB03 register line). Active while
 * the outcome is empty; a patient has at most one active treatment.
 *
 * @property int $id
 * @property int $patient_id
 * @property Carbon $start_date
 * @property RegimenCategory $regimen_category
 * @property RegistrationType $registration_type
 * @property DiseaseSite $site
 * @property string|null $tb03_number
 * @property TreatmentOutcome|null $outcome
 * @property Carbon|null $outcome_date
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Patient $patient
 * @property-read Collection<int, TreatmentVisit> $visits
 */
#[Fillable([
    'patient_id', 'start_date', 'regimen_category', 'registration_type', 'site', 'tb03_number',
    'outcome', 'outcome_date', 'notes', 'created_by', 'updated_by',
])]
class Treatment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'regimen_category' => RegimenCategory::class,
            'registration_type' => RegistrationType::class,
            'site' => DiseaseSite::class,
            'outcome' => TreatmentOutcome::class,
            'outcome_date' => 'date',
        ];
    }

    public function isActive(): bool
    {
        return $this->outcome === null;
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return HasMany<TreatmentVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(TreatmentVisit::class)->orderBy('month_number');
    }
}
