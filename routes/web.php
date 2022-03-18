<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExcelCSVController;
use App\Http\Controllers\MemberController;
 







Route::get('excel-csv-file', [ExcelCSVController::class, 'index']);
Route::post('import-excel-csv-file', [ExcelCSVController::class, 'importExcelCSV']);
Route::get('export-excel-csv-file/{slug}', [ExcelCSVController::class, 'exportExcelCSV']);

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});




Route::post('/import',[MemberController::class,'import'])->name('member.import');

Route::get('/import-form', [MemberController::class, 'importForm']);

