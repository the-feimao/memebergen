<?php

namespace Database\Seeders;

use App\Models\MembershipGrade;
use Illuminate\Database\Seeder;

class MembershipGradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Graduate', 'Associate', 'Member', 'Fellow'] as $name) {
            MembershipGrade::query()->firstOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }
    }
}
