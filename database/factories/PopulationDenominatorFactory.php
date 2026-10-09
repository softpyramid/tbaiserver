<?php

namespace Database\Factories;

use App\Enums\AreaType;
use App\Models\City;
use App\Models\PopulationDenominator;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PopulationDenominator>
 */
class PopulationDenominatorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'area_type' => AreaType::District,
            'area_id' => City::factory(),
            'year' => (int) date('Y'),
            'population' => $this->faker->numberBetween(50000, 2000000),
            'source' => 'Test data',
            'source_year' => null,
            'method_note' => null,
            'entered_by' => null,
        ];
    }

    public function province(?int $provinceId = null): static
    {
        return $this->state(fn (): array => [
            'area_type' => AreaType::Province,
            'area_id' => $provinceId ?? Province::factory(),
        ]);
    }

    public function district(?int $cityId = null): static
    {
        return $this->state(fn (): array => [
            'area_type' => AreaType::District,
            'area_id' => $cityId ?? City::factory(),
        ]);
    }
}
