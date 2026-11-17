<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class NewMembershipTemplateExport implements FromArray, WithHeadings
{
    /**
     * @return array<int, array<never, never>>
     */
    public function array(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['lastname', 'othername', 'firstname', 'Grade', 'Email', 'Phone', 'State', 'dob', 'address', 'membership_number', 'registration_date'];
    }
}
