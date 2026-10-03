<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $daily_return_id
 * @property int $changed_by
 * @property Carbon $changed_at
 * @property array<string, int|float|null> $old_values
 * @property array<string, int|float|null> $new_values
 * @property-read User $editor
 */
#[Fillable(['daily_return_id', 'changed_by', 'changed_at', 'old_values', 'new_values'])]
class DailyReturnRevision extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DailyReturn, $this>
     */
    public function dailyReturn(): BelongsTo
    {
        return $this->belongsTo(DailyReturn::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
