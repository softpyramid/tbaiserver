<?php

namespace Database\Factories;

use App\Enums\ProgrammeRole;
use App\Models\ProgrammeAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgrammeAssignment>
 */
class ProgrammeAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => ProgrammeRole::TbaiAdmin,
            'scope_type' => null,
            'scope_id' => null,
        ];
    }
}
