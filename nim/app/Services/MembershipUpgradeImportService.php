<?php

namespace App\Services;

use App\Enums\MembershipBatchRowStatus;
use App\Enums\MembershipBatchStatus;
use App\Enums\MembershipBatchType;
use App\Imports\UpgradeMembershipRowsImport;
use App\Models\Member;
use App\Models\MembershipBatch;
use App\Models\MembershipBatchRow;
use App\Models\MembershipGrade;
use App\Models\UpgradeType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Exceptions\NoSheetsFoundException;
use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

class MembershipUpgradeImportService
{
    public function __construct(
        private readonly MembershipGradeResolver $gradeResolver,
        private readonly SystemSettings $settings,
    ) {}

    public function store(UploadedFile $file, User $uploader, UpgradeType $upgradeType): MembershipBatch
    {
        $fileHash = hash_file('sha256', $file->getRealPath() ?: '');

        if ($fileHash === false) {
            throw new RuntimeException('Unable to calculate the uploaded workbook hash.');
        }

        $batchType = MembershipBatchType::Upgrade;
        $disk = (string) config('membership.import.disk');
        $batchReference = (string) Str::ulid();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'xlsx');
        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            $extension = 'xlsx';
        }
        $filePath = $file->storeAs(
            'membership-upgrade-imports',
            $batchReference.'.'.$extension,
            $disk,
        );

        if ($filePath === false) {
            throw new RuntimeException('Unable to store the uploaded workbook.');
        }

        try {
            $batch = MembershipBatch::query()->create([
                'batch_reference' => $batchReference,
                'batch_type' => $batchType,
                'upgrade_type_id' => $upgradeType->getKey(),
                'uploaded_by' => $uploader->getKey(),
                'original_filename' => Str::limit(
                    str_replace(['\\', '/'], '-', $file->getClientOriginalName()),
                    255,
                    '',
                ),
                'original_file_path' => $filePath,
                'file_sha256' => $fileHash,
                'status' => MembershipBatchStatus::Validating,
                'uploaded_at' => now(),
            ]);
        } catch (QueryException $exception) {
            Storage::disk($disk)->delete($filePath);

            throw $exception;
        }

        return $this->preview($batch, $upgradeType);
    }

    private function preview(MembershipBatch $batch, UpgradeType $upgradeType): MembershipBatch
    {
        $disk = (string) config('membership.import.disk');
        $path = $batch->original_file_path;
        $fullPath = Storage::disk($disk)->path($path);

        try {
            $reader = IOFactory::createReaderForFile($fullPath);
            $worksheetNames = $reader->listWorksheetNames($fullPath);
            $worksheetInfo = $reader->listWorksheetInfo($fullPath);
        } catch (NoSheetsFoundException|NoTypeDetectedException|SpreadsheetException|SpreadsheetReaderException $exception) {
            Log::warning('Unable to read membership upgrade workbook.', [
                'batch_reference' => $batch->batch_reference,
                'exception' => $exception,
            ]);

            return $this->failBatch($batch, 'The uploaded file could not be read as a valid .xlsx or .csv file.');
        }

        if (count($worksheetNames) !== 1) {
            return $this->failBatch($batch, 'The workbook must contain exactly one worksheet.');
        }

        $maximumRows = $this->settings->getInt(SystemSettings::IMPORT_MAX_ROWS);
        $physicalRowCount = max(0, (int) ($worksheetInfo[0]['totalRows'] ?? 0) - 1);

        if ($physicalRowCount > $maximumRows) {
            return $this->failBatch(
                $batch,
                "The worksheet exceeds the configured limit of {$maximumRows} data rows.",
                totalRows: $physicalRowCount,
            );
        }

        try {
            $headingRows = Excel::toArray(new HeadingRowImport, $path, $disk);
            $detectedHeadings = array_values(array_filter(
                array_map(
                    static fn (mixed $heading): string => is_scalar($heading) ? trim((string) $heading) : '',
                    $headingRows[0][0] ?? [],
                ),
                static fn (string $heading): bool => $heading !== '',
            ));

            $import = new UpgradeMembershipRowsImport(
                $maximumRows,
                $this->settings->getInt(SystemSettings::IMPORT_CHUNK_SIZE),
            );
            Excel::import($import, $path, $disk);
        } catch (NoSheetsFoundException|NoTypeDetectedException|SpreadsheetException|SpreadsheetReaderException $exception) {
            Log::warning('Unable to parse membership upgrade workbook.', [
                'batch_reference' => $batch->batch_reference,
                'exception' => $exception,
            ]);

            return $this->failBatch($batch, 'The uploaded file could not be read as a valid .xlsx or .csv file.');
        }

        if ($import->rows() === []) {
            return $this->failBatch(
                $batch,
                'The worksheet contains no non-empty upgrade rows.',
                $detectedHeadings,
                $physicalRowCount,
            );
        }

        return $this->savePreview($batch, $import->rows(), $detectedHeadings, $upgradeType);
    }

    /**
     * @param  list<array{row_number: int, values: array<array-key, mixed>}>  $sourceRows
     * @param  list<string>  $detectedHeadings
     */
    private function savePreview(
        MembershipBatch $batch,
        array $sourceRows,
        array $detectedHeadings,
        UpgradeType $upgradeType,
    ): MembershipBatch {
        $fullNameKey = $this->firstPresentKey($detectedHeadings, ['full_name', 'fullname', 'name', 'member_name', 'membername']);
        $firstNameKey = $this->firstPresentKey($detectedHeadings, ['first_name', 'firstname', 'first', 'given_name', 'givenname']);
        $lastNameKey = $this->firstPresentKey($detectedHeadings, ['last_name', 'lastname', 'last', 'surname', 'family_name', 'familyname']);
        $otherNameKey = $this->firstPresentKey($detectedHeadings, ['other_name', 'othername', 'other_names', 'middle_name', 'middlename', 'middle']);
        $usesSplitName = $fullNameKey === null && ($firstNameKey !== null || $lastNameKey !== null);
        $hasNameSource = $fullNameKey !== null || $usesSplitName;

        $numberHeadingKey = $this->firstPresentKey($detectedHeadings, [
            'membership_number',
            'membership_no',
            'membershipno',
            'member_number',
            'member_no',
            'memberno',
            'mem_number',
            'mem_no',
            'memno',
            'membership_id',
            'member_id',
            'memberid',
            'reg_no',
            'regno',
            'no',
        ]);

        $gradeKey = $this->firstPresentKey($detectedHeadings, [
            'grade',
            'grade_name',
            'membership_grade',
            'member_grade',
            'current_grade',
            'present_grade',
            'existing_grade',
        ]);

        $intendedGradeKey = $this->firstPresentKey($detectedHeadings, [
            'intended_grade',
            'intendedgrade',
            'target_grade',
            'new_grade',
            'upgrade_to',
            'new_membership_grade',
            'intended_membership_grade',
        ]);

        $emailKey = $this->firstPresentKey($detectedHeadings, ['email', 'email_address', 'e_mail', 'mail']);
        $phoneKey = $this->firstPresentKey($detectedHeadings, ['phone', 'phone_number', 'phone_no', 'mobile', 'mobile_number', 'telephone', 'tel']);
        $dobKey = $this->firstPresentKey($detectedHeadings, ['dob', 'd_o_b', 'date_of_birth', 'dateofbirth', 'birth_date', 'birthday']);

        $totalRows = count($sourceRows);

        if (! $hasNameSource) {
            return $this->failBatch(
                $batch,
                'The uploaded file must include member name headings. Provide a full name column (e.g. "Full Name"/"Name") or split name columns (e.g. "firstname"/"lastname"/"othername").',
                $detectedHeadings,
                $totalRows,
            );
        }

        if ($numberHeadingKey === null) {
            return $this->failBatch(
                $batch,
                'The uploaded file must include a membership number column (e.g. "Membership Number", "membership_no", "mem_no", "member_no"). Upgrades cannot proceed without membership numbers.',
                $detectedHeadings,
                $totalRows,
            );
        }

        if ($gradeKey === null) {
            return $this->failBatch(
                $batch,
                'The uploaded file must include a grade column (e.g. "Grade", "Membership Grade"). Upgrades cannot proceed without grades.',
                $detectedHeadings,
                $totalRows,
            );
        }

        /** @var Collection<string, array{id:int,name:string}> $gradesByName */
        $gradesByName = MembershipGrade::query()
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->mapWithKeys(static fn (MembershipGrade $grade): array => [
                Member::normaliseName($grade->name) => [
                    'id' => $grade->getKey(),
                    'name' => $grade->name,
                ],
            ]);

        $preparedRows = [];

        foreach ($sourceRows as $sourceRow) {
            $values = $sourceRow['values'];
            $errors = [];
            $warnings = [];
            $fullName = null;

            if ($usesSplitName) {
                $firstName = $firstNameKey !== null ? $this->nullableTextValue($values[$firstNameKey] ?? null) : null;
                $lastName = $lastNameKey !== null ? $this->nullableTextValue($values[$lastNameKey] ?? null) : null;
                $otherName = $otherNameKey !== null ? $this->nullableTextValue($values[$otherNameKey] ?? null) : null;

                $hasFormula = collect([$firstName, $otherName, $lastName])
                    ->filter(fn (?string $part): bool => $part !== null && str_starts_with($part, '='))
                    ->isNotEmpty();

                if ($hasFormula) {
                    $errors[] = 'Name fields must not be formulas.';
                } else {
                    $fullName = $this->buildSplitFullName($lastName, $otherName, $firstName);

                    if ($fullName === '') {
                        $fullName = null;
                        $errors[] = 'Member name must contain text (lastname and/or firstname).';
                    } elseif (Str::length($fullName) > 255) {
                        $errors[] = 'Full Name must not exceed 255 characters.';
                    }
                }
            } else {
                $fullName = $this->nullableTextValue($values[$fullNameKey] ?? null);

                if ($fullName === null) {
                    $errors[] = 'Full Name must contain text.';
                } elseif (Str::length($fullName) > 255) {
                    $errors[] = 'Full Name must not exceed 255 characters.';
                } elseif (str_starts_with($fullName, '=')) {
                    $errors[] = 'Full Name must not be a formula.';
                }
            }

            $suppliedNumber = $this->nullableTextValue($values[$numberHeadingKey] ?? null);

            if ($suppliedNumber === null) {
                $errors[] = 'Membership Number must contain text.';
            } elseif (! ctype_digit($suppliedNumber)) {
                $errors[] = 'Membership Number must be numeric-only.';
            }

            $rawGrade = $this->nullableTextValue($values[$gradeKey] ?? null);
            $suppliedGradeId = null;
            $suppliedGradeName = $rawGrade;
            $targetGradeFromSupplied = null;

            if ($rawGrade === null) {
                $errors[] = 'Grade must be present. Upgrades cannot proceed without the member\'s current grade.';
            } elseif (str_starts_with($rawGrade, '=')) {
                $errors[] = 'Grade must not be a formula.';
            } else {
                $grade = $this->gradeResolver->resolve($rawGrade, $gradesByName);

                if ($grade === null) {
                    $errors[] = "Grade \"{$rawGrade}\" is not an active configured grade or a recognized alias.";
                } else {
                    $suppliedGradeId = $grade['id'];
                    $suppliedGradeName = $grade['name'];
                    $nextGradeName = $this->gradeResolver->nextGradeName($suppliedGradeName);

                    if ($nextGradeName === null) {
                        $errors[] = "Grade \"{$suppliedGradeName}\" cannot be upgraded further using the confirmed one-step rule.";
                    } else {
                        $targetGradeFromSupplied = $this->gradeResolver->resolve($nextGradeName, $gradesByName);

                        if ($targetGradeFromSupplied === null) {
                            $errors[] = "Target grade \"{$nextGradeName}\" is not an active configured grade.";
                        }
                    }
                }
            }

            $rawIntendedGrade = $intendedGradeKey !== null
                ? $this->nullableTextValue($values[$intendedGradeKey] ?? null)
                : null;
            $explicitIntendedGradeId = null;
            $explicitIntendedGradeName = $rawIntendedGrade;

            if ($rawIntendedGrade !== null) {
                if (str_starts_with($rawIntendedGrade, '=')) {
                    $errors[] = 'Intended Grade must not be a formula.';
                } else {
                    $intendedGrade = $this->gradeResolver->resolve($rawIntendedGrade, $gradesByName);

                    if ($intendedGrade === null) {
                        $errors[] = "Intended grade \"{$rawIntendedGrade}\" is not an active configured grade or a recognized alias.";
                    } else {
                        $explicitIntendedGradeId = $intendedGrade['id'];
                        $explicitIntendedGradeName = $intendedGrade['name'];
                    }
                }
            }

            $email = $emailKey !== null ? $this->nullableTextValue($values[$emailKey] ?? null) : null;
            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email address is not valid.';
            }

            $phone = $phoneKey !== null ? $this->nullableTextValue($values[$phoneKey] ?? null) : null;
            [$dateOfBirth, $dateOfBirthError] = $this->parseDateOfBirth(
                $dobKey !== null ? ($values[$dobKey] ?? null) : null,
            );

            if ($dateOfBirthError !== null) {
                $errors[] = $dateOfBirthError;
            }

            $preparedRows[] = [
                'row_number' => $sourceRow['row_number'],
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'date_of_birth' => $dateOfBirth,
                'supplied_membership_number' => $suppliedNumber,
                'supplied_grade_value' => $suppliedGradeName,
                'supplied_grade_id' => $suppliedGradeId,
                'explicit_intended_grade_value' => $explicitIntendedGradeName,
                'explicit_intended_grade_id' => $explicitIntendedGradeId,
                'member_id' => null,
                'previous_grade_id' => null,
                'target_grade_id' => $targetGradeFromSupplied['id'] ?? null,
                'target_grade_value' => $targetGradeFromSupplied['name'] ?? null,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        $numberCounts = [];
        foreach ($preparedRows as $row) {
            $num = $row['supplied_membership_number'];
            if ($num !== null && ctype_digit($num)) {
                $numberCounts[$num] = ($numberCounts[$num] ?? 0) + 1;
            }
        }

        $numbers = array_keys($numberCounts);
        $membersByNumber = $numbers === []
            ? collect()
            : Member::query()
                ->with('grade:id,name')
                ->whereIn('membership_number', $numbers)
                ->get()
                ->keyBy('membership_number');

        foreach ($preparedRows as $index => $row) {
            $num = $row['supplied_membership_number'];

            if ($num !== null && ctype_digit($num) && ($numberCounts[$num] ?? 0) > 1) {
                $preparedRows[$index]['errors'][] = 'This membership number appears more than once in the uploaded file.';
            }

            if ($num === null || ! ctype_digit($num)) {
                continue;
            }

            $member = $membersByNumber->get($num);

            if ($member === null) {
                $preparedRows[$index]['errors'][] = "No member found with membership number {$num}.";

                continue;
            }

            $preparedRows[$index]['member_id'] = $member->getKey();
            $preparedRows[$index]['previous_grade_id'] = $member->membership_grade_id;

            if ($row['full_name'] !== null && Member::normaliseName($row['full_name']) !== Member::normaliseName($member->full_name)) {
                $preparedRows[$index]['warnings'][] = "Supplied name does not match registered name ({$member->full_name}). Review required.";
            }

            $this->compareKeyInformation($preparedRows[$index], $member);

            if ($member->grade?->name === null) {
                $preparedRows[$index]['errors'][] = 'The registered member has no active grade on file; the upgrade cannot be verified.';
            }

            $explicitIntendedGradeId = $row['explicit_intended_grade_id'];
            $targetGradeId = $preparedRows[$index]['target_grade_id'];

            if ($explicitIntendedGradeId !== null && $targetGradeId !== null && (int) $explicitIntendedGradeId !== (int) $targetGradeId) {
                $expected = $preparedRows[$index]['target_grade_value'];
                $supplied = $row['explicit_intended_grade_value'];
                $preparedRows[$index]['errors'][] = "Supplied intended grade \"{$supplied}\" is not the next one-step upgrade (expected \"{$expected}\").";
            }
        }

        $now = now();
        $validRows = 0;
        $failedRows = 0;
        $warningRows = 0;
        $rowInserts = [];

        foreach ($preparedRows as $row) {
            $status = $row['errors'] !== []
                ? MembershipBatchRowStatus::Invalid
                : ($row['warnings'] !== [] ? MembershipBatchRowStatus::Warning : MembershipBatchRowStatus::Valid);

            if ($status === MembershipBatchRowStatus::Invalid) {
                $failedRows++;
            } else {
                $validRows++;
            }

            if ($row['warnings'] !== []) {
                $warningRows++;
            }

            $intendedGradeId = $row['target_grade_id'];
            $intendedGradeValue = $row['target_grade_value'] ?? $row['explicit_intended_grade_value'];

            $rowInserts[] = [
                'membership_batch_id' => $batch->getKey(),
                'row_number' => $row['row_number'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'date_of_birth' => $row['date_of_birth'],
                'supplied_full_name' => $row['full_name'],
                'supplied_membership_number' => $row['supplied_membership_number'],
                'grade_value' => $row['supplied_grade_value'],
                'intended_grade_value' => $intendedGradeValue,
                'normalised_name' => $row['full_name'] !== null ? Member::normaliseName($row['full_name']) : null,
                'membership_grade_id' => $intendedGradeId,
                'intended_grade_id' => $intendedGradeId,
                'previous_grade_id' => $row['previous_grade_id'],
                'upgrade_type_id' => $upgradeType->getKey(),
                'member_id' => $row['member_id'],
                'status' => $status->value,
                'validation_errors' => $row['errors'] === [] ? null : json_encode($row['errors'], JSON_THROW_ON_ERROR),
                'validation_warnings' => $row['warnings'] === [] ? null : json_encode($row['warnings'], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($batch, $detectedHeadings, $rowInserts, $validRows, $failedRows, $warningRows): void {
            foreach (array_chunk($rowInserts, 500) as $chunk) {
                MembershipBatchRow::query()->insert($chunk);
            }

            $batch->update([
                'status' => MembershipBatchStatus::ReadyForReview,
                'detected_headings' => $detectedHeadings,
                'total_rows' => count($rowInserts),
                'valid_rows' => $validRows,
                'failed_rows' => $failedRows,
                'warning_rows' => $warningRows,
                'failure_reason' => null,
            ]);
        });

        return $batch->refresh();
    }

    /**
     * Compare the supplied key information against the registered member record.
     *
     * @param  array<string, mixed>  $row
     */
    private function compareKeyInformation(array &$row, Member $member): void
    {
        $suppliedGradeId = $row['supplied_grade_id'];
        $currentGradeName = $member->grade?->name;

        if ($suppliedGradeId !== null && $currentGradeName !== null && (int) $suppliedGradeId !== (int) $member->membership_grade_id) {
            $supplied = $row['supplied_grade_value'];
            $row['errors'][] = "Grade \"{$supplied}\" does not match the registered grade \"{$currentGradeName}\" for this member.";
        }

        $email = $row['email'];
        if ($email !== null) {
            if ($member->email === null) {
                $row['warnings'][] = 'The registered member has no email on file; the supplied email could not be verified.';
            } elseif ($this->normaliseEmail($email) !== $this->normaliseEmail($member->email)) {
                $row['errors'][] = 'Supplied email does not match the registered member email.';
            }
        }

        $phone = $row['phone'];
        if ($phone !== null) {
            if ($member->phone === null) {
                $row['warnings'][] = 'The registered member has no phone number on file; the supplied phone could not be verified.';
            } elseif ($this->normalisePhone($phone) !== $this->normalisePhone($member->phone)) {
                $row['errors'][] = 'Supplied phone number does not match the registered member phone number.';
            }
        }

        $dateOfBirth = $row['date_of_birth'];
        if ($dateOfBirth !== null) {
            if ($member->date_of_birth === null) {
                $row['warnings'][] = 'The registered member has no date of birth on file; the supplied date of birth could not be verified.';
            } elseif ($member->date_of_birth->format('Y-m-d') !== $dateOfBirth) {
                $row['errors'][] = 'Supplied date of birth does not match the registered member date of birth.';
            }
        }
    }

    /**
     * @param  list<string>  $detectedHeadings
     */
    private function failBatch(
        MembershipBatch $batch,
        string $reason,
        array $detectedHeadings = [],
        int $totalRows = 0,
    ): MembershipBatch {
        $batch->update([
            'status' => MembershipBatchStatus::Failed,
            'detected_headings' => $detectedHeadings,
            'total_rows' => $totalRows,
            'valid_rows' => 0,
            'failed_rows' => 0,
            'warning_rows' => 0,
            'failure_reason' => $reason,
        ]);

        return $batch->refresh();
    }

    /**
     * @param  list<string>  $detectedHeadings
     * @param  list<string>  $candidates
     */
    private function firstPresentKey(array $detectedHeadings, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $detectedHeadings, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildSplitFullName(?string $lastName, ?string $otherName, ?string $firstName): string
    {
        $parts = [];
        foreach ([$lastName, $otherName, $firstName] as $part) {
            if ($part !== null && $part !== '') {
                $parts[] = $part;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{0: string|null, 1: string|null} The ISO date (or null) and any validation error.
     */
    private function parseDateOfBirth(mixed $value): array
    {
        if (! is_scalar($value) || is_bool($value)) {
            return [null, null];
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return [null, null];
        }

        try {
            if (is_numeric($raw)) {
                $date = ExcelDate::excelToDateTimeObject((float) $raw);
            } else {
                $date = Carbon::parse($raw);
            }

            return [$date->format('Y-m-d'), null];
        } catch (\Throwable) {
            return [null, "Date of birth \"{$raw}\" is not a valid date."];
        }
    }

    private function normaliseEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = mb_strtolower(trim($email));

        return $email === '' ? null : $email;
    }

    private function normalisePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits === '' ? null : $digits;
    }

    private function nullableTextValue(mixed $value): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? trim((string) $value);

        return $value === '' ? null : $value;
    }
}
