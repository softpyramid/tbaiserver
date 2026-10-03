<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $facility_id
 * @property int $user_id
 * @property bool $is_suspect
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
}
