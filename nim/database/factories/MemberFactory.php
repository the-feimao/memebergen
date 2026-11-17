<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MembershipGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_number' => fake()->unique()->numerify('########'),
            'full_name' => fake()->name(),
            'membership_grade_id' => MembershipGrade::factory(),
            'registration_date' => fake()->optional()->date(),
        ];
    }
}
