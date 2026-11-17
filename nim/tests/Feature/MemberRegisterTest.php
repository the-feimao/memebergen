<?php

use App\Models\Member;
use App\Models\MembershipGrade;
use App\Models\MembershipHistory;
use App\Models\MembershipUpgrade;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\MembershipGradeSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('editors can search and filter the membership register', function () {
    $this->seed([AccessControlSeeder::class, MembershipGradeSeeder::class]);

    $editor = User::factory()->create();
    $editor->assignRole('Editor');

    $graduate = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();
    $associate = MembershipGrade::query()->where('name', 'Associate')->firstOrFail();

    Member::factory()->for($graduate, 'grade')->create([
        'membership_number' => '00000041',
        'full_name' => 'NIM Member One',
        'registration_date' => '2024-02-05',
    ]);
    Member::factory()->for($associate, 'grade')->create([
        'membership_number' => '00000042',
        'full_name' => 'NIM Member Two',
        'registration_date' => '2024-02-06',
    ]);
    Member::factory()->for($graduate, 'grade')->create([
        'membership_number' => '00000043',
        'full_name' => 'NIM Member Three',
        'registration_date' => '2023-12-31',
    ]);

    $this->actingAs($editor)
        ->get(route('members.index', [
            'search' => '0000004',
            'grade' => 'Graduate',
            'registration_from' => '2024-01-01',
            'registration_to' => '2024-02-28',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/index')
            ->where('members.total', 1)
            ->has('members.data', 1)
            ->where('members.data.0.membership_number', '00000041')
            ->where('members.data.0.grade.name', 'Graduate')
            ->where('filters.registration_from', '2024-01-01')
            ->etc());
});

test('editors can search members by name', function () {
    $this->seed([AccessControlSeeder::class, MembershipGradeSeeder::class]);

    $editor = User::factory()->create();
    $editor->assignRole('Editor');
    $grade = MembershipGrade::query()->where('name', 'Member')->firstOrFail();

    Member::factory()->for($grade, 'grade')->create([
        'membership_number' => '00000101',
        'full_name' => 'Amina Okafor',
    ]);
    Member::factory()->for($grade, 'grade')->create([
        'membership_number' => '00000102',
        'full_name' => 'Bola Ade',
    ]);

    $this->actingAs($editor)
        ->get(route('members.index', ['search' => 'Amina']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/index')
            ->where('members.total', 1)
            ->where('members.data.0.full_name', 'Amina Okafor')
            ->etc());
});

test('the membership register paginates results', function () {
    $this->seed([AccessControlSeeder::class, MembershipGradeSeeder::class]);

    $editor = User::factory()->create();
    $editor->assignRole('Editor');
    $grade = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();

    foreach (range(1, 16) as $number) {
        Member::factory()->for($grade, 'grade')->create([
            'membership_number' => str_pad((string) $number, 8, '0', STR_PAD_LEFT),
            'full_name' => sprintf('Member %02d', $number),
        ]);
    }

    $this->actingAs($editor)
        ->get(route('members.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/index')
            ->where('members.total', 16)
            ->where('members.current_page', 2)
            ->has('members.data', 1)
            ->etc());
});

test('editors can view a member record and its grade history', function () {
    $this->seed([AccessControlSeeder::class, MembershipGradeSeeder::class]);

    $editor = User::factory()->create();
    $editor->assignRole('Editor');
    $grade = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();

    $member = Member::factory()->for($grade, 'grade')->create([
        'membership_number' => '00000201',
        'full_name' => 'Chika Member',
        'registration_date' => '2024-01-15',
    ]);

    MembershipHistory::factory()->for($member)->create([
        'new_grade_id' => $grade->id,
        'change_type' => 'registration',
        'changed_by' => $editor->id,
    ]);

    $this->actingAs($editor)
        ->get(route('members.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/show')
            ->where('member.membership_number', '00000201')
            ->where('member.grade.name', 'Graduate')
            ->has('member.history', 1)
            ->where('member.history.0.new_grade.name', 'Graduate')
            ->where('member.history.0.changed_by.name', $editor->name)
            ->etc());
});

test('the membership register exposes registration dates', function () {
    $this->seed([AccessControlSeeder::class, MembershipGradeSeeder::class]);

    $editor = User::factory()->create();
    $editor->assignRole('Editor');
    $grade = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();

    Member::factory()->for($grade, 'grade')->create([
        'membership_number' => '00000301',
        'full_name' => 'Date Recorded',
        'registration_date' => '2024-03-09',
    ]);
    Member::factory()->for($grade, 'grade')->create([
        'membership_number' => '00000302',
        'full_name' => 'Date Missing',
        'registration_date' => null,
        'created_at' => '2025-07-14 10:00:00',
    ]);

    $this->actingAs($editor)
        ->get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/index')
            ->where('members.data.0.registration_date', '2025-07-14')
            ->where('members.data.1.registration_date', '2024-03-09')
            ->etc());
});

test('member records show the upgrade updated date as the effective date', function () {
    $this->seed([AccessControlSeeder::class, MembershipGradeSeeder::class]);

    $editor = User::factory()->create();
    $editor->assignRole('Editor');
    $graduate = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();
    $associate = MembershipGrade::query()->where('name', 'Associate')->firstOrFail();

    $member = Member::factory()->for($graduate, 'grade')->create([
        'membership_number' => '00000311',
        'full_name' => 'Upgraded Member',
        'registration_date' => '2024-01-15',
    ]);

    $upgrade = MembershipUpgrade::query()->create([
        'member_id' => $member->id,
        'previous_grade_id' => $graduate->id,
        'new_grade_id' => $associate->id,
        'processed_by' => $editor->id,
        'effective_date' => null,
    ]);

    MembershipUpgrade::query()
        ->whereKey($upgrade->getKey())
        ->update(['updated_at' => '2026-11-17 09:44:03']);

    $this->actingAs($editor)
        ->get(route('members.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/show')
            ->where('member.upgrades.0.effective_date', '2026-11-17')
            ->etc());
});

test('guests cannot visit the membership register', function () {
    $this->get(route('members.index'))
        ->assertRedirect(route('login'));
});

test('users without membership register permission are forbidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('members.index'))
        ->assertForbidden();
});
