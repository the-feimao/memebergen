<?php

namespace Database\Factories;

use App\Enums\MembershipBatchRowStatus;
use App\Models\Member;
use App\Models\MembershipBatch;
use App\Models\MembershipBatchRow;
use App\Models\MembershipGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipBatchRow>
 */
class MembershipBatchRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fullName = fake()->name();

        return [
            'membership_batch_id' => MembershipBatch::factory(),
            'row_number' => 2,
            'full_name' => $fullName,
            'grade_value' => 'Graduate',
            'normalised_name' => Member::normaliseName($fullName),
            'membership_grade_id' => MembershipGrade::query()->value('id') ?? MembershipGrade::factory(),
            'status' => MembershipBatchRowStatus::Valid,
            'validation_errors' => null,
            'validation_warnings' => null,
            'member_id' => null,
            'allocated_membership_number' => null,
        ];
    }
}
