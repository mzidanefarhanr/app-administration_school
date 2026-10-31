<?php

use App\Http\Controllers\AbilityController;
use App\Http\Controllers\AccessAssignmentController;
// use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BloodTypeController;
use App\Http\Controllers\DistrictController;
use App\Http\Controllers\EducationLevelController;
use App\Http\Controllers\EducationSchoolController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDataQualityController;
use App\Http\Controllers\EmployeeExportController;
use App\Http\Controllers\EmployeeStatusController;
use App\Http\Controllers\EmployeeTypeController;
use App\Http\Controllers\EmploymentController;
use App\Http\Controllers\FamilyStatusController;
use App\Http\Controllers\FirstLoginController;
use App\Http\Controllers\GenderController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MaritalStatusController;
use App\Http\Controllers\PhaseStatusController;
use App\Http\Controllers\ProfessionController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\RegencyController;
use App\Http\Controllers\ReligionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchoolLevelController;
use App\Http\Controllers\SchoolProfileController;
use App\Http\Controllers\SchoolRombelController;
use App\Http\Controllers\SchoolYearController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\StatusUserController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDataQualityController;
use App\Http\Controllers\StudentEntryController;
use App\Http\Controllers\StudentExportController;
use App\Http\Controllers\StudentMajorController;
use App\Http\Controllers\StudentRombelController;
use App\Http\Controllers\StudentStatusController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TypeUserController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VillageController;
use Illuminate\Http\Request;
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
// Fase 01a: meminimalkan payload agar tidak mengekspos objek user penuh /
// atribut sensitif. Cukup status + identitas minimal untuk kebutuhan UI.
Route::get('/check-auth', function (Request $request) {
    $user = $request->user('sanctum');

    if ($user) {
        return response()->json([
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'type_user_id' => $user->type_user_id,
                'status_user_id' => $user->status_user_id,
                'status_active' => (bool) $user->status_active,
                'abilities' => $user->abilityNames(),
                'roles' => $user->roles->pluck('slug')->all(),
            ],
        ], 200);
    }

    return response()->json([
        'authenticated' => false,
        'user' => null,
    ], 200); // Mengirim 200 OK agar konsol tetap bersih
});
// Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
//     return $request->user();
// });
// Grup Rute Terproteksi (Hanya bisa diakses jika sudah login)
// `active` menegakkan status akun pada request berikutnya, termasuk sesi aktif.
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::middleware(['auth:sanctum', 'active'])->get('/user', function (Request $request) {
        return $request->user();
    });

    // Fase 01a — pengelolaan peran, kemampuan, dan penetapan peran.
    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('can:roles.view');
    Route::get('/roles/{role}', [RoleController::class, 'show'])
        ->middleware('can:roles.view');
    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('can:roles.manage');
    Route::match(['put', 'patch'], '/roles/{role}', [RoleController::class, 'update'])
        ->middleware('can:roles.manage');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('can:roles.manage');

    Route::get('/abilities', [AbilityController::class, 'index'])
        ->middleware('can:roles.view');
    Route::get('/abilities/{ability}', [AbilityController::class, 'show'])
        ->middleware('can:roles.view');

    Route::post('/users/{user}/roles', [AccessAssignmentController::class, 'assignRole'])
        ->middleware('can:access.assign');
    Route::delete('/users/{user}/roles/{role}', [AccessAssignmentController::class, 'revokeRole'])
        ->middleware('can:access.assign');

    // Route::apiResource('/new-password', NewPasswordController::class);
    Route::apiResource('/users', UserController::class);
    Route::apiResource('/status-users', StatusUserController::class)
        ->only(['index', 'show'])->middleware('can:master.view');
    Route::apiResource('/status-users', StatusUserController::class)
        ->except(['index', 'show'])->middleware('can:master.manage');
    Route::apiResource('/type-users', TypeUserController::class)
        ->only(['index', 'show'])->middleware('can:master.view');
    Route::apiResource('/type-users', TypeUserController::class)
        ->except(['index', 'show'])->middleware('can:master.manage');
    // Fase 01a: firstlogin hanya untuk akun actor sendiri (update saja).
    Route::match(['put', 'patch'], '/firstlogin/{firstlogin}', [FirstLoginController::class, 'update']);
    // Fase 01a — penegakan kemampuan pada endpoint master/referensi.
    // Read = master.view, Write = master.manage.
    $masterResources = [
        '/provinces' => ProvinceController::class,
        '/regencies' => RegencyController::class,
        '/districts' => DistrictController::class,
        '/villages' => VillageController::class,
        '/school-years' => SchoolYearController::class,
        '/education-levels' => EducationLevelController::class,
        '/semesters' => SemesterController::class,
        '/genders' => GenderController::class,
        '/religions' => ReligionController::class,
        '/blood-types' => BloodTypeController::class,
        '/professions' => ProfessionController::class,
        '/family-status' => FamilyStatusController::class,
        '/education-schools' => EducationSchoolController::class,
        '/student-statuses' => StudentStatusController::class,
        '/student-entries' => StudentEntryController::class,
        '/student-majors' => StudentMajorController::class,
        '/phase-statuses' => PhaseStatusController::class,
    ];

    foreach ($masterResources as $uri => $controller) {
        Route::apiResource($uri, $controller)
            ->only(['index', 'show'])
            ->middleware('can:master.view');

        Route::apiResource($uri, $controller)
            ->except(['index', 'show'])
            ->middleware('can:master.manage');
    }

    // Fase 05a (DEC-25) - impor siswa CSV: preview tanpa mutasi, commit all-or-nothing.
    // Template hanya berisi header, jadi cukup students.view (lihat PHASE-05-PLAN 5.2).
    Route::get('/imports/students/template', [ImportController::class, 'studentsTemplate'])
        ->middleware('can:students.view');
    Route::post('/imports/students', [ImportController::class, 'importStudents'])
        ->middleware('can:students.import');
    Route::get('/imports/{batch}', [ImportController::class, 'show'])
        ->middleware('can:students.import');
    Route::post('/imports/{batch}/commit', [ImportController::class, 'commit'])
        ->middleware('can:students.import');
    Route::get('/imports/{batch}/errors', [ImportController::class, 'errors'])
        ->middleware('can:students.import');

    // Fase 05b (DEC-26) - impor pegawai CSV: match NIK, preview tanpa mutasi.
    // Template hanya berisi header, jadi cukup employees.view.
    Route::get('/imports/employees/template', [ImportController::class, 'employeesTemplate'])
        ->middleware('can:employees.view');
    Route::post('/imports/employees', [ImportController::class, 'importEmployees'])
        ->middleware('can:employees.import');
    Route::get('/imports/employees/{batch}', [ImportController::class, 'showEmployee'])
        ->middleware('can:employees.import');
    Route::post('/imports/employees/{batch}/commit', [ImportController::class, 'commitEmployee'])
        ->middleware('can:employees.import');
    Route::get('/imports/employees/{batch}/errors', [ImportController::class, 'errorsEmployee'])
        ->middleware('can:employees.import');

    // Fase 05c (DEC-28) — pemeriksaan kelengkapan dan status review.
    Route::prefix('data-quality/students')->group(function () {
        Route::get('/summary', [StudentDataQualityController::class, 'summary'])
            ->middleware('can:students.view');
        Route::get('/issues', [StudentDataQualityController::class, 'issues'])
            ->middleware('can:students.view');
        Route::put('/reviews/{issueKey}', [StudentDataQualityController::class, 'review'])
            ->middleware('can:students.manage');
    });

    Route::prefix('data-quality/employees')->group(function () {
        Route::get('/summary', [EmployeeDataQualityController::class, 'summary'])
            ->middleware('can:employees.view');
        Route::get('/issues', [EmployeeDataQualityController::class, 'issues'])
            ->middleware('can:employees.view');
        Route::put('/reviews/{issueKey}', [EmployeeDataQualityController::class, 'review'])
            ->middleware('can:employees.manage');
    });

    // Fase 01a — ekspor siswa (CSV) terlindungi: students.view + students.export.
    Route::get('/students/export', [StudentExportController::class, 'export'])
        ->middleware('can:students.view');

    Route::post('/students/{id}/restore', [StudentController::class, 'restore'])
        ->middleware('can:students.manage');

    // Subfase 03d (DEC-23) - Penempatan dan perpindahan rombel siswa
    Route::post('/students/{id}/assign-rombel', [StudentRombelController::class, 'assignRombel'])
        ->middleware('can:students.manage');
    Route::post('/students/{id}/transfer-rombel', [StudentRombelController::class, 'transferRombel'])
        ->middleware('can:students.manage');

    Route::apiResource('/students', StudentController::class)
        ->only(['index', 'show'])->middleware('can:students.view');
    Route::apiResource('/students', StudentController::class)
        ->except(['index', 'show'])->middleware('can:students.manage');
    Route::apiResource('/school-levels', SchoolLevelController::class)
        ->only(['index', 'show'])->middleware('can:master.view');
    Route::apiResource('/school-levels', SchoolLevelController::class)
        ->except(['index', 'show'])->middleware('can:master.manage');
    Route::apiResource('/school-rombels', SchoolRombelController::class)
        ->only(['index', 'show'])->middleware('can:students.view');
    Route::apiResource('/school-rombels', SchoolRombelController::class)
        ->except(['index', 'show'])->middleware('can:students.manage');
    Route::apiResource('/student-rombels', StudentRombelController::class)
        ->only(['index', 'show'])->middleware('can:students.view');
    Route::apiResource('/student-rombels', StudentRombelController::class)
        ->except(['index', 'show'])->middleware('can:students.manage');
    // Subfase 04b/05b (DEC-24, DEC-26) - Direktori pegawai, ekspor, arsip, relasi akun
    Route::get('/employees/export/options', [EmployeeExportController::class, 'options'])
        ->middleware('can:employees.view');
    Route::get('/employees/export', [EmployeeExportController::class, 'export'])
        ->middleware('can:employees.view');
    Route::get('/employees/account-candidates', [EmployeeController::class, 'accountCandidates'])
        ->middleware('can:employees.manage');
    Route::post('/employees/{id}/restore', [EmployeeController::class, 'restore'])
        ->middleware('can:employees.manage');
    Route::post('/employees/{id}/link-account', [EmployeeController::class, 'linkAccount'])
        ->middleware('can:employees.manage');
    Route::post('/employees/{id}/unlink-account', [EmployeeController::class, 'unlinkAccount'])
        ->middleware('can:employees.manage');

    Route::apiResource('/employees', EmployeeController::class)
        ->only(['index', 'show'])->middleware('can:employees.view');
    Route::apiResource('/employees', EmployeeController::class)
        ->except(['index', 'show'])->middleware('can:employees.manage');

    // Alias deprecated untuk consumer lama; dipertahankan sampai migrasi UI teruji.
    Route::apiResource('/employee', EmployeeController::class)
        ->only(['index', 'show'])->middleware('can:employees.view');
    Route::apiResource('/employee', EmployeeController::class)
        ->except(['index', 'show'])->middleware('can:employees.manage');
    Route::apiResource('/employee-statuses', EmployeeStatusController::class);
    Route::apiResource('/employee-types', EmployeeTypeController::class);
    Route::apiResource('/employments', EmploymentController::class);
    Route::apiResource('/marital-statuses', MaritalStatusController::class);
    Route::apiResource('/school-profile', SchoolProfileController::class);
    Route::apiResource('/subjects', SubjectController::class);
    Route::get('/activities', [ActivityController::class, 'index'])->middleware('can:audit.view');
    Route::get('/activities/{activity}', [ActivityController::class, 'show'])->middleware('can:audit.view');
    Route::post('/activities', [ActivityController::class, 'store']);
    Route::match(['put', 'patch'], '/activities/{activity}', [ActivityController::class, 'update']);
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy']);
    Route::get('/backups', [BackupController::class, 'index'])->middleware('can:backup.manage');
    Route::get('/backups/status', [BackupController::class, 'status'])->middleware('can:backup.manage');
    Route::get('/backups/{filename}/download', [BackupController::class, 'download'])
        ->where('filename', '.*')
        ->middleware('can:backup.manage');

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
