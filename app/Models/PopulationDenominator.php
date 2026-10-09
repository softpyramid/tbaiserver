<?php

namespace App\Models;

use App\Enums\AreaType;
use App\Enums\PopulationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The population a notification rate is measured against, for a district or a
 * province in one year. Entered by a TB-AI admin; never derived from returns.
 *
 * A confirmed row has an audited population figure (the PBS Census 2023 row).
 * A placeholder row has none yet, so it never feeds a rate; an admin fills the
 * figure in and the row becomes confirmed.
 *
 * @property int $id
 * @property AreaType $area_type
 * @property int $area_id
 * @property int $year
 * @property int|null $population
 * @property PopulationStatus $status
 * @property string $source
 * @property int|null $source_year
 * @property string|null $reference_url
 * @property string|null $method_note
 * @property int|null $entered_by
 */
#[Fillable(['area_type', 'area_id', 'year', 'population', 'status', 'source', 'source_year', 'reference_url', 'method_note', 'entered_by'])]
class PopulationDenominator extends Model
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
            'population' => 'integer',
            'status' => PopulationStatus::class,
            'source_year' => 'integer',
        ];
    }
}
