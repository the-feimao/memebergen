<?php

use App\Enums\MembershipBatchStatus;
use App\Enums\MembershipBatchType;
use App\Models\MembershipBatch;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(AccessControlSeeder::class);
});

test('guests and users without the batch permission cannot view import history', function () {
    $this->get(route('batch-history.index'))->assertRedirect(route('login'));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('batch-history.index'))
        ->assertForbidden();
});

test('editors can review batch history with row outcomes and review links', function () {
    $editor = User::factory()->create();
    $editor->assignRole('Editor');

    $batch = MembershipBatch::factory()->for($editor, 'uploadedBy')->create([
        'batch_reference' => '01JTESTBATCH00000000000001',
        'batch_type' => MembershipBatchType::NewRegistration,
        'status' => MembershipBatchStatus::Completed,
        'total_rows' => 4,
        'successful_rows' => 3,
        'failed_rows' => 1,
        'warning_rows' => 0,
        'uploaded_at' => Carbon::parse('2026-02-10 09:30:00'),
        'processed_at' => Carbon::parse('2026-02-10 09:35:00'),
    ]);

    $this->actingAs($editor)
        ->get(route('batch-history.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('batch-history/index')
            ->where('stats.total', 1)
            ->where('stats.completed', 1)
            ->where('batches.total', 1)
            ->where('batches.data.0.batch_reference', $batch->batch_reference)
            ->where('batches.data.0.uploaded_by', $editor->name)
            ->where('batches.data.0.successful_rows', 3)
            ->where('batches.data.0.failed_rows', 1)
            ->where('batches.data.0.review_url', '/membership-batches/'.$batch->batch_reference)
            ->etc());
});

test('batch history can be filtered by type status date and search', function () {
    $editor = User::factory()->create();
    $editor->assignRole('Editor');

    MembershipBatch::factory()->for($editor, 'uploadedBy')->create([
        'batch_reference' => '01JNEWBATCH000000000000001',
        'batch_type' => MembershipBatchType::NewRegistration,
        'status' => MembershipBatchStatus::ReadyForReview,
        'original_filename' => 'new-members.xlsx',
        'uploaded_at' => Carbon::parse('2026-02-10 09:30:00'),
    ]);
    MembershipBatch::factory()->for($editor, 'uploadedBy')->create([
        'batch_reference' => '01JUPGRADEBATCH00000000001',
        'batch_type' => MembershipBatchType::Upgrade,
        'status' => MembershipBatchStatus::CompletedWithErrors,
        'original_filename' => 'upgrades.xlsx',
        'uploaded_at' => Carbon::parse('2026-03-12 11:00:00'),
    ]);

    $this->actingAs($editor)
        ->get(route('batch-history.index', [
            'type' => MembershipBatchType::Upgrade->value,
            'status' => MembershipBatchStatus::CompletedWithErrors->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('batches.total', 1)
            ->where('batches.data.0.batch_reference', '01JUPGRADEBATCH00000000001')
            ->where('batches.data.0.review_url', '/upgrade-batches/01JUPGRADEBATCH00000000001')
            ->etc());

    $this->get(route('batch-history.index', [
        'from' => '2026-03-01',
        'to' => '2026-03-31',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('batches.total', 1)
            ->where('batches.data.0.original_filename', 'upgrades.xlsx')
            ->etc());

    $this->get(route('batch-history.index', ['search' => 'new-members']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('batches.total', 1)
            ->where('batches.data.0.batch_reference', '01JNEWBATCH000000000000001')
            ->etc());
});
