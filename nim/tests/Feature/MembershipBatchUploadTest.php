<?php

use App\Enums\MembershipBatchRowStatus;
use App\Enums\MembershipBatchStatus;
use App\Models\Member;
use App\Models\MembershipBatch;
use App\Models\MembershipGrade;
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

function membershipWorkbookContent(array $rows, bool $addSecondSheet = false): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray(
        [['Full Name', 'Grade'], ...$rows],
        null,
        'A1',
    );

    if ($addSecondSheet) {
        $spreadsheet->createSheet()->setTitle('Second sheet');
    }

    $path = tempnam(sys_get_temp_dir(), 'nim-membership-');

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

function membershipImportUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('Editor');

    return $user;
}

test('it stores a private workbook and previews validated rows without creating members or numbers', function () {
    $grade = MembershipGrade::query()->where('name', 'Graduate')->firstOrFail();
    Member::factory()->create([
        'membership_number' => '00000001',
        'full_name' => '  Alice Smith ',
        'membership_grade_id' => $grade->id,
    ]);

    $content = membershipWorkbookContent([
        ['Alice Smith', 'Graduate'],
        ['ALICE SMITH', 'Graduate'],
        ['Bob Brown', 'Unknown'],
        ['', 'Graduate'],
    ]);

    $response = $this->actingAs(membershipImportUser())
        ->from(route('membership-batches.create'))
        ->post(route('membership-batches.store'), [
            'file' => UploadedFile::fake()->createWithContent('members.xlsx', $content),
        ]);

    $batch = MembershipBatch::query()->sole();

    $response->assertRedirect(route('membership-batches.show', $batch));
    expect($batch->status)->toBe(MembershipBatchStatus::ReadyForReview)
        ->and($batch->total_rows)->toBe(4)
        ->and($batch->valid_rows)->toBe(2)
        ->and($batch->failed_rows)->toBe(2)
        ->and($batch->warning_rows)->toBe(2);

    Storage::disk('local')->assertExists($batch->original_file_path);
    expect($batch->original_file_path)->toStartWith('membership-imports/');

    $rows = $batch->rows()->orderBy('row_number')->get();
    expect($rows[0]->status)->toBe(MembershipBatchRowStatus::Warning)
        ->and($rows[0]->validation_warnings)->toContain('A member with this name already exists in the register.')
        ->and($rows[1]->validation_warnings)->toContain('This name appears more than once in the uploaded file.')
        ->and($rows[2]->validation_errors[0])->toContain('not an active configured grade')
        ->and($rows[3]->validation_errors)->toContain('Full Name must contain text.')
        ->and($rows->every(fn ($row): bool => $row->member_id === null && $row->allocated_membership_number === null))
        ->toBeTrue();

    expect(Member::query()->count())->toBe(1);

    $this->get(route('membership-batches.show', $batch))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('membership-batches/show')
            ->where('batch.total_rows', 4)
            ->has('rows.data', 4));
});

test('it allows the same workbook to be uploaded again as a separate batch', function () {
    $user = membershipImportUser();
    $content = membershipWorkbookContent([
        ['Charlie Brown', 'Graduate'],
    ]);
    $upload = fn () => UploadedFile::fake()->createWithContent('members.xlsx', $content);

    $this->actingAs($user)
        ->from(route('membership-batches.create'))
        ->post(route('membership-batches.store'), ['file' => $upload()])
        ->assertRedirect(route('membership-batches.show', MembershipBatch::query()->sole()));

    $this->from(route('membership-batches.create'))
        ->post(route('membership-batches.store'), ['file' => $upload()])
        ->assertRedirect();

    expect(MembershipBatch::query()->count())->toBe(2);
});

test('it rejects unauthorized uploads and workbooks with multiple worksheets', function () {
    $userWithoutPermission = User::factory()->create();

    $this->actingAs($userWithoutPermission)
        ->get(route('membership-batches.create'))
        ->assertForbidden();

    $user = membershipImportUser();
    $response = $this->actingAs($user)
        ->post(route('membership-batches.store'), [
            'file' => UploadedFile::fake()->createWithContent(
                'multiple.xlsx',
                membershipWorkbookContent([['Dana White', 'Graduate']], true),
            ),
        ]);

    $batch = MembershipBatch::query()->sole();
    $response->assertRedirect(route('membership-batches.show', $batch));
    expect($batch->status)->toBe(MembershipBatchStatus::Failed)
        ->and($batch->failure_reason)->toBe('The workbook must contain exactly one worksheet.')
        ->and($batch->rows()->count())->toBe(0);

    Storage::disk('local')->assertExists($batch->original_file_path);
});
