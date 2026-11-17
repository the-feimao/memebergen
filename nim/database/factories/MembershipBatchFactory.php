<?php

namespace Database\Factories;

use App\Models\MembershipBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MembershipBatch>
 */
class MembershipBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_reference' => (string) Str::ulid(),
            'batch_type' => 'new_registration',
            'uploaded_by' => User::factory(),
            'original_filename' => 'members.xlsx',
            'original_file_path' => 'membership-imports/members.xlsx',
            'file_sha256' => hash('sha256', fake()->uuid()),
            'status' => 'ready_for_review',
            'detected_headings' => ['full_name', 'grade'],
            'total_rows' => 0,
            'valid_rows' => 0,
            'failed_rows' => 0,
            'warning_rows' => 0,
            'successful_rows' => 0,
            'uploaded_at' => now(),
        ];
    }
}
