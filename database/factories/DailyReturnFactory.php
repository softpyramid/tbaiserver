<?php

namespace Database\Factories;

use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyReturn>
 */
class DailyReturnFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tested = fake()->numberBetween(1, 20);
        $positive = fake()->numberBetween(0, $tested);

        return [
            'facility_id' => Facility::factory(),
            'reported_on' => now()->toDateString(),
            'submitted_by' => User::factory(),
            'suspects' => fake()->numberBetween($tested, $tested + 10),
            'tested' => $tested,
            'positive' => $positive,
            'negative' => $tested - $positive,
            'started_treatment' => fake()->numberBetween(0, $positive),
            'follow_up' => fake()->numberBetween(0, 5),
            'median_tat_days' => null,
        ];
    }
}
