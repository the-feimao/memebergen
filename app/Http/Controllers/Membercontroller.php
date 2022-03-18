<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use App\Imports\MemberImport;
use Excel;


class MemberController extends Controller
{
   
    
   public function importForm()
   {
       
    return view('import-form');
   }

   public function import(Request $request){
        $file = $request->file('file');
        $import = Excel::import(new MemberImport, $file);
        dd($import);
       return back()->with('success','Member file uploaded!');

   }
}
