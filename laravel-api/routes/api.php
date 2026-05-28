<?php

use App\Http\Controllers\ActivityController;
// use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\BloodTypeController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\EducationLevelController;
use App\Http\Controllers\EducationSchoolController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeStatusController;
use App\Http\Controllers\EmployeeTypeController;
use App\Http\Controllers\EmploymentController;
use App\Http\Controllers\FamilyStatusController;
use App\Http\Controllers\FirstLoginController;
use App\Http\Controllers\GenderController;
use App\Http\Controllers\MaritalStatusController;
use App\Http\Controllers\PhaseStatusController;
use App\Http\Controllers\ProfessionController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\RegencyController;
use App\Http\Controllers\ReligionController;
use App\Http\Controllers\SchoolLevelController;
use App\Http\Controllers\SchoolProfileController;
use App\Http\Controllers\SchoolRombelController;
use App\Http\Controllers\SchoolYearController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\StatusUserController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentEntryController;
use App\Http\Controllers\StudentMajorController;
use App\Http\Controllers\StudentRombelController;
use App\Http\Controllers\StudentStatusController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TypeUserController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VillageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


// Ubah dari yang lama menjadi seperti ini:
Route::get('/check-auth', function (Request $request) {
    if (Auth::guard('sanctum')->check()) {
        return response()->json([
            'authenticated' => true,
            'user' => $request->user('sanctum')
        ], 200);
    }

    return response()->json([
        'authenticated' => false,
        'user' => null
    ], 200); // Mengirim 200 OK agar konsol tetap bersih
});
// Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
//     return $request->user();
// });
// Grup Rute Terproteksi (Hanya bisa diakses jika sudah login)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
        return $request->user();
    });
    // Route::apiResource('/new-password', NewPasswordController::class);
    Route::apiResource('/users', UserController::class);
    Route::apiResource('/status-users', StatusUserController::class);
    Route::apiResource('/type-users', TypeUserController::class);
    Route::apiResource('/firstlogin', FirstLoginController::class);
    Route::apiResource('/provinces', ProvinceController::class);
    Route::apiResource('/regencies', RegencyController::class);
    Route::apiResource('/districts', DistrictController::class);
    Route::apiResource('/villages', VillageController::class);
    Route::apiResource('/school-years', SchoolYearController::class);
    Route::apiResource('/education-levels', EducationLevelController::class);
    Route::apiResource('/semesters', SemesterController::class);
    Route::apiResource('/genders', GenderController::class);
    Route::apiResource('/religions', ReligionController::class);
    Route::apiResource('/blood-types', BloodTypeController::class);
    Route::apiResource('/professions', ProfessionController::class);
    Route::apiResource('/family-status', FamilyStatusController::class);
    Route::apiResource('/education-schools', EducationSchoolController::class);
    Route::apiResource('/student-statuses', StudentStatusController::class);
    Route::apiResource('/student-entries', StudentEntryController::class);
    Route::apiResource('/student-majors', StudentMajorController::class);
    Route::apiResource('/phase-statuses', PhaseStatusController::class);
    Route::apiResource('/students', StudentController::class);
    Route::apiResource('/school-levels', SchoolLevelController::class);
    Route::apiResource('/school-rombels', SchoolRombelController::class);
    Route::apiResource('/student-rombels', StudentRombelController::class);
    Route::apiResource('/employee', EmployeeController::class);
    Route::apiResource('/employee-statuses', EmployeeStatusController::class);
    Route::apiResource('/employee-types', EmployeeTypeController::class);
    Route::apiResource('/employments', EmploymentController::class);
    Route::apiResource('/marital-statuses', MaritalStatusController::class);
    Route::apiResource('/school-profile', SchoolProfileController::class);
    Route::apiResource('/subjects', SubjectController::class);
    Route::apiResource('/activities', ActivityController::class);

});

// Route::apiResource('/users', UserController::class);
// Route::apiResource('/status-users', StatusUserController::class);
// Route::apiResource('/type-users', TypeUserController::class);
// Route::apiResource('/firstlogin', FirstLoginController::class);
// Route::apiResource('/provinces', ProvinceController::class);
// Route::apiResource('/regencies', RegencyController::class);
// Route::apiResource('/districts', DistrictController::class);
// Route::apiResource('/villages', VillageController::class);
// Route::apiResource('/school-years', SchoolYearController::class);
// Route::apiResource('/education-levels', EducationLevelController::class);
// Route::apiResource('/semesters', SemesterController::class);
// Route::apiResource('/genders', GenderController::class);
// Route::apiResource('/religions', ReligionController::class);
// Route::apiResource('/blood-types', BloodTypeController::class);
// Route::apiResource('/professions', ProfessionController::class);
// Route::apiResource('/family-status', FamilyStatusController::class);
// Route::apiResource('/education-schools', EducationSchoolController::class);
// Route::apiResource('/student-statuses', StudentStatusController::class);
// Route::apiResource('/student-entries', StudentEntryController::class);
// Route::apiResource('/student-majors', StudentMajorController::class);
// Route::apiResource('/phase-statuses', PhaseStatusController::class);
// Route::apiResource('/students', StudentController::class);
// Route::apiResource('/school-levels', SchoolLevelController::class);
// Route::apiResource('/school-rombels', SchoolRombelController::class);
// Route::apiResource('/student-rombels', StudentRombelController::class);
