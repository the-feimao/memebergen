<?php

namespace Database\Factories;

use App\Models\MembershipGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipGrade>
 */
class MembershipGradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Graduate', 'Associate', 'Member', 'Fellow']),
            'is_active' => true,
        ];
    }
}
