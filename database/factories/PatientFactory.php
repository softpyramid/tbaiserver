<?php

namespace Database\Factories;

use App\Enums\PatientGender;
use App\Models\Facility;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Obviously fake patients for tests: "Test Patient" names, CNICs starting
 * 00000 and no phone number unless a test sets one.
 *
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'name' => 'Test Patient '.fake()->unique()->numberBetween(1, 99999),
            'cnic' => '00000'.fake()->numerify('########'),
            'phone' => null,
            'gender' => PatientGender::Female,
            'age' => 30,
            'register_number' => 'TEST-'.fake()->unique()->numerify('#####'),
            'informed_of_data_use' => true,
            'informed_at' => now(),
        ];
    }
}
