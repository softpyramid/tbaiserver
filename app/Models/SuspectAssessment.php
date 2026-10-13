<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One suspect screening: the patient's name and register number (named
 * screenings, shown only to the facility's staff and TB-AI admins), age band,
 * sex and answers. No phone number, CNIC, national ID or MR number. Scores, priority and flags are stored when completed and are never
 * recomputed with a newer rule set.
 *
 * @property int $id
 * @property string $uuid
 * @property int $facility_id
 * @property int|null $city_id
 * @property int|null $user_id
 * @property string|null $patient_name
 * @property string|null $register_number
 * @property int|null $patient_id
 * @property string|null $worker_role
 * @property string $age_band
 * @property string $sex
 * @property int $rule_set_id
 * @property string $engine_version
 * @property string $status
 * @property int $symptom
 * @property int $exposure
 * @property int $history
 * @property int $raw_score
 * @property int $score
 * @property string|null $priority
 * @property bool $refer_for_testing
 * @property string|null $recommended_next_action
 * @property int $unknown_count
 * @property list<string>|null $unknown_items
 * @property list<string>|null $reason_codes
 * @property string|null $criterion_status
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Facility $facility
 * @property-read Patient|null $patient
 * @property-read SuspectRuleSet $ruleSet
 * @property-read Collection<int, SuspectAnswer> $answers
 * @property-read Collection<int, SuspectAssessmentFlag> $flags
 */
#[Fillable([
    'uuid', 'facility_id', 'city_id', 'user_id', 'patient_name', 'register_number', 'patient_id', 'worker_role', 'age_band', 'sex', 'rule_set_id',
    'engine_version', 'status', 'symptom', 'exposure', 'history', 'raw_score', 'score', 'priority',
    'refer_for_testing', 'recommended_next_action', 'unknown_count', 'unknown_items', 'reason_codes', 'criterion_status', 'completed_at',
])]
class SuspectAssessment extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    /**
     * @var list<string>
     */
    public const AGE_BANDS = ['0_14', '15_24', '25_44', '45_64', '65_plus', 'unknown'];

    /**
     * @var list<string>
     */
    public const SEXES = ['female', 'male', 'other', 'unknown'];

    protected static function booted(): void
    {
        static::creating(function (SuspectAssessment $assessment): void {
            if (! is_string($assessment->getAttribute('uuid')) || $assessment->getAttribute('uuid') === '') {
                $assessment->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'symptom' => 'integer',
            'exposure' => 'integer',
            'history' => 'integer',
            'raw_score' => 'integer',
            'score' => 'integer',
            'refer_for_testing' => 'boolean',
            'unknown_count' => 'integer',
            'unknown_items' => 'array',
            'reason_codes' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Was this screening scored with a rule set that was not yet published
     * when it started? True for a draft or pending set, an approved set that
     * was never published, and a set published only after this screening.
     */
    public function usedDraftRuleSet(): bool
    {
        $publishedAt = $this->ruleSet->published_at;

        return $publishedAt === null
            || ($this->created_at !== null && $publishedAt->greaterThan($this->created_at));
    }

    /**
     * Answers keyed by question key.
     *
     * @return array<string, string>
     */
    public function answerMap(): array
    {
        $map = [];

        foreach ($this->answers()->orderBy('id')->get() as $answer) {
            $map[$answer->question_key] = $answer->value;
        }

        return $map;
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * The patient record this screening is linked to, at the same facility.
     *
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<SuspectRuleSet, $this>
     */
    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(SuspectRuleSet::class, 'rule_set_id');
    }

    /**
     * @return HasMany<SuspectAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(SuspectAnswer::class, 'assessment_id');
    }

    /**
     * @return HasMany<SuspectAssessmentFlag, $this>
     */
    public function flags(): HasMany
    {
        return $this->hasMany(SuspectAssessmentFlag::class, 'assessment_id');
    }
}
