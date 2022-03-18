<?php

namespace App\Imports;

use App\Models\Member;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MemberImport implements ToModel, WithHeadingRow
{

    use Importable;
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new member([
            's_n' => $row['s_n'],
            'Passport' => $row['passport'],
            'firstName' => $row['firstName'],
            'LastName' => $row['LastName'],
            'OtherName' => $row['OtherName'],
            'Exam_number' => $row['Exam_number'],
            'Email' => $row['Email'],
            'Grade' => $row['Grade'],
            'License' => $row['License'],
            'Phone' => $row['Phone'],
            'Address' => $row['Address'],
            'Employer' => $row['Employer'],
            'dob' => $row['dob'],
            'State' => $row['State'],
            'Induction' => $row['Induction'],
            'Submitted' => $row['Submitted'],
            'Accepted' => $row['Accepted'],
            'Chapter' => $row['Chapter'],
            'Mem_no' => $row['Mem_no'],
        ]);
    }
}
