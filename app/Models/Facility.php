<?php

namespace App\Models;

use App\Enums\FacilityKind;
use App\Enums\FacilityStatus;
use App\Enums\Sector;
use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property int $city_id
 * @property int|null $taluka_id
 * @property Sector $sector
 * @property FacilityKind $kind
 * @property string|null $code
 * @property FacilityStatus $status
 * @property-read Team $team
 * @property-read City $city
 */
#[Fillable(['team_id', 'city_id', 'taluka_id', 'sector', 'kind', 'code', 'status', 'latitude', 'longitude'])]
class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sector' => Sector::class,
            'kind' => FacilityKind::class,
            'status' => FacilityStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return HasMany<DailyReturn, $this>
     */
    public function dailyReturns(): HasMany
    {
        return $this->hasMany(DailyReturn::class);
    }
}
