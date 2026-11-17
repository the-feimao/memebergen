<?php

namespace App\Exports;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class MembershipRegisterExport implements FromQuery, WithColumnFormatting, WithHeadings, WithMapping
{
    /**
     * @param  Builder<Member>  $query
     */
    public function __construct(
        private readonly Builder $query,
    ) {}

    /**
     * @return Builder<Member>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @param  Member  $member
     * @return array<int, string>
     */
    public function map($member): array
    {
        return [
            $this->sanitize((string) $member->membership_number),
            $this->sanitize((string) $member->full_name),
            $this->sanitize((string) ($member->grade?->name ?? '')),
            $this->sanitize((string) ($member->email ?? '')),
            $this->sanitize((string) ($member->phone ?? '')),
            $this->sanitize((string) ($member->state ?? '')),
            $this->sanitize($member->registration_date?->toDateString() ?? ''),
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Membership Number',
            'Full Name',
            'Current Grade',
            'Email',
            'Phone',
            'State',
            'Registration Date',
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
