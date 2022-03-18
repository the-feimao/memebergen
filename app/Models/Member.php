<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Member extends Model
{
    use HasFactory;

    protected $table = 'member';

    protected $fillable = ['s_n', 'Passport', 'firstName', 'LastName','OtherName','Exam_number', 'Email', 'Phone','dob', 'State', 'Employer',
    'Accepted','Chapter', 'Mem_no'];

    public static function getMember(){
        $records = DB::table('member')->select('id','s_n', 'Passport', 'firstName', 'LastName','OtherName','Exam_number', 'Email', 'Phone','dob', 'State', 'Employer',
        'Accepted','Chapter', 'Mem_no');
        return $records;
    }
}
