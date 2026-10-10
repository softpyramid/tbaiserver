<?php

namespace App\Models;

use App\Enums\AreaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An estimated TB incidence rate per 100,000 population, for the country, a
 * province, or a district in one year. Entered by a TB-AI admin; the seeded
 * Pakistan 2026 row is the national reference.
 *
 * @property int $id
 * @property AreaType $area_type
 * @property int|null $area_id
 * @property int $year
 * @property string $rate_per_100k
 * @property string|null $lower_bound
 * @property string|null $upper_bound
 * @property int|null $estimated_cases
 * @property string $source
 * @property string $method
 * @property string|null $reference_url
 * @property int|null $entered_by
 */
#[Fillable(['area_type', 'area_id', 'year', 'rate_per_100k', 'lower_bound', 'upper_bound', 'estimated_cases', 'source', 'method', 'reference_url', 'entered_by'])]
class IncidenceEstimate extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area_type' => AreaType::class,
            'area_id' => 'integer',
            'year' => 'integer',
            'rate_per_100k' => 'decimal:2',
            'lower_bound' => 'decimal:2',
            'upper_bound' => 'decimal:2',
            'estimated_cases' => 'integer',
        ];
    }
}
