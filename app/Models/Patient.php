<?php

namespace App\Models;

use App\Enums\PatientGender;
use App\Support\Patients\PatientIdentifiers;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A patient at one facility. Name, CNIC and phone are encrypted at rest and
 * shown only to the facility's staff and TB-AI admins. cnic_hash (HMAC of
 * the digits) allows an exact CNIC search. Never log the decrypted values.
 *
 * @property int $id
 * @property string $uuid
 * @property int $facility_id
 * @property string $name
 * @property string|null $cnic
 * @property string|null $cnic_hash
 * @property string|null $phone
 * @property PatientGender $gender
 * @property int $age
 * @property string $register_number
 * @property bool $informed_of_data_use
 * @property Carbon|null $informed_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Facility $facility
 * @property-read Treatment|null $activeTreatment
 * @property-read Collection<int, Treatment> $treatments
 * @property-read Collection<int, SuspectAssessment> $screenings
 * @property-read Collection<int, Diagnosis> $diagnoses
 */
#[Fillable([
    'uuid', 'facility_id', 'name', 'cnic', 'phone', 'gender', 'age', 'register_number',
    'informed_of_data_use', 'informed_at', 'created_by', 'updated_by',
])]
#[Hidden(['name', 'cnic', 'cnic_hash', 'phone'])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Patient $patient): void {
            if (! is_string($patient->getAttribute('uuid')) || $patient->getAttribute('uuid') === '') {
                $patient->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (Patient $patient): void {
            if ($patient->isDirty('cnic') || ($patient->cnic !== null && $patient->cnic_hash === null)) {
                $patient->cnic_hash = $patient->cnic === null ? null : PatientIdentifiers::cnicHash($patient->cnic);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'cnic' => 'encrypted',
            'phone' => 'encrypted',
            'gender' => PatientGender::class,
            'age' => 'integer',
            'informed_of_data_use' => 'boolean',
            'informed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return HasMany<Treatment, $this>
     */
    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    /**
     * @return HasOne<Treatment, $this>
     */
    public function activeTreatment(): HasOne
    {
        return $this->hasOne(Treatment::class)->whereNull('outcome')->latestOfMany('start_date');
    }

    /**
     * @return HasMany<SuspectAssessment, $this>
     */
    public function screenings(): HasMany
    {
        return $this->hasMany(SuspectAssessment::class);
    }

    /**
     * @return HasMany<Diagnosis, $this>
     */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }
}
