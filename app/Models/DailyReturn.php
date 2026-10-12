<?php

namespace App\Models;

use Database\Factories\DailyReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int $facility_id
 * @property string $reported_on
 * @property int $submitted_by
 * @property int $suspects
 * @property int $tested
 * @property int $positive
 * @property int $negative
 * @property int $started_treatment
 * @property int $follow_up
 * @property string|null $median_tat_days
 */
#[Fillable([
    'facility_id',
    'reported_on',
    'submitted_by',
    'suspects',
    'tested',
    'positive',
    'negative',
    'started_treatment',
    'follow_up',
    'median_tat_days',
])]
class DailyReturn extends Model
{
    /** @use HasFactory<DailyReturnFactory> */
    use HasFactory;

    /**
     * Fields copied into a revision when an existing return changes.
     *
     * @var list<string>
     */
    public const TRACKED = [
        'suspects',
        'tested',
        'positive',
        'negative',
        'started_treatment',
        'follow_up',
        'median_tat_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reported_on' => 'date',
            'suspects' => 'integer',
            'tested' => 'integer',
            'positive' => 'integer',
            'negative' => 'integer',
            'started_treatment' => 'integer',
            'follow_up' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (DailyReturn $return): void {
            if (! $return->wasChanged(self::TRACKED)) {
                return;
            }

            $old = [];
            $new = [];

            foreach (self::TRACKED as $field) {
                $old[$field] = self::normalizeTracked($field, $return->getOriginal($field));
                $new[$field] = self::normalizeTracked($field, $return->getAttribute($field));
            }

            if ($old === $new) {
                return;
            }

            $changedBy = Auth::id() ?? $return->submitted_by;

            $return->revisions()->create([
                'changed_by' => $changedBy,
                'changed_at' => now(),
                'old_values' => $old,
                'new_values' => $new,
            ]);
        });
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return HasMany<SlidePhoto, $this>
     */
    public function slidePhotos(): HasMany
    {
        return $this->hasMany(SlidePhoto::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return HasMany<DailyReturnRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(DailyReturnRevision::class);
    }

    private static function normalizeTracked(string $field, mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($field === 'median_tat_days') {
            return (float) $value;
        }

        return (int) $value;
    }
}
