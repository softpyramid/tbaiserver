<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One answer, stored exactly as given (unknown and not_disclosed included).
 *
 * @property int $id
 * @property int $assessment_id
 * @property string $question_key
 * @property string $value
 * @property Carbon $answered_at
 */
#[Fillable(['assessment_id', 'question_key', 'value', 'answered_at'])]
class SuspectAnswer extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SuspectAssessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(SuspectAssessment::class, 'assessment_id');
    }
}
