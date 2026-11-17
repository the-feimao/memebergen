<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MembershipGrade;
use App\Models\MembershipHistory;
use App\Services\MembershipNumberAllocator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class NyscDecember2024InductionSeeder extends Seeder
{
    /**
     * Seed/import members from the NYSC December 2024 Induction upload list.
     *
     * - Preserves mem_no/Mem_No when provided (numeric).
     * - If mem_no is empty, allocates a new membership number from the configured sequence
     *   (requires membership_sequences to be initialized) in alphabetical order of those rows.
     * - Idempotent by membership_number.
     */
    public function run(): void
    {
        $path = base_path('membership file/graduates/NYSC December 2024 Induction Upload List.xlsx');

        if (! is_file($path)) {
            throw new RuntimeException("NYSC December 2024 Induction workbook not found at [{$path}].");
        }

        $gradesByName = MembershipGrade::query()
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->mapWithKeys(static fn (MembershipGrade $grade): array => [
                Member::normaliseName($grade->name) => ['id' => $grade->id, 'name' => $grade->name],
            ]);

        $reader = IOFactory::createReaderForFile($path);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $header = [];
        $highestColumn = $sheet->getHighestColumn();
        $headerRow = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false, false)[0] ?? [];
        foreach ($headerRow as $i => $value) {
            $key = $this->normalizeHeader((string) $value);
            if ($key !== '' && ! array_key_exists($key, $header)) {
                $header[$key] = $i;
            }
        }

        $col = function (array $candidates) use ($header): ?int {
            foreach ($candidates as $candidate) {
                if (array_key_exists($candidate, $header)) {
                    return $header[$candidate];
                }
            }

            return null;
        };

        $firstIdx = $col(['first_name', 'firstname']);
        $otherIdx = $col(['othername', 'other_name']);
        $lastIdx = $col(['lastname', 'last_name']);
        $gradeIdx = $col(['grade']);
        $memIdx = $col(['mem_no', 'memno', 'membership_number']);
        $emailIdx = $col(['email']);
        $phoneIdx = $col(['phone']);
        $stateIdx = $col(['state']);

        foreach ([
            'first name' => $firstIdx,
            'last name' => $lastIdx,
            'grade' => $gradeIdx,
            'mem no' => $memIdx,
        ] as $label => $idx) {
            if ($idx === null) {
                throw new RuntimeException("Required column [{$label}] was not found in the NYSC workbook.");
            }
        }

        $rows = [];
        $preservedNumbers = [];

        for ($r = 2; $r <= $highestRow; $r++) {
            $rowValues = $sheet->rangeToArray("A{$r}:{$highestColumn}{$r}", null, true, false, false)[0] ?? [];

            $first = $this->cellString($rowValues[$firstIdx] ?? null);
            $other = $otherIdx !== null ? $this->cellString($rowValues[$otherIdx] ?? null) : '';
            $last = $this->cellString($rowValues[$lastIdx] ?? null);
            $fullName = $this->buildFullName($first, $other, $last);

            $rawMem = $this->cellString($rowValues[$memIdx] ?? null);
            $rawGrade = $this->cellString($rowValues[$gradeIdx] ?? null);
            $email = $emailIdx !== null ? $this->nullableString($rowValues[$emailIdx] ?? null) : null;
            $phone = $phoneIdx !== null ? $this->nullableString($rowValues[$phoneIdx] ?? null) : null;
            $state = $stateIdx !== null ? $this->nullableString($rowValues[$stateIdx] ?? null) : null;

            if ($fullName === '' && $rawMem === '' && $rawGrade === '' && $email === null && $phone === null && $state === null) {
                continue;
            }

            if ($fullName === '') {
                throw new RuntimeException("Row {$r}: member name is missing.");
            }

            if ($rawMem !== '' && ! ctype_digit($rawMem)) {
                throw new RuntimeException("Row {$r} ({$fullName}): mem_no must be numeric when provided.");
            }

            if ($rawMem !== '') {
                if (isset($preservedNumbers[$rawMem])) {
                    throw new RuntimeException("Row {$r}: duplicate mem_no [{$rawMem}] in workbook.");
                }
                $preservedNumbers[$rawMem] = true;
            }

            $grade = $gradesByName->get(Member::normaliseName($rawGrade));
            if ($grade === null) {
                throw new RuntimeException("Row {$r} ({$fullName}): grade [{$rawGrade}] is not an active configured grade.");
            }

            $rows[] = [
                'row_number' => $r,
                'membership_number' => $rawMem !== '' ? $rawMem : null,
                'full_name' => $fullName,
                'normalised_name' => Member::normaliseName($fullName),
                'email' => $email,
                'phone' => $phone,
                'state' => $state,
                'grade_id' => $grade['id'],
            ];
        }

        if ($rows === []) {
            $this->command?->warn('No member rows found to import.');

            return;
        }

        $needsAllocation = array_values(array_filter($rows, static fn (array $row): bool => $row['membership_number'] === null));

        DB::transaction(function () use ($rows, $needsAllocation, $preservedNumbers): void {
            $allocatedMap = [];

            if ($needsAllocation !== []) {
                usort($needsAllocation, static function (array $a, array $b): int {
                    return [$a['normalised_name'], $a['row_number']] <=> [$b['normalised_name'], $b['row_number']];
                });

                /** @var MembershipNumberAllocator $allocator */
                $allocator = app(MembershipNumberAllocator::class);
                $allocated = $allocator->allocate(count($needsAllocation));

                foreach ($needsAllocation as $i => $row) {
                    $number = $allocated[$i];

                    if (isset($preservedNumbers[$number])) {
                        throw new RuntimeException("Allocated membership number [{$number}] collides with a preserved mem_no in the workbook. Sequence/configuration must be corrected before import.");
                    }

                    if (Member::query()->where('membership_number', $number)->exists()) {
                        throw new RuntimeException("Allocated membership number [{$number}] already exists in the register. Sequence/configuration must be corrected before import.");
                    }

                    $allocatedMap[$row['row_number']] = $number;
                }
            }

            foreach ($rows as $row) {
                $membershipNumber = $row['membership_number'] ?? ($allocatedMap[$row['row_number']] ?? null);

                if ($membershipNumber === null) {
                    throw new RuntimeException("Row {$row['row_number']}: could not determine membership number.");
                }

                $member = Member::query()->updateOrCreate(
                    ['membership_number' => $membershipNumber],
                    [
                        'full_name' => $row['full_name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'],
                        'state' => $row['state'],
                        'membership_grade_id' => $row['grade_id'],
                        // The workbook records the induction date in its "date" column.
                        'registration_date' => '2024-12-27',
                    ],
                );

                $this->ensureRegistrationHistory($member, (int) $row['grade_id']);
            }
        });
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($header, '_');
    }

    private function cellString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';

        return preg_replace('/\s+/u', ' ', trim($string)) ?? trim($string);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->cellString($value);

        return $value === '' ? null : $value;
    }

    private function buildFullName(string $first, string $other, string $last): string
    {
        $parts = [];

        foreach ([$first, $other, $last] as $part) {
            $part = preg_replace('/\s+/u', ' ', trim($part)) ?? trim($part);
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(' ', $parts);
    }

    private function ensureRegistrationHistory(Member $member, int $gradeId): void
    {
        $exists = MembershipHistory::query()
            ->where('member_id', $member->id)
            ->where('change_type', 'registration')
            ->exists();

        if ($exists) {
            return;
        }

        MembershipHistory::query()->create([
            'member_id' => $member->id,
            'previous_grade_id' => null,
            'new_grade_id' => $gradeId,
            'change_type' => 'registration',
            'changed_by' => null,
            'changed_at' => Carbon::now(),
        ]);
    }
}
