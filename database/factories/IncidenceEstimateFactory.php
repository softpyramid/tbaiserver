<?php

namespace Database\Factories;

use App\Enums\AreaType;
use App\Models\City;
use App\Models\IncidenceEstimate;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidenceEstimate>
 */
class IncidenceEstimateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'area_type' => AreaType::Country,
            'area_id' => null,
            'year' => (int) date('Y'),
            'rate_per_100k' => $this->faker->randomFloat(2, 50, 400),
            'lower_bound' => null,
            'upper_bound' => null,
            'estimated_cases' => null,
            'source' => 'Test data',
            'method' => 'Test method',
            'reference_url' => null,
            'entered_by' => null,
        ];
    }

    public function country(): static
    {
        return $this->state(fn (): array => [
            'area_type' => AreaType::Country,
            'area_id' => null,
        ]);
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
