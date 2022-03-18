<?php

namespace App\Imports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;

class UsersImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new User([
            "S/N" => $row['S/N'],
            "Passport" => $row['Passport'],
            "firstName" => $row['firstName'],
            "LastName" => $row['LastName'],
            "OtherName" => $row['OtherName'],
            "Exam_number" => $row['Exam_number'],
            "Email" => $row['Email'],
            "Phone" => $row['Phone'],
            "dob" => $row['dob'],
            "State" => $row['State'],
            "Employer" => $row['Employer'],
            "Accepted" => $row['Accepted'],
            "Chapter" => $row['Chapter'],
            "Mem_no" => $row['Mem_no'],
        ]);
    }
}
