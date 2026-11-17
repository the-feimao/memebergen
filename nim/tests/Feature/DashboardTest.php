<?php

use App\Enums\MembershipBatchStatus;
use App\Models\Member;
use App\Models\MembershipBatch;
use App\Models\MembershipGrade;
use App\Models\MembershipUpgrade;
use App\Models\UpgradeType;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\MembershipGradeSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->seed([AccessControlSeeder::class, MembershipGradeSeeder::class]);

    $editor = User::factory()->create();
    $editor->assignRole('Editor');

    $this->actingAs($editor)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('metrics.members', 0)
            ->has('latestBatches', 0)
            ->etc());
});

test('authenticated users without a role cannot visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('dashboard metrics are derived from the register and batch history', function () {
    $this->seed([AccessControlSeeder::class, MembershipGradeSeeder::class]);

    $editor = User::factory()->create();
    $editor->assignRole('Editor');
    $graduate = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();
    $associate = MembershipGrade::query()->where('name', 'Associate')->firstOrFail();

    Member::factory()->for($graduate, 'grade')->create([
        'membership_number' => '00000101',
        'registration_date' => now()->toDateString(),
    ]);
    Member::factory()->for($associate, 'grade')->create([
        'membership_number' => '00000102',
        'registration_date' => now()->subMonth()->toDateString(),
    ]);

    $upgradeType = UpgradeType::query()->create([
        'name' => 'ITMA',
        'is_active' => true,
    ]);

    MembershipUpgrade::query()->create([
        'member_id' => Member::query()->where('membership_number', '00000101')->value('id'),
        'upgrade_type_id' => $upgradeType->id,
        'previous_grade_id' => $graduate->id,
        'new_grade_id' => $associate->id,
        'processed_by' => $editor->id,
        'effective_date' => now()->toDateString(),
    ]);

    MembershipBatch::factory()->for($editor, 'uploadedBy')->create([
        'batch_reference' => '01JDASHBOARD0000000000001',
        'status' => MembershipBatchStatus::ReadyForReview,
        'uploaded_at' => Carbon::parse('2026-02-10 09:30:00'),
    ]);

    $this->actingAs($editor)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.members', 2)
            ->where('metrics.newMembersThisMonth', 1)
            ->where('metrics.upgradesThisMonth', 1)
            ->where('metrics.awaitingReview', 1)
            ->where('latestBatches.0.batch_reference', '01JDASHBOARD0000000000001')
            ->where('gradeBreakdown.0.count', 1)
            ->etc());
});
