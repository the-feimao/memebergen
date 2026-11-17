<?php

use App\Enums\MembershipBatchRowStatus;
use App\Enums\MembershipBatchStatus;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MembershipBatch;
use App\Models\MembershipGrade;
use App\Models\MembershipHistory;
use App\Models\MembershipUpgrade;
use App\Models\UpgradeType;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\MembershipGradeSeeder;
use Illuminate\Http\UploadedFile;
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

function upgradeRuleWorkbook(array $headings, array $rows): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([$headings, ...$rows], null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'nim-upgrade-rules-');

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

function upgradeRuleUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('Editor');

    return $user;
}

function upgradeRuleType(): UpgradeType
{
    return UpgradeType::query()->create([
        'name' => 'ITMA',
        'description' => 'Test upgrade type',
        'is_active' => true,
    ]);
}

test('it refuses an upgrade upload without a grade column', function () {
    $content = upgradeRuleWorkbook(
        ['lastname', 'firstname', 'membership_number'],
        [['Doe', 'John', '267117']],
    );

    $this->actingAs(upgradeRuleUser())
        ->post(route('upgrade-batches.store'), [
            'upgrade_type_id' => upgradeRuleType()->id,
            'file' => UploadedFile::fake()->createWithContent('upgrades.xlsx', $content),
        ])
        ->assertRedirect();

    $batch = MembershipBatch::query()->sole();

    expect($batch->status)->toBe(MembershipBatchStatus::Failed)
        ->and($batch->failure_reason)->toContain('must include a grade column')
        ->and($batch->rows()->count())->toBe(0);
});

test('it refuses an upgrade upload without a membership number column', function () {
    $content = upgradeRuleWorkbook(
        ['lastname', 'firstname', 'grade'],
        [['Doe', 'John', 'Graduate']],
    );

    $this->actingAs(upgradeRuleUser())
        ->post(route('upgrade-batches.store'), [
            'upgrade_type_id' => upgradeRuleType()->id,
            'file' => UploadedFile::fake()->createWithContent('upgrades.xlsx', $content),
        ])
        ->assertRedirect();

    $batch = MembershipBatch::query()->sole();

    expect($batch->status)->toBe(MembershipBatchStatus::Failed)
        ->and($batch->failure_reason)->toContain('must include a membership number column')
        ->and($batch->rows()->count())->toBe(0);
});

test('it previews and processes a one-step upgrade while retaining the membership number', function () {
    $graduate = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();
    $associate = MembershipGrade::query()->where('name', 'Associate')->firstOrFail();

    $member = Member::factory()->create([
        'membership_number' => '267117',
        'full_name' => 'Doe John',
        'email' => 'john.doe@example.com',
        'phone' => '8031234567',
        'date_of_birth' => '1990-05-10',
        'membership_grade_id' => $graduate->id,
    ]);

    $content = upgradeRuleWorkbook(
        ['lastname', 'firstname', 'membership_number', 'grade', 'email', 'phone', 'dob'],
        [['Doe', 'John', '267117', 'member_graduate', 'John.Doe@example.com', '803-123-4567', '1990-05-10']],
    );

    $user = upgradeRuleUser();

    $this->actingAs($user)
        ->post(route('upgrade-batches.store'), [
            'upgrade_type_id' => upgradeRuleType()->id,
            'file' => UploadedFile::fake()->createWithContent('upgrades.xlsx', $content),
        ])
        ->assertRedirect();

    $batch = MembershipBatch::query()->sole();
    $row = $batch->rows()->sole();

    expect($batch->status)->toBe(MembershipBatchStatus::ReadyForReview)
        ->and($batch->valid_rows)->toBe(1)
        ->and($row->status)->toBe(MembershipBatchRowStatus::Valid)
        ->and($row->member_id)->toBe($member->id)
        ->and($row->previous_grade_id)->toBe($graduate->id)
        ->and($row->intended_grade_id)->toBe($associate->id)
        ->and($row->intended_grade_value)->toBe('Associate');

    $this->post(route('upgrade-batches.process', $batch))->assertRedirect();

    $member->refresh();

    $upgradeLog = AuditLog::query()->where('action', 'membership.upgraded')->sole();

    expect($member->membership_number)->toBe('267117')
        ->and($member->membership_grade_id)->toBe($associate->id)
        ->and(MembershipUpgrade::query()->where('member_id', $member->id)->count())->toBe(1)
        ->and(MembershipHistory::query()->where('member_id', $member->id)->where('change_type', 'upgrade')->count())->toBe(1)
        ->and($upgradeLog->subject_label)->toBe('267117')
        ->and($upgradeLog->metadata['previous_grade'])->toBe('Graduate')
        ->and($upgradeLog->metadata['new_grade'])->toBe('Associate')
        ->and(AuditLog::query()->where('action', 'membership.upgrade_batch.uploaded')->where('subject_label', $batch->batch_reference)->exists())->toBeTrue();
});

test('it blocks rows whose key information does not match the register', function () {
    $graduate = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();

    Member::factory()->create([
        'membership_number' => '267117',
        'full_name' => 'Doe John',
        'email' => 'john.doe@example.com',
        'phone' => '8031234567',
        'date_of_birth' => '1990-05-10',
        'membership_grade_id' => $graduate->id,
    ]);

    $content = upgradeRuleWorkbook(
        ['lastname', 'firstname', 'membership_number', 'grade', 'email', 'phone', 'dob'],
        [['Doe', 'John', '267117', 'Associate', 'other@example.com', '8039999999', '1991-01-01']],
    );

    $this->actingAs(upgradeRuleUser())
        ->post(route('upgrade-batches.store'), [
            'upgrade_type_id' => upgradeRuleType()->id,
            'file' => UploadedFile::fake()->createWithContent('upgrades.xlsx', $content),
        ])
        ->assertRedirect();

    $row = MembershipBatch::query()->sole()->rows()->sole();

    expect($row->status)->toBe(MembershipBatchRowStatus::Invalid)
        ->and($row->validation_errors)->toContain('Grade "Associate" does not match the registered grade "Graduate" for this member.')
        ->and($row->validation_errors)->toContain('Supplied email does not match the registered member email.')
        ->and($row->validation_errors)->toContain('Supplied phone number does not match the registered member phone number.')
        ->and($row->validation_errors)->toContain('Supplied date of birth does not match the registered member date of birth.');
});

test('it rejects an unknown membership number and a multi-step intended grade', function () {
    $graduate = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();

    Member::factory()->create([
        'membership_number' => '267117',
        'full_name' => 'Doe John',
        'membership_grade_id' => $graduate->id,
    ]);

    $content = upgradeRuleWorkbook(
        ['lastname', 'firstname', 'membership_number', 'grade', 'intended_grade'],
        [
            ['Doe', 'John', '267117', 'Graduate', 'Member'],
            ['Roe', 'Jane', '999999', 'Graduate', 'Associate'],
        ],
    );

    $this->actingAs(upgradeRuleUser())
        ->post(route('upgrade-batches.store'), [
            'upgrade_type_id' => upgradeRuleType()->id,
            'file' => UploadedFile::fake()->createWithContent('upgrades.xlsx', $content),
        ])
        ->assertRedirect();

    $batch = MembershipBatch::query()->sole();
    $rows = $batch->rows()->orderBy('row_number')->get();

    expect($batch->valid_rows)->toBe(0)
        ->and($batch->failed_rows)->toBe(2)
        ->and($rows[0]->validation_errors)->toContain('Supplied intended grade "Member" is not the next one-step upgrade (expected "Associate").')
        ->and($rows[1]->validation_errors)->toContain('No member found with membership number 999999.');
});

test('it previews the file grade as current grade and the computed one-step grade as intended', function () {
    $graduate = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();

    Member::factory()->create([
        'membership_number' => '267117',
        'full_name' => 'Doe John',
        'membership_grade_id' => $graduate->id,
    ]);

    $content = upgradeRuleWorkbook(
        ['lastname', 'firstname', 'membership_number', 'grade'],
        [
            ['Doe', 'John', '267117', 'member_graduate'],
            ['Roe', 'Jane', '999999', 'Associate'],
        ],
    );

    $this->actingAs(upgradeRuleUser())
        ->post(route('upgrade-batches.store'), [
            'upgrade_type_id' => upgradeRuleType()->id,
            'file' => UploadedFile::fake()->createWithContent('upgrades.xlsx', $content),
        ])
        ->assertRedirect();

    $batch = MembershipBatch::query()->sole();
    $rows = $batch->rows()->orderBy('row_number')->get();

    expect($rows[0]->grade_value)->toBe('Graduate')
        ->and($rows[0]->intended_grade_value)->toBe('Associate')
        ->and($rows[1]->grade_value)->toBe('Associate')
        ->and($rows[1]->intended_grade_value)->toBe('Member')
        ->and($rows[1]->status)->toBe(MembershipBatchRowStatus::Invalid);

    foreach ($rows as $row) {
        $row->forceFill([
            'intended_grade_id' => null,
            'intended_grade_value' => null,
        ])->save();
    }

    $this->get(route('upgrade-batches.show', $batch))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('upgrade-batches/show')
            ->where('rows.data.0.current_grade', 'Graduate')
            ->where('rows.data.0.intended_grade', 'Associate')
            ->where('rows.data.1.current_grade', 'Associate')
            ->where('rows.data.1.intended_grade', 'Member'));
});
