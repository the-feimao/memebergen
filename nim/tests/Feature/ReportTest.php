<?php

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MembershipGrade;
use App\Models\MembershipUpgrade;
use App\Models\UpgradeType;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\MembershipGradeSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([AccessControlSeeder::class, MembershipGradeSeeder::class]);
});

function reportEditor(): User
{
    $editor = User::factory()->create();
    $editor->assignRole('Editor');

    return $editor;
}

function reportUpgrade(array $attributes = []): MembershipUpgrade
{
    $graduate = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();
    $associate = MembershipGrade::query()->where('name', 'Associate')->firstOrFail();
    $upgradeType = UpgradeType::query()->firstOrCreate([
        'name' => 'ITMA',
    ], [
        'is_active' => true,
    ]);

    $member = Member::factory()->for($graduate, 'grade')->create(array_merge([
        'membership_number' => '00000201',
        'full_name' => 'Report Member',
        'registration_date' => '2026-02-05',
    ], $attributes['member'] ?? []));

    return MembershipUpgrade::query()->create([
        'member_id' => $member->id,
        'upgrade_type_id' => $upgradeType->id,
        'previous_grade_id' => $graduate->id,
        'new_grade_id' => $associate->id,
        'effective_date' => $attributes['effective_date'] ?? '2026-02-10',
    ]);
}

test('guests and users without register access cannot view reports', function () {
    $this->get(route('reports.index'))->assertRedirect(route('login'));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertForbidden();
});

test('editors can view filtered report summaries', function () {
    $editor = reportEditor();
    reportUpgrade(['effective_date' => '2026-02-10']);

    Member::factory()
        ->for(MembershipGrade::query()->where('name', 'Member')->firstOrFail(), 'grade')
        ->create([
            'membership_number' => '00000202',
            'full_name' => 'Outside Report Range',
            'registration_date' => '2025-01-15',
        ]);

    $this->actingAs($editor)
        ->get(route('reports.index', [
            'from' => '2026-02-01',
            'to' => '2026-02-28',
            'grade' => 'Associate',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/index')
            ->where('summary.members', 0)
            ->where('summary.upgrades', 1)
            ->where('filters.from', '2026-02-01')
            ->where('filters.grade', 'Associate')
            ->where('upgradeTypeBreakdown.0.name', 'ITMA')
            ->where('can.exportRegister', true)
            ->etc());
});

test('report exports are authorised and audited', function () {
    $editor = reportEditor();
    reportUpgrade();

    $this->actingAs($editor)
        ->get(route('reports.register.export', ['from' => '2026-02-01']))
        ->assertOk();

    $this->actingAs($editor)
        ->get(route('reports.upgrades.export', ['from' => '2026-02-01']))
        ->assertOk();

    expect(AuditLog::query()->where('action', 'reports.register_exported')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'reports.upgrades_exported')->exists())->toBeTrue();
});
