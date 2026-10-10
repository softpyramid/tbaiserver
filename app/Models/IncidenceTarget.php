<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yearly provincial planning number. Not calculated from daily returns.
 *
 * @property int $id
 * @property int $province_id
 * @property int $year
 * @property int|null $month
 * @property string $value
 * @property string|null $source
 */
#[Fillable(['province_id', 'year', 'month', 'value', 'source'])]
class IncidenceTarget extends Model
{
    public const MIN_YEAR = 2026;

    public const MAX_YEAR = 2030;

    /**
     * @return list<int>
     */
    public static function years(): array
    {
        return range(self::MIN_YEAR, self::MAX_YEAR);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'value' => 'decimal:3',
        ];
    }
}
