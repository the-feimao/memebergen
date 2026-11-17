<?php

namespace App\Exports;

use App\Enums\MembershipBatchRowStatus;
use App\Models\MembershipBatch;
use App\Models\MembershipBatchRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class UpgradeBatchResultsExport implements FromCollection, WithColumnFormatting, WithHeadings
{
    public function __construct(
        private readonly MembershipBatch $batch,
    ) {}

    /**
     * @return Collection<int, array<int, string>>
     */
    public function collection(): Collection
    {
        $this->batch->loadMissing('upgradeType:id,name');

        $rows = MembershipBatchRow::query()
            ->where('membership_batch_id', $this->batch->getKey())
            ->with(['previousGrade:id,name', 'intendedGrade:id,name', 'member:id,full_name,membership_number'])
            ->orderBy('row_number')
            ->get();

        $upgradeTypeName = $this->batch->upgradeType?->name ?? '';

        return $rows->map(function (MembershipBatchRow $row) use ($upgradeTypeName): array {
            $membershipNumber = (string) ($row->supplied_membership_number ?? $row->allocated_membership_number ?? $row->member?->membership_number ?? '');
            $memberName = (string) ($row->member?->full_name ?? $row->supplied_full_name ?? $row->full_name ?? '');
            $previousGrade = (string) ($row->previousGrade?->name ?? '');
            $newGrade = (string) (
                $row->status === MembershipBatchRowStatus::Processed
                    ? ($row->intendedGrade?->name ?? $row->intended_grade_value ?? $row->grade_value ?? '')
                    : ($row->intendedGrade?->name ?? $row->intended_grade_value ?? $row->grade_value ?? '')
            );
            $result = match ($row->status) {
                MembershipBatchRowStatus::Processed => 'Upgraded',
                MembershipBatchRowStatus::Invalid => 'Failed: '.implode('; ', $row->validation_errors ?? []),
                MembershipBatchRowStatus::Valid, MembershipBatchRowStatus::Warning => 'Not processed: '.$row->status->value,
            };

            return [
                $this->sanitize($membershipNumber),
                $this->sanitize($memberName),
                $this->sanitize($previousGrade),
                $this->sanitize($newGrade),
                $this->sanitize($upgradeTypeName),
                $this->sanitize($result),
            ];
        });
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Membership Number',
            'Member Name',
            'Previous Grade',
            'New Grade',
            'Upgrade Type',
            'Processing Result',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
        ];
    }

    private function sanitize(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
