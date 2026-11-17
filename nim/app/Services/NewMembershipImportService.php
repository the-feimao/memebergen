<?php

namespace App\Services;

use App\Enums\MembershipBatchRowStatus;
use App\Enums\MembershipBatchStatus;
use App\Enums\MembershipBatchType;
use App\Imports\NewMembershipRowsImport;
use App\Models\Member;
use App\Models\MembershipBatch;
use App\Models\MembershipBatchRow;
use App\Models\MembershipGrade;
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

class NewMembershipImportService
{
    public function __construct(
        private readonly MembershipGradeResolver $gradeResolver,
        private readonly SystemSettings $settings,
    ) {}

    public function store(UploadedFile $file, User $uploader): MembershipBatch
    {
        $fileHash = hash_file('sha256', $file->getRealPath() ?: '');

        if ($fileHash === false) {
            throw new RuntimeException('Unable to calculate the uploaded workbook hash.');
        }

        $batchType = MembershipBatchType::NewRegistration;
        $disk = (string) config('membership.import.disk');
        $batchReference = (string) Str::ulid();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'xlsx');
        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            $extension = 'xlsx';
        }
        $filePath = $file->storeAs(
            'membership-imports',
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
            $this->removeStoredFile($disk, $filePath);

            throw $exception;
        }

        return $this->preview($batch);
    }

    private function preview(MembershipBatch $batch): MembershipBatch
    {
        $disk = (string) config('membership.import.disk');
        $path = $batch->original_file_path;
        $fullPath = Storage::disk($disk)->path($path);

        try {
            $reader = IOFactory::createReaderForFile($fullPath);
            $worksheetNames = $reader->listWorksheetNames($fullPath);
            $worksheetInfo = $reader->listWorksheetInfo($fullPath);
        } catch (NoSheetsFoundException|NoTypeDetectedException|SpreadsheetException|SpreadsheetReaderException $exception) {
            Log::warning('Unable to read membership workbook.', [
                'batch_reference' => $batch->batch_reference,
                'exception' => $exception,
            ]);

            return $this->failBatch($batch, 'The uploaded file could not be read as a valid .xlsx or .csv file.');
        }

        if (count($worksheetNames) !== 1) {
            return $this->failBatch(
                $batch,
                'The workbook must contain exactly one worksheet.',
            );
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

            $import = new NewMembershipRowsImport(
                $maximumRows,
                $this->settings->getInt(SystemSettings::IMPORT_CHUNK_SIZE),
            );
            Excel::import($import, $path, $disk);
        } catch (NoSheetsFoundException|NoTypeDetectedException|SpreadsheetException|SpreadsheetReaderException $exception) {
            Log::warning('Unable to parse membership workbook.', [
                'batch_reference' => $batch->batch_reference,
                'exception' => $exception,
            ]);

            return $this->failBatch($batch, 'The uploaded file could not be read as a valid .xlsx or .csv file.');
        }

        if ($import->rows() === []) {
            return $this->failBatch(
                $batch,
                'The worksheet contains no non-empty membership rows.',
                $detectedHeadings,
                $physicalRowCount,
            );
        }

        return $this->savePreview($batch, $import->rows(), $detectedHeadings);
    }

    /**
     * @param  list<array{row_number: int, values: array<array-key, mixed>}>  $sourceRows
     * @param  list<string>  $detectedHeadings
     */
    private function savePreview(
        MembershipBatch $batch,
        array $sourceRows,
        array $detectedHeadings,
    ): MembershipBatch {
        $fullNameKey = $this->firstPresentKey($detectedHeadings, ['full_name', 'fullname', 'name', 'member_name', 'membername']);
        $firstNameKey = $this->firstPresentKey($detectedHeadings, ['first_name', 'firstname', 'first', 'given_name', 'givenname']);
        $lastNameKey = $this->firstPresentKey($detectedHeadings, ['last_name', 'lastname', 'last', 'surname', 'family_name', 'familyname']);
        $otherNameKey = $this->firstPresentKey($detectedHeadings, ['other_name', 'othername', 'other_names', 'othernames', 'middle_name', 'middlename', 'middle']);
        $gradeKey = $this->firstPresentKey($detectedHeadings, ['grade', 'membership_grade', 'member_grade', 'grade_name', 'current_grade', 'present_grade', 'existing_grade']);
        $emailKey = $this->firstPresentKey($detectedHeadings, ['email', 'email_address', 'e_mail']);
        $phoneKey = $this->firstPresentKey($detectedHeadings, ['phone', 'phone_number', 'mobile', 'mobile_number', 'telephone']);
        $stateKey = $this->firstPresentKey($detectedHeadings, ['state', 'state_of_origin', 'location']);
        $dobKey = $this->firstPresentKey($detectedHeadings, ['dob', 'd_o_b', 'date_of_birth', 'date_of_birth_dob', 'birth_date', 'birthday', 'dateofbirth']);
        $addressKey = $this->firstPresentKey($detectedHeadings, ['address', 'residential_address', 'home_address', 'contact_address']);
        $registrationDateKey = $this->firstPresentKey($detectedHeadings, [
            'registration_date',
            'date_registered',
            'registered_on',
            'registered_date',
            'date_of_registration',
            'induction_date',
            'date_of_induction',
            'date_of_election',
            'date',
        ]);
        $numberKey = $this->firstPresentKey($detectedHeadings, [
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
            'reg_no',
            'regno',
        ]);

        $usesSplitName = $fullNameKey === null && ($firstNameKey !== null || $lastNameKey !== null);
        $hasNameSource = $fullNameKey !== null || $usesSplitName;
        $hasGradeHeading = $gradeKey !== null;

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
            $rawGrade = $gradeKey !== null ? ($values[$gradeKey] ?? null) : null;
            $rawEmail = $emailKey !== null ? ($values[$emailKey] ?? null) : null;
            $rawPhone = $phoneKey !== null ? ($values[$phoneKey] ?? null) : null;
            $rawState = $stateKey !== null ? ($values[$stateKey] ?? null) : null;
            $rawNumber = $numberKey !== null ? ($values[$numberKey] ?? null) : null;
            $rawDob = $dobKey !== null ? ($values[$dobKey] ?? null) : null;
            $rawAddress = $addressKey !== null ? ($values[$addressKey] ?? null) : null;
            $rawRegistrationDate = $registrationDateKey !== null ? ($values[$registrationDateKey] ?? null) : null;
            $gradeValue = $this->nullableTextValue($rawGrade);
            $email = $this->nullableTextValue($rawEmail);
            $phone = $this->nullableTextValue($rawPhone);
            $state = $this->nullableTextValue($rawState);
            $address = $this->nullableTextValue($rawAddress);
            [$dateOfBirth, $dateOfBirthError] = $this->parseDate($rawDob, 'Date of birth');
            [$registrationDate, $registrationDateError] = $this->parseDate($rawRegistrationDate, 'Registration date');
            $suppliedMembershipNumber = $this->nullableTextValue($rawNumber);
            $errors = [];
            $warnings = [];
            $fullName = null;
            $normalisedName = null;
            $lastNameForSort = null;
            $gradeId = null;

            if (! $hasNameSource) {
                $errors[] = 'Required name headings were not found. Provide a full name column (e.g. "Full Name"/"Name") or split name columns (e.g. "firstname"/"lastname"/"othername").';
            } elseif ($usesSplitName) {
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
                        $errors[] = 'Member name must contain text (lastname and/or firstname).';
                    } elseif (Str::length($fullName) > 255) {
                        $errors[] = 'Full Name must not exceed 255 characters.';
                    } else {
                        $normalisedName = Member::normaliseName($fullName);
                        $lastNameForSort = $this->normaliseLastNameSort($lastName);
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
                } else {
                    $normalisedName = Member::normaliseName($fullName);
                    $lastNameForSort = $this->normaliseLastNameSort($this->deriveLastName($fullName));
                }
            }

            if (! $hasGradeHeading) {
                $errors[] = 'Required heading "Grade" was not found.';
            } elseif ($gradeValue === null) {
                $errors[] = 'Grade must contain text.';
            } elseif (str_starts_with($gradeValue, '=')) {
                $errors[] = 'Grade must not be a formula.';
            } else {
                $grade = $this->resolveGrade($gradeValue, $gradesByName);

                if ($grade === null) {
                    $errors[] = "Grade \"{$gradeValue}\" is not an active configured grade.";
                } else {
                    $gradeId = $grade['id'];
                    $gradeValue = $grade['name'];
                }
            }

            if ($suppliedMembershipNumber !== null && ! ctype_digit($suppliedMembershipNumber)) {
                $errors[] = 'Membership number must be numeric-only when provided.';
            }

            if ($dateOfBirthError !== null) {
                $errors[] = $dateOfBirthError;
            }

            if ($registrationDateError !== null) {
                $errors[] = $registrationDateError;
            } elseif ($registrationDate !== null && Carbon::parse($registrationDate)->isAfter(today())) {
                $errors[] = 'Registration date cannot be in the future.';
            }

            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email address is not valid.';
            }

            $preparedRows[] = [
                'row_number' => $sourceRow['row_number'],
                'full_name' => $fullName,
                'last_name_sort' => $lastNameForSort,
                'email' => $email,
                'phone' => $phone,
                'state' => $state,
                'date_of_birth' => $dateOfBirth,
                'registration_date' => $registrationDate,
                'address' => $address,
                'grade_value' => $gradeValue,
                'normalised_name' => $normalisedName,
                'membership_grade_id' => $gradeId,
                'supplied_membership_number' => $suppliedMembershipNumber,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        $nameCounts = [];
        $numberCounts = [];
        $emailCounts = [];

        foreach ($preparedRows as $row) {
            if ($row['normalised_name'] !== null) {
                $nameCounts[$row['normalised_name']] = ($nameCounts[$row['normalised_name']] ?? 0) + 1;
            }

            $num = $row['supplied_membership_number'];
            if ($num !== null && ctype_digit($num)) {
                $numberCounts[$num] = ($numberCounts[$num] ?? 0) + 1;
            }

            $emailKey = $this->normaliseEmail($row['email']);
            if ($emailKey !== null) {
                $emailCounts[$emailKey] = ($emailCounts[$emailKey] ?? 0) + 1;
            }
        }

        $existingNames = $nameCounts === []
            ? []
            : Member::query()
                ->whereIn('normalised_name', array_keys($nameCounts))
                ->pluck('normalised_name')
                ->all();
        $existingNameSet = array_fill_keys($existingNames, true);

        $existingNumbers = $numberCounts === []
            ? []
            : Member::query()
                ->whereIn('membership_number', array_keys($numberCounts))
                ->pluck('membership_number')
                ->all();
        $existingNumberSet = array_fill_keys($existingNumbers, true);

        $existingEmails = $emailCounts === []
            ? []
            : Member::query()
                ->whereIn('email', array_keys($emailCounts))
                ->pluck('email')
                ->map(fn (?string $email): ?string => $this->normaliseEmail($email))
                ->filter()
                ->all();
        $existingEmailSet = array_fill_keys($existingEmails, true);

        foreach ($preparedRows as $index => $row) {
            $normalisedName = $row['normalised_name'];
            $suppliedNumber = $row['supplied_membership_number'];
            $emailKey = $this->normaliseEmail($row['email']);

            if ($normalisedName !== null && ($nameCounts[$normalisedName] ?? 0) > 1) {
                $preparedRows[$index]['warnings'][] = 'This name appears more than once in the uploaded file.';
            }

            if ($normalisedName !== null && isset($existingNameSet[$normalisedName])) {
                $preparedRows[$index]['warnings'][] = 'A member with this name already exists in the register.';
            }

            if ($suppliedNumber !== null && ctype_digit($suppliedNumber)) {
                if (($numberCounts[$suppliedNumber] ?? 0) > 1) {
                    $preparedRows[$index]['errors'][] = 'This membership number appears more than once in the uploaded file.';
                }

                if (isset($existingNumberSet[$suppliedNumber])) {
                    $preparedRows[$index]['errors'][] = "Membership number {$suppliedNumber} is already assigned to an existing member.";
                }
            }

            if ($emailKey !== null) {
                if (($emailCounts[$emailKey] ?? 0) > 1) {
                    $preparedRows[$index]['errors'][] = 'This email address appears more than once in the uploaded file.';
                }

                if (isset($existingEmailSet[$emailKey])) {
                    $preparedRows[$index]['errors'][] = 'A member with this email address already exists in the register.';
                }
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

            $rowInserts[] = [
                'membership_batch_id' => $batch->getKey(),
                'row_number' => $row['row_number'],
                'full_name' => $row['full_name'],
                'last_name_sort' => $row['last_name_sort'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'state' => $row['state'],
                'date_of_birth' => $row['date_of_birth'],
                'registration_date' => $row['registration_date'],
                'address' => $row['address'],
                'grade_value' => $row['grade_value'],
                'normalised_name' => $row['normalised_name'],
                'membership_grade_id' => $row['membership_grade_id'],
                'supplied_membership_number' => $row['supplied_membership_number'],
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
     * Derive a best-effort last name from a single full-name column.
     *
     * Importers that supply separate name columns always provide an explicit last
     * name. When only a full name is available, a comma-separated "Last, First"
     * value is respected; otherwise the final token is treated as the last name.
     */
    private function deriveLastName(?string $fullName): ?string
    {
        if ($fullName === null) {
            return null;
        }

        $name = trim($fullName);

        if ($name === '') {
            return null;
        }

        if (str_contains($name, ',')) {
            return trim(explode(',', $name, 2)[0]);
        }

        $parts = preg_split('/\s+/u', $name) ?: [];

        return $parts === [] ? null : (string) end($parts);
    }

    private function normaliseLastNameSort(?string $lastName): ?string
    {
        if ($lastName === null || trim($lastName) === '') {
            return null;
        }

        return Member::normaliseName($lastName);
    }

    /**
     * @param  Collection<string, array{id:int,name:string}>  $gradesByName
     * @return array{id:int,name:string}|null
     */
    private function resolveGrade(string $value, Collection $gradesByName): ?array
    {
        return $this->gradeResolver->resolve($value, $gradesByName);
    }

    private function nullableTextValue(mixed $value): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{0: string|null, 1: string|null} The ISO date (or null) and any validation error.
     */
    private function parseDate(mixed $value, string $label): array
    {
        if (! is_scalar($value) || is_bool($value)) {
            return [null, null];
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return [null, null];
        }

        $normalised = preg_replace('/(\d+)(st|nd|rd|th)\b/i', '$1', $raw) ?? $raw;
        $normalised = str_replace(',', ' ', $normalised);
        $normalised = preg_replace('/\s+/u', ' ', trim($normalised)) ?? $normalised;

        try {
            if (is_numeric($normalised)) {
                $date = ExcelDate::excelToDateTimeObject((float) $normalised);
            } else {
                $date = Carbon::parse($normalised);
            }

            return [$date->format('Y-m-d'), null];
        } catch (\Throwable) {
            return [null, "{$label} \"{$raw}\" is not a valid date."];
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

    private function removeStoredFile(string $disk, string $path): void
    {
        if (! Storage::disk($disk)->delete($path)) {
            Log::error('Unable to remove a duplicate membership workbook upload.', [
                'path' => $path,
                'disk' => $disk,
            ]);
        }
    }
}
