<?php

use App\Enums\MembershipBatchStatus;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MembershipBatch;
use App\Models\MembershipGrade;
use App\Models\User;
use App\Services\MembershipNumberAllocator;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\MembershipGradeSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed([
        AccessControlSeeder::class,
        MembershipGradeSeeder::class,
    ]);

    Storage::fake('local');
});

function newMembershipRuleWorkbook(array $headings, array $rows): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([$headings, ...$rows], null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'nim-membership-rules-');

    if ($path === false) {
        throw new RuntimeException('Unable to create a temporary workbook.');
    }

    try {
        (new Xlsx($spreadsheet))->save($path);
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('Unable to read the temporary workbook.');
        }

        return $content;
    } finally {
        $spreadsheet->disconnectWorksheets();
        unlink($path);
    }
}

function newMembershipRuleUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('Editor');

    return $user;
}

test('it preserves new supplied membership numbers and rejects existing or duplicate ones', function () {
    $grade = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();

    Member::factory()->create([
        'membership_number' => '267117',
        'full_name' => 'Existing Member',
        'email' => 'existing@example.com',
        'membership_grade_id' => $grade->id,
    ]);

    $content = newMembershipRuleWorkbook(
        ['Full Name', 'Grade', 'Membership Number'],
        [
            ['Alice Smith', 'Graduate', '267118'],
            ['Bob Brown', 'Graduate', '267117'],
            ['Cara Cole', 'Graduate', '267119'],
            ['Dan Dole', 'Graduate', '267119'],
        ],
    );

    $response = $this->actingAs(newMembershipRuleUser())
        ->post(route('membership-batches.store'), [
            'file' => UploadedFile::fake()->createWithContent('members.xlsx', $content),
        ]);

    $batch = MembershipBatch::query()->sole();

    $response->assertRedirect(route('membership-batches.show', $batch));
    expect($batch->status)->toBe(MembershipBatchStatus::ReadyForReview)
        ->and($batch->valid_rows)->toBe(1)
        ->and($batch->failed_rows)->toBe(3);

    $rows = $batch->rows()->orderBy('row_number')->get();

    expect($rows[0]->supplied_membership_number)->toBe('267118')
        ->and($rows[0]->validation_errors)->toBeNull()
        ->and($rows[1]->validation_errors)->toContain('Membership number 267117 is already assigned to an existing member.')
        ->and($rows[2]->validation_errors)->toContain('This membership number appears more than once in the uploaded file.')
        ->and($rows[3]->validation_errors)->toContain('This membership number appears more than once in the uploaded file.');

    $this->get(route('membership-batches.show', $batch))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('membership-batches/show')
            ->where('rows.data.0.supplied_membership_number', '267118')
            ->where('rows.data.0.allocated_membership_number', null)
            ->where('rows.data.1.supplied_membership_number', '267117'));

    $this->post(route('membership-batches.process', $batch))->assertRedirect();

    $alice = Member::query()->where('membership_number', '267118')->sole();
    expect($alice->full_name)->toBe('Alice Smith')
        ->and(Member::query()->where('membership_number', '267117')->count())->toBe(1)
        ->and(Member::query()->where('membership_number', '267119')->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'membership.batch.uploaded')->where('subject_label', $batch->batch_reference)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'membership.created')->where('subject_label', '267118')->exists())->toBeTrue();
});

test('it blocks duplicate emails within a file and against the register', function () {
    $grade = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();

    Member::factory()->create([
        'membership_number' => '267117',
        'full_name' => 'Existing Member',
        'email' => 'registered@example.com',
        'membership_grade_id' => $grade->id,
    ]);

    $content = newMembershipRuleWorkbook(
        ['Full Name', 'Grade', 'Email'],
        [
            ['Alice Smith', 'Graduate', 'alice@example.com'],
            ['Bob Brown', 'Graduate', 'alice@example.com'],
            ['Cara Cole', 'Graduate', 'registered@example.com'],
        ],
    );

    $this->actingAs(newMembershipRuleUser())
        ->post(route('membership-batches.store'), [
            'file' => UploadedFile::fake()->createWithContent('members.xlsx', $content),
        ])
        ->assertRedirect();

    $batch = MembershipBatch::query()->sole();
    $rows = $batch->rows()->orderBy('row_number')->get();

    expect($rows[0]->validation_errors)->toContain('This email address appears more than once in the uploaded file.')
        ->and($rows[1]->validation_errors)->toContain('This email address appears more than once in the uploaded file.')
        ->and($rows[2]->validation_errors)->toContain('A member with this email address already exists in the register.');
});

test('it records file-supplied registration dates and defaults to the processing date', function () {
    $this->travelTo(Carbon::parse('2026-03-10 09:00:00'));

    $content = newMembershipRuleWorkbook(
        ['Full Name', 'Grade', 'Membership Number', 'Registration Date'],
        [
            ['Alice Smith', 'Graduate', '267118', '2024-02-05'],
            ['Bob Brown', 'Graduate', '267119', ''],
        ],
    );

    $this->actingAs(newMembershipRuleUser())
        ->post(route('membership-batches.store'), [
            'file' => UploadedFile::fake()->createWithContent('members.xlsx', $content),
        ])
        ->assertRedirect();

    $batch = MembershipBatch::query()->sole();

    expect($batch->rows()->orderBy('row_number')->pluck('registration_date')->map(fn ($date) => $date?->toDateString())->all())
        ->toBe(['2024-02-05', null]);

    $this->post(route('membership-batches.process', $batch))->assertRedirect();

    expect(Member::query()->where('membership_number', '267118')->sole()->registration_date->toDateString())
        ->toBe('2024-02-05')
        ->and(Member::query()->where('membership_number', '267119')->sole()->registration_date->toDateString())
        ->toBe('2026-03-10');
});

test('it reads induction-style date headings and rejects future registration dates', function () {
    $content = newMembershipRuleWorkbook(
        ['Full Name', 'Grade', 'Date of Election'],
        [
            ['Alice Smith', 'Graduate', '15TH DECEMBER, 2022'],
            ['Bob Brown', 'Graduate', '2999-01-01'],
        ],
    );

    $this->actingAs(newMembershipRuleUser())
        ->post(route('membership-batches.store'), [
            'file' => UploadedFile::fake()->createWithContent('members.xlsx', $content),
        ])
        ->assertRedirect();

    $rows = MembershipBatch::query()->sole()->rows()->orderBy('row_number')->get();

    expect($rows[0]->registration_date->toDateString())->toBe('2022-12-15')
        ->and($rows[0]->validation_errors)->toBeNull()
        ->and($rows[1]->registration_date->toDateString())->toBe('2999-01-01')
        ->and($rows[1]->validation_errors)->toContain('Registration date cannot be in the future.');
});

test('it allocates generated numbers after the highest existing number', function () {
    $grade = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();

    Member::factory()->create([
        'membership_number' => '267117',
        'full_name' => 'Existing Member',
        'membership_grade_id' => $grade->id,
    ]);

    $numbers = app(MembershipNumberAllocator::class)->allocate(3);

    expect($numbers)->toBe(['267118', '267119', '267120']);
});

test('it fills free six-digit numbers once the highest number reaches 999999', function () {
    $grade = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();

    foreach (['999999', '000002'] as $number) {
        Member::factory()->create([
            'membership_number' => $number,
            'full_name' => "Member {$number}",
            'membership_grade_id' => $grade->id,
        ]);
    }

    $numbers = app(MembershipNumberAllocator::class)->allocate(3);

    expect($numbers)->toBe(['000001', '000003', '000004']);
});
