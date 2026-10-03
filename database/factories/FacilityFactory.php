<?php

namespace Database\Factories;

use App\Enums\FacilityKind;
use App\Enums\FacilityStatus;
use App\Enums\Sector;
use App\Models\City;
use App\Models\Facility;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'city_id' => City::factory(),
            'sector' => Sector::Public,
            'kind' => FacilityKind::Bmu,
            'status' => FacilityStatus::Active,
        ];
    }
}
