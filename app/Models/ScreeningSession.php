<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $facility_id
 * @property int $user_id
 * @property Carbon $occurred_on
 * @property int $score
 * @property bool $is_suspect
 * @property-read Facility $facility
 */
#[Fillable(['facility_id', 'user_id', 'occurred_on', 'answers', 'score', 'is_suspect'])]
class ScreeningSession extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'answers' => 'array',
            'is_suspect' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
