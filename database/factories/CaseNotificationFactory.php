<?php

namespace Database\Factories;

use App\Models\CaseNotification;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseNotification>
 */
class CaseNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'month' => date('Y-m'),
            'new_cases' => $this->faker->numberBetween(0, 50),
            'relapse_cases' => $this->faker->numberBetween(0, 20),
            'other_previously_treated' => 0,
            'history_unknown' => 0,
            'source' => 'Test data',
        ];
    }
}
