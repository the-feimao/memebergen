<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MembershipGrade;
use App\Models\MembershipHistory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MpeApril2022InductionSeeder extends Seeder
{
    /**
     * Seed members from the MPE April 2022 Induction workbook.
     */
    public function run(): void
    {
        $path = base_path('membership file/MPE April 2022 Induction.csv');

        if (! is_file($path)) {
            throw new RuntimeException("MPE April 2022 Induction CSV not found at [{$path}].");
        }

        $grades = MembershipGrade::query()
            ->whereIn('name', ['Graduate', 'Associate', 'Member', 'Fellow'])
            ->get()
            ->keyBy('name');

        foreach (['Graduate', 'Associate', 'Member', 'Fellow'] as $name) {
            if (! $grades->has($name)) {
                throw new RuntimeException("Required membership grade [{$name}] is missing. Run MembershipGradeSeeder first.");
            }
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open [{$path}] for reading.");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw new RuntimeException('The MPE April 2022 Induction CSV is empty.');
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

            $index = [];
            foreach ($header as $i => $column) {
                $index[$this->normalizeHeader((string) $column)] = $i;
            }

            $firstNameKey = $this->findHeader($index, ['first_name', 'firstname']);
            $lastNameKey = $this->findHeader($index, ['last_name', 'lastname']);
            $otherNameKey = $this->findHeader($index, ['other_name', 'othername']);
            $gradeKey = $this->findHeader($index, ['grade']);
            $memNoKey = $this->findHeader($index, ['mem_no', 'memno', 'membership_number']);
            $examNoKey = $this->findHeader($index, ['exam_no', 'examno']);
            $emailKey = $this->findHeader($index, ['email']);
            $phoneKey = $this->findHeader($index, ['phone']);
            $stateKey = $this->findHeader($index, ['state']);

            foreach ([
                'first name' => $firstNameKey,
                'last name' => $lastNameKey,
                'other name' => $otherNameKey,
                'grade' => $gradeKey,
                'Mem_No' => $memNoKey,
            ] as $label => $key) {
                if ($key === null) {
                    throw new RuntimeException("Required column [{$label}] was not found in the MPE April 2022 Induction CSV.");
                }
            }

            DB::transaction(function () use (
                $handle,
                $grades,
                $firstNameKey,
                $lastNameKey,
                $otherNameKey,
                $gradeKey,
                $memNoKey,
                $examNoKey,
                $emailKey,
                $phoneKey,
                $stateKey
            ): void {
                $line = 1;

                while (($row = fgetcsv($handle)) !== false) {
                    $line++;

                    if ($this->isBlankRow($row)) {
                        continue;
                    }

                    $membershipNumber = trim((string) ($row[$memNoKey] ?? ''));
                    $firstName = trim((string) ($row[$firstNameKey] ?? ''));
                    $lastName = trim((string) ($row[$lastNameKey] ?? ''));
                    $otherName = trim((string) ($row[$otherNameKey] ?? ''));
                    $gradeValue = trim((string) ($row[$gradeKey] ?? ''));
                    $examNo = $examNoKey !== null ? trim((string) ($row[$examNoKey] ?? '')) : '';
                    $email = $emailKey !== null ? $this->nullableString($row[$emailKey] ?? null) : null;
                    $phone = $phoneKey !== null ? $this->nullableString($row[$phoneKey] ?? null) : null;
                    $state = $stateKey !== null ? $this->nullableString($row[$stateKey] ?? null) : null;

                    if ($membershipNumber === '') {
                        throw new RuntimeException("Row {$line}: membership number (Mem_No) is missing.");
                    }

                    $fullName = $this->buildFullName($firstName, $lastName, $otherName);

                    if ($fullName === '') {
                        throw new RuntimeException("Row {$line} ({$membershipNumber}): member name is missing.");
                    }

                    $gradeName = $this->mapGrade($gradeValue, $line, $membershipNumber, $fullName, $examNo);
                    $grade = $grades[$gradeName];

                    $member = Member::query()->updateOrCreate(
                        ['membership_number' => $membershipNumber],
                        [
                            'full_name' => $fullName,
                            'email' => $email,
                            'phone' => $phone,
                            'state' => $state,
                            'membership_grade_id' => $grade->id,
                            // The induction list carries no date column; the induction took place in April 2022.
                            'registration_date' => '2022-04-01',
                        ],
                    );

                    $this->ensureRegistrationHistory($member, $grade->id);
                }
            });
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, int>  $index
     * @param  array<int, string>  $candidates
     */
    private function findHeader(array $index, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $index)) {
                return $index[$candidate];
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($header, '_');
    }

    private function buildFullName(string $firstName, string $lastName, string $otherName): string
    {
        $parts = [];

        foreach ([$firstName, $lastName, $otherName] as $part) {
            $part = preg_replace('/\s+/u', ' ', trim($part)) ?? trim($part);

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(' ', $parts);
    }

    private function mapGrade(string $value, int $line, string $membershipNumber, string $fullName, string $examNo = ''): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[\s\-]+/', '_', $normalized) ?? $normalized;

        if ($normalized === '') {
            $normalized = $this->inferGradeFromExamNumber($examNo);
        }

        return match ($normalized) {
            'member_associate', 'member_exam' => 'Associate',
            'member_research' => 'Member',
            'member_graduate' => 'Graduate',
            'graduate' => 'Graduate',
            'associate' => 'Associate',
            'member' => 'Member',
            'fellow' => 'Fellow',
            default => throw new RuntimeException(
                "Row {$line} ({$membershipNumber}, {$fullName}): unsupported or missing grade value [{$value}]."
            ),
        };
    }

    private function inferGradeFromExamNumber(string $examNo): string
    {
        $examNo = strtoupper($examNo);

        return match (true) {
            str_contains($examNo, 'GRD') => 'member_graduate',
            str_contains($examNo, 'ASS') => 'member_associate',
            default => '',
        };
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
