<?php

namespace App\Models;

use App\Enums\SputumResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A scheduled monthly follow-up visit. Cancelled (never deleted) when the
 * treatment closes before it; flagged as missed by tbai:flag-missed-visits.
 *
 * @property int $id
 * @property int $treatment_id
 * @property int $month_number
 * @property Carbon $scheduled_date
 * @property Carbon|null $attended_date
 * @property SputumResult|null $sputum_result
 * @property string|null $notes
 * @property Carbon|null $missed_flagged_at
 * @property Carbon|null $cancelled_at
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Treatment $treatment
 */
#[Fillable([
    'treatment_id', 'month_number', 'scheduled_date', 'attended_date', 'sputum_result', 'notes',
    'missed_flagged_at', 'cancelled_at', 'updated_by',
])]
class TreatmentVisit extends Model
{
    public const STATUS_ATTENDED = 'attended';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_MISSED = 'missed';

    public const STATUS_DUE = 'due';

    public const STATUS_UPCOMING = 'upcoming';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month_number' => 'integer',
            'scheduled_date' => 'date',
            'attended_date' => 'date',
            'sputum_result' => SputumResult::class,
            'missed_flagged_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Status on the given day (Y-m-d, programme time zone).
     */
    public function statusOn(string $today): string
    {
        $scheduled = $this->scheduled_date->toDateString();

        return match (true) {
            $this->cancelled_at !== null => self::STATUS_CANCELLED,
            $this->attended_date !== null => self::STATUS_ATTENDED,
            $scheduled < $today => self::STATUS_MISSED,
            $scheduled === $today => self::STATUS_DUE,
            default => self::STATUS_UPCOMING,
        };
    }

    /**
     * @return BelongsTo<Treatment, $this>
     */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }
}
