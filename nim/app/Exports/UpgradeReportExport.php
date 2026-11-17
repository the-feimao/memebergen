<?php

namespace App\Exports;

use App\Models\MembershipUpgrade;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class UpgradeReportExport implements FromQuery, WithColumnFormatting, WithHeadings, WithMapping
{
    /**
     * @param  Builder<MembershipUpgrade>  $query
     */
    public function __construct(
        private readonly Builder $query,
    ) {}

    /**
     * @return Builder<MembershipUpgrade>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Effective Date',
            'Membership Number',
            'Member Name',
            'Previous Grade',
            'New Grade',
            'Upgrade Type',
            'Batch Reference',
        ];
    }

    /**
     * @param  MembershipUpgrade  $upgrade
     * @return array<int, string>
     */
    public function map($upgrade): array
    {
        return [
            $this->sanitize($upgrade->effective_date?->toDateString() ?? ''),
            $this->sanitize((string) ($upgrade->member?->membership_number ?? '')),
            $this->sanitize((string) ($upgrade->member?->full_name ?? '')),
            $this->sanitize((string) ($upgrade->previousGrade?->name ?? '')),
            $this->sanitize((string) ($upgrade->newGrade?->name ?? '')),
            $this->sanitize((string) ($upgrade->upgradeType?->name ?? '')),
            $this->sanitize((string) ($upgrade->batch?->batch_reference ?? '')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
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
