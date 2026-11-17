<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MembershipGrade;
use App\Models\MembershipHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipHistory>
 */
class MembershipHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'previous_grade_id' => null,
            'new_grade_id' => MembershipGrade::factory(),
            'change_type' => 'registration',
            'changed_by' => null,
            'changed_at' => now(),
        ];
    }
}
