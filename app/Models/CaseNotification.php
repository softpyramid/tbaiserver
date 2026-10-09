<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * New and relapse cases a facility notified in one month. Aggregate counts
 * only; no patient identifier is stored.
 *
 * @property int $id
 * @property int $facility_id
 * @property string $month
 * @property int $new_cases
 * @property int $relapse_cases
 * @property int $other_previously_treated
 * @property int $history_unknown
 * @property string $source
 */
#[Fillable(['facility_id', 'month', 'new_cases', 'relapse_cases', 'other_previously_treated', 'history_unknown', 'source'])]
class CaseNotification extends Model
{
    use HasFactory;

    /**
     * The cases a notification rate counts: new plus relapse only.
     */
    public function countedCases(): int
    {
        return $this->new_cases + $this->relapse_cases;
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'facility_id' => 'integer',
            'new_cases' => 'integer',
            'relapse_cases' => 'integer',
            'other_previously_treated' => 'integer',
            'history_unknown' => 'integer',
        ];
    }
}
