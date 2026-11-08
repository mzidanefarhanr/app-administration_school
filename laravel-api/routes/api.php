<?php

use App\Http\Controllers\AbilityController;
use App\Http\Controllers\AcademicPeriodController;
use App\Http\Controllers\AccessAssignmentController;
use App\Http\Controllers\ActivityController;
// use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BloodTypeController;
use App\Http\Controllers\DashboardController;
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
use App\Http\Controllers\IncomingLetterController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LetterDispositionController;
use App\Http\Controllers\LetterSettingsController;
use App\Http\Controllers\LetterSubjectController;
use App\Http\Controllers\MaritalStatusController;
use App\Http\Controllers\OutgoingLetterController;
use App\Http\Controllers\PhaseStatusController;
use App\Http\Controllers\ProfessionController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\RegencyController;
use App\Http\Controllers\ReligionController;
use App\Http\Controllers\Reports\DapodikReconciliationController;
use App\Http\Controllers\Reports\SchoolReportController;
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
use App\Http\Controllers\StudentLockedCorrectionController;
use App\Http\Controllers\StudentMajorController;
use App\Http\Controllers\StudentMutationController;
use App\Http\Controllers\StudentProgressionController;
use App\Http\Controllers\StudentRombelController;
use App\Http\Controllers\StudentStatusController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\Tasks\OperationalTaskController;
use App\Http\Controllers\TeachingAssignmentController;
use App\Http\Controllers\TeachingJournalController;
use App\Http\Controllers\TeachingOptionController;
use App\Http\Controllers\TeachingReportController;
use App\Http\Controllers\TeachingScheduleController;
use App\Http\Controllers\TeachingScheduleExceptionController;
use App\Http\Controllers\TeachingSessionController;
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
        $user = $request->user();
        $user->loadMissing([
            'type_user',
            'status_user',
            'roles',
            'employee.employeeType',
            'employee.employeeStatus',
            'employee.employment',
            'employee.subject',
        ]);

        return $user;
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
    // Fase 06a (DEC-29) — periode akademik aktif dan lock tahun ajaran.
    Route::get('/academic/period', [AcademicPeriodController::class, 'show'])
        ->middleware('can:students.view');
    Route::put('/academic/period', [AcademicPeriodController::class, 'update'])
        ->middleware('can:master.manage');
    Route::post('/school-years/{id}/lock', [SchoolYearController::class, 'lock'])
        ->middleware('can:master.manage');
    Route::post('/school-years/{id}/unlock', [SchoolYearController::class, 'unlock'])
        ->middleware('can:master.manage');

    // Fase 06b (DEC-30) — progresi akhir tahun: preview tanpa mutasi, commit atomik ber-token.
    Route::prefix('academic/progression')->group(function () {
        Route::get('/options', [StudentProgressionController::class, 'options'])
            ->middleware('can:students.view');
        Route::get('/roster', [StudentProgressionController::class, 'roster'])
            ->middleware('can:students.view');
        Route::post('/preview', [StudentProgressionController::class, 'preview'])
            ->middleware('can:students.manage');
        Route::post('/commit', [StudentProgressionController::class, 'commit'])
            ->middleware('can:students.manage');
        Route::get('/batches', [StudentProgressionController::class, 'batches'])
            ->middleware('can:students.view');
        Route::get('/batches/{id}', [StudentProgressionController::class, 'showBatch'])
            ->middleware('can:students.view');
    });

    // Fase 06c (DEC-31) — mutasi masuk/keluar, pembatalan, rekap, dan koreksi periode terkunci.
    Route::prefix('academic/mutations')->group(function () {
        Route::get('/options', [StudentMutationController::class, 'options'])
            ->middleware('can:students.view');
        Route::get('/schools', [StudentMutationController::class, 'schools'])
            ->middleware('can:students.view');
        Route::get('/recap', [StudentMutationController::class, 'recap'])
            ->middleware('can:students.view');
        Route::get('/report', [StudentMutationController::class, 'report'])
            ->middleware('can:students.view');
        Route::get('/report/excel', [StudentMutationController::class, 'exportExcel'])
            ->middleware('can:students.view');
        Route::get('/import/template', [StudentMutationController::class, 'importTemplate'])
            ->middleware('can:students.view');
        Route::post('/import', [StudentMutationController::class, 'import'])
            ->middleware('can:students.manage');
        Route::get('/', [StudentMutationController::class, 'index'])
            ->middleware('can:students.view');
    });

    Route::get('/students/{id}/mutations', [StudentMutationController::class, 'timeline'])
        ->middleware('can:students.view');
    Route::post('/students/{id}/mutations/in', [StudentMutationController::class, 'mutationIn'])
        ->middleware('can:students.manage');
    Route::post('/students/{id}/mutations/out', [StudentMutationController::class, 'mutationOut'])
        ->middleware('can:students.manage');
    Route::post('/students/{id}/mutations/{mutation}/cancel', [StudentMutationController::class, 'cancel'])
        ->middleware('can:students.manage');

    Route::get('/students/{id}/locked-corrections', [StudentLockedCorrectionController::class, 'index'])
        ->middleware('can:students.view');
    Route::post('/students/{id}/locked-corrections', [StudentLockedCorrectionController::class, 'store'])
        ->middleware(['can:students.manage', 'can:students.correct-locked']);

    // Fase 07a (DEC-32) — agenda surat masuk, lampiran privat, edit/void berversi, dan history.
    Route::prefix('letters/incoming')->group(function () {
        Route::get('/options', [IncomingLetterController::class, 'options'])
            ->middleware('can:letters.view');
        Route::get('/', [IncomingLetterController::class, 'index'])
            ->middleware('can:letters.view');
        Route::post('/batch-void', [IncomingLetterController::class, 'batchVoid'])
            ->middleware('can:letters.manage');
        Route::get('/{letter}', [IncomingLetterController::class, 'show'])
            ->whereNumber('letter')->middleware('can:letters.view');
        Route::post('/', [IncomingLetterController::class, 'store'])
            ->middleware('can:letters.manage');
        Route::match(['put', 'patch'], '/{letter}', [IncomingLetterController::class, 'update'])
            ->whereNumber('letter')->middleware('can:letters.manage');
        Route::post('/{letter}/void', [IncomingLetterController::class, 'void'])
            ->whereNumber('letter')->middleware('can:letters.manage');
        Route::post('/{letter}/dispositions', [LetterDispositionController::class, 'store'])
            ->whereNumber('letter')->middleware('can:letters.manage');
        Route::match(['put', 'patch'], '/{letter}/dispositions/{disposition}', [LetterDispositionController::class, 'update'])
            ->whereNumber(['letter', 'disposition'])->middleware('can:letters.manage');
        Route::post('/{letter}/dispositions/{disposition}/cancel', [LetterDispositionController::class, 'cancel'])
            ->whereNumber(['letter', 'disposition'])->middleware('can:letters.manage');
        Route::post('/{letter}/attachments', [IncomingLetterController::class, 'storeAttachment'])
            ->whereNumber('letter')->middleware('can:letters.manage');
        Route::get('/{letter}/attachments/{attachment}/download', [IncomingLetterController::class, 'downloadAttachment'])
            ->whereNumber(['letter', 'attachment'])->middleware('can:letters.view');
        Route::delete('/{letter}/attachments/{attachment}', [IncomingLetterController::class, 'destroyAttachment'])
            ->whereNumber(['letter', 'attachment'])->middleware('can:letters.manage');
    });

    // Fase 07b (DEC-33) — worklist disposisi surat masuk dan opsi penerima pegawai.
    Route::prefix('letters/dispositions')->group(function () {
        Route::get('/options', [LetterDispositionController::class, 'options'])
            ->middleware('can:letters.view');
        Route::get('/recipients', [LetterDispositionController::class, 'recipients'])
            ->middleware(['can:letters.manage', 'can:employees.view']);
        Route::get('/', [LetterDispositionController::class, 'index'])
            ->middleware('can:letters.view');
    });

    // Fase 07c (DEC-34) — kop, penandatangan, dan aset privat surat bertipe.
    Route::prefix('letters/settings')->group(function () {
        Route::get('/', [LetterSettingsController::class, 'show'])
            ->middleware('can:letters.view');
        Route::post('/header', [LetterSettingsController::class, 'updateHeader'])
            ->middleware('can:letters.manage');
        Route::post('/signatory', [LetterSettingsController::class, 'updateSignatory'])
            ->middleware('can:letters.sign');
        Route::get('/assets/{asset}', [LetterSettingsController::class, 'asset'])
            ->middleware('can:letters.view');
    });

    // Fase 07b (DEC-33) — surat keluar: draft, approval/sign, nomor resmi, lampiran.
    Route::prefix('letters/outgoing')->group(function () {
        Route::get('/subjects/students', [LetterSubjectController::class, 'students'])
            ->middleware(['can:letters.manage', 'can:students.view']);
        Route::get('/subjects/student-mutations', [LetterSubjectController::class, 'mutations'])
            ->middleware(['can:letters.manage', 'can:students.view']);
        Route::get('/subjects/employees', [LetterSubjectController::class, 'employees'])
            ->middleware(['can:letters.manage', 'can:employees.view']);
        Route::get('/options', [OutgoingLetterController::class, 'options'])
            ->middleware('can:letters.view');
        Route::get('/', [OutgoingLetterController::class, 'index'])
            ->middleware('can:letters.view');
        Route::get('/{letter}', [OutgoingLetterController::class, 'show'])
            ->whereNumber('letter')->middleware('can:letters.view');
        Route::post('/', [OutgoingLetterController::class, 'store'])
            ->middleware('can:letters.manage');
        Route::match(['put', 'patch'], '/{letter}', [OutgoingLetterController::class, 'update'])
            ->whereNumber('letter')->middleware('can:letters.manage');
        Route::post('/{letter}/submit', [OutgoingLetterController::class, 'submit'])
            ->whereNumber('letter')->middleware('can:letters.manage');
        Route::post('/{letter}/approve', [OutgoingLetterController::class, 'approve'])
            ->whereNumber('letter')->middleware('can:letters.approve');
        Route::post('/{letter}/reject', [OutgoingLetterController::class, 'reject'])
            ->whereNumber('letter')->middleware('can:letters.approve');
        Route::post('/{letter}/issue', [OutgoingLetterController::class, 'issue'])
            ->whereNumber('letter')->middleware('can:letters.sign');
        Route::post('/{letter}/cancel', [OutgoingLetterController::class, 'cancel'])
            ->whereNumber('letter')->middleware('can:letters.view');
        Route::get('/{letter}/document', [OutgoingLetterController::class, 'downloadDocument'])
            ->whereNumber('letter')->middleware('can:letters.view');
        Route::get('/{letter}/document/preview', [OutgoingLetterController::class, 'previewDocument'])
            ->whereNumber('letter')->middleware('can:letters.view');
        Route::post('/{letter}/attachments', [OutgoingLetterController::class, 'storeAttachment'])
            ->whereNumber('letter')->middleware('can:letters.manage');
        Route::get('/{letter}/attachments/{attachment}/download', [OutgoingLetterController::class, 'downloadAttachment'])
            ->whereNumber(['letter', 'attachment'])->middleware('can:letters.view');
        Route::delete('/{letter}/attachments/{attachment}', [OutgoingLetterController::class, 'destroyAttachment'])
            ->whereNumber(['letter', 'attachment'])->middleware('can:letters.manage');
    });

    // Fase 08a (DEC-35) — pengajuan izin siswa dan pegawai.
    Route::prefix('leaves')->group(function () {
        Route::get('/options', [LeaveController::class, 'options']);
        Route::get('/mine', [LeaveController::class, 'mine'])
            ->middleware('can:leaves.request');
        Route::get('/subjects/students', [LeaveController::class, 'studentSubjects'])
            ->middleware(['can:leaves.manage', 'can:students.view']);
        Route::get('/subjects/employees', [LeaveController::class, 'employeeSubjects'])
            ->middleware(['can:leaves.manage', 'can:employees.view']);
        Route::get('/', [LeaveController::class, 'index'])
            ->middleware('can:leaves.view');
        Route::get('/recap', [LeaveController::class, 'recap'])
            ->middleware('can:leaves.view');
        Route::get('/{leave}', [LeaveController::class, 'show'])
            ->whereNumber('leave');
        Route::post('/', [LeaveController::class, 'store']);
        Route::match(['put', 'patch'], '/{leave}', [LeaveController::class, 'update'])
            ->whereNumber('leave');
        Route::post('/{leave}/submit', [LeaveController::class, 'submit'])
            ->whereNumber('leave');
        Route::post('/{leave}/cancel', [LeaveController::class, 'cancel'])
            ->whereNumber('leave');
        Route::post('/{leave}/approve', [LeaveController::class, 'approve'])
            ->whereNumber('leave')->middleware('can:leaves.approve');
        Route::post('/{leave}/reject', [LeaveController::class, 'reject'])
            ->whereNumber('leave')->middleware('can:leaves.approve');
        Route::post('/{leave}/attachments', [LeaveController::class, 'storeAttachment'])
            ->whereNumber('leave');
        Route::get('/{leave}/attachments/{attachment}/download', [LeaveController::class, 'downloadAttachment'])
            ->whereNumber(['leave', 'attachment']);
        Route::delete('/{leave}/attachments/{attachment}', [LeaveController::class, 'destroyAttachment'])
            ->whereNumber(['leave', 'attachment']);
    });

    // Fase 09a (DEC-37) — metadata dan versi file arsip dokumen.
    // Fase 09b (DEC-38) — preview aman, audit akses, tugas kedaluwarsa,
    // arsip/restore, dan laporan retensi read-only.
    // Filter domain dan 404 untuk objek hidden ditegakkan di service.
    Route::prefix('archives')->group(function () {
        Route::get('/options', [ArchiveController::class, 'options'])
            ->middleware('can:archives.view');
        Route::get('/subjects/{type}', [ArchiveController::class, 'subjects'])
            ->whereIn('type', array_keys((array) config('archives.subject_types')))
            ->middleware('can:archives.view');
        // Rute statis harus sebelum /{archive} (yang dibatasi whereNumber).
        Route::get('/tasks/expiring', [ArchiveController::class, 'expiringTasks'])
            ->middleware('can:archives.view');
        Route::get('/retention/report', [ArchiveController::class, 'retentionReport'])
            ->middleware('can:archives.manage');
        Route::post('/batch-archive', [ArchiveController::class, 'batchArchive'])
            ->middleware('can:archives.manage');
        Route::post('/batch-restore', [ArchiveController::class, 'batchRestore'])
            ->middleware('can:archives.manage');
        Route::get('/', [ArchiveController::class, 'index'])
            ->middleware('can:archives.view');
        Route::post('/', [ArchiveController::class, 'store'])
            ->middleware('can:archives.manage');
        Route::get('/{archive}', [ArchiveController::class, 'show'])
            ->whereNumber('archive')
            ->middleware('can:archives.view');
        Route::match(['put', 'patch'], '/{archive}', [ArchiveController::class, 'update'])
            ->whereNumber('archive')
            ->middleware('can:archives.manage');
        Route::get('/{archive}/versions', [ArchiveController::class, 'versions'])
            ->whereNumber('archive')
            ->middleware('can:archives.view');
        Route::post('/{archive}/versions', [ArchiveController::class, 'storeVersion'])
            ->whereNumber('archive')
            ->middleware('can:archives.manage');
        Route::get('/{archive}/versions/{version}/download', [ArchiveController::class, 'downloadVersion'])
            ->whereNumber(['archive', 'version'])
            ->middleware('can:archives.view');
        Route::get('/{archive}/versions/{version}/preview', [ArchiveController::class, 'previewVersion'])
            ->whereNumber(['archive', 'version'])
            ->middleware('can:archives.view');
        Route::get('/{archive}/access-logs', [ArchiveController::class, 'accessLogs'])
            ->whereNumber('archive')
            ->middleware('can:archives.view');
        Route::post('/{archive}/archive', [ArchiveController::class, 'archive'])
            ->whereNumber('archive')
            ->middleware('can:archives.manage');
        Route::post('/{archive}/restore', [ArchiveController::class, 'restore'])
            ->whereNumber('archive')
            ->middleware('can:archives.manage');
    });

    // Fase 10a (DEC-39 / DEC-10) — penugasan mengajar, jadwal mingguan + deteksi bentrok,
    // dan exception kalender (holiday / cancelled / rescheduled).
    Route::prefix('teaching')->group(function () {
        Route::get('/options', TeachingOptionController::class)
            ->middleware('can:teaching.view');

        Route::get('/assignments', [TeachingAssignmentController::class, 'index'])
            ->middleware('can:teaching.view');
        Route::post('/assignments', [TeachingAssignmentController::class, 'store'])
            ->middleware('can:teaching.manage');
        Route::match(['put', 'patch'], '/assignments/{teachingAssignment}', [TeachingAssignmentController::class, 'update'])
            ->whereNumber('teachingAssignment')
            ->middleware('can:teaching.manage');
        Route::delete('/assignments/{teachingAssignment}', [TeachingAssignmentController::class, 'destroy'])
            ->whereNumber('teachingAssignment')
            ->middleware('can:teaching.manage');

        Route::get('/schedules', [TeachingScheduleController::class, 'index'])
            ->middleware('can:teaching.view');
        Route::post('/schedules', [TeachingScheduleController::class, 'store'])
            ->middleware('can:teaching.manage');
        Route::match(['put', 'patch'], '/schedules/{teachingSchedule}', [TeachingScheduleController::class, 'update'])
            ->whereNumber('teachingSchedule')
            ->middleware('can:teaching.manage');
        Route::delete('/schedules/{teachingSchedule}', [TeachingScheduleController::class, 'destroy'])
            ->whereNumber('teachingSchedule')
            ->middleware('can:teaching.manage');

        Route::get('/exceptions', [TeachingScheduleExceptionController::class, 'index'])
            ->middleware('can:teaching.view');
        Route::post('/exceptions', [TeachingScheduleExceptionController::class, 'store'])
            ->middleware('can:teaching.manage');
        Route::match(['put', 'patch'], '/exceptions/{teachingScheduleException}', [TeachingScheduleExceptionController::class, 'update'])
            ->whereNumber('teachingScheduleException')
            ->middleware('can:teaching.manage');
        Route::delete('/exceptions/{teachingScheduleException}', [TeachingScheduleExceptionController::class, 'destroy'])
            ->whereNumber('teachingScheduleException')
            ->middleware('can:teaching.manage');

        // Fase 10b (DEC-40) — sesi harian & jurnal pembelajaran.
        Route::get('/sessions', [TeachingSessionController::class, 'index'])
            ->middleware('can:teaching.view');
        Route::get('/journals', [TeachingJournalController::class, 'index'])
            ->middleware('can:teaching.view');
        Route::get('/journals/{teachingJournal}', [TeachingJournalController::class, 'show'])
            ->whereNumber('teachingJournal')
            ->middleware('can:teaching.view');
        Route::post('/journals', [TeachingJournalController::class, 'store'])
            ->middleware('can:teaching.view');
        Route::match(['put', 'patch'], '/journals/{teachingJournal}', [TeachingJournalController::class, 'update'])
            ->whereNumber('teachingJournal')
            ->middleware('can:teaching.view');

        // Fase 10c (DEC-41) — laporan belum mengisi jurnal & ekspor CSV.
        Route::get('/reports/missing-journals', [TeachingReportController::class, 'missingJournals'])
            ->middleware('can:teaching.view');
        Route::get('/reports/missing-journals/export', [TeachingReportController::class, 'exportMissingJournals'])
            ->middleware('can:teaching.view');
    });

    // Fase 11a (DEC-42) — dashboard operasional terpadu.
    Route::prefix('dashboard')->group(function () {
        Route::get('/overview', [DashboardController::class, 'overview']);
        Route::get('/pending-tasks', [DashboardController::class, 'pendingTasks']);
        Route::get('/charts', [DashboardController::class, 'charts']);
        Route::get('/onboarding', [DashboardController::class, 'onboarding']);
    });

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
    Route::post('/students/batch-archive', [StudentController::class, 'batchArchive'])
        ->middleware('can:students.manage');
    Route::post('/students/batch-restore', [StudentController::class, 'batchRestore'])
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
    Route::post('/school-rombels/batch-delete', [SchoolRombelController::class, 'batchDelete'])
        ->middleware('can:students.manage');
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
    Route::get('/employees/teacher-options', [EmployeeController::class, 'teacherOptions'])
        ->middleware('can:employees.view');
    Route::post('/employees/{id}/restore', [EmployeeController::class, 'restore'])
        ->middleware('can:employees.manage');
    Route::post('/employees/batch-archive', [EmployeeController::class, 'batchArchive'])
        ->middleware('can:employees.manage');
    Route::post('/employees/batch-restore', [EmployeeController::class, 'batchRestore'])
        ->middleware('can:employees.manage');
    Route::post('/employees/{id}/link-account', [EmployeeController::class, 'linkAccount'])
        ->middleware('can:employees.manage');
    Route::post('/employees/{id}/unlink-account', [EmployeeController::class, 'unlinkAccount'])
        ->middleware('can:employees.manage');
    Route::post('/employees/{id}/generate-account', [EmployeeController::class, 'generateAccount'])
        ->middleware('can:employees.manage');
    Route::delete('/employees/{id}/delete-account', [EmployeeController::class, 'deleteAccount'])
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

    // Subfase 11b: Laporan Rekapitulasi Sekolah, Statistik Multi-Tahun & Rekonsiliasi Dapodik (DEC-43)
    Route::prefix('reports')->group(function () {
        Route::get('/school-statistics', [SchoolReportController::class, 'schoolStatistics'])
            ->middleware('can:students.view');
        Route::get('/demographics', [SchoolReportController::class, 'demographics'])
            ->middleware('can:students.view');
        Route::get('/dapodik-readiness', [SchoolReportController::class, 'dapodikReadiness'])
            ->middleware('can:students.view');
        Route::get('/school-statistics/export', [SchoolReportController::class, 'exportSchoolStatistics'])
            ->middleware('can:students.view');

        Route::post('/dapodik-reconciliation/compare', [DapodikReconciliationController::class, 'compare'])
            ->middleware('can:students.import');
        Route::post('/dapodik-reconciliation/export', [DapodikReconciliationController::class, 'exportReconciliation'])
            ->middleware('can:students.import');
    });

    // Subfase 11c: Tugas Operasional dan Pengingat Internal (DEC-44)
    Route::prefix('tasks')->group(function () {
        Route::get('/', [OperationalTaskController::class, 'index'])->middleware('can:tasks.view');
        Route::get('/summary', [OperationalTaskController::class, 'summary'])->middleware('can:tasks.view');
        Route::post('/reminders', [OperationalTaskController::class, 'storeReminder'])->middleware('can:tasks.manage');
        Route::get('/reminders/{reminder}', [OperationalTaskController::class, 'showReminder'])->middleware('can:tasks.view');
        Route::match(['put', 'patch'], '/reminders/{reminder}', [OperationalTaskController::class, 'updateReminder'])->middleware('can:tasks.manage');
        Route::post('/reminders/{reminder}/complete', [OperationalTaskController::class, 'completeReminder'])->middleware('can:tasks.manage');
        Route::post('/reminders/{reminder}/cancel', [OperationalTaskController::class, 'cancelReminder'])->middleware('can:tasks.manage');
        Route::delete('/reminders/{reminder}', [OperationalTaskController::class, 'destroyReminder'])->middleware('can:tasks.manage');
        Route::post('/system/dismiss', [OperationalTaskController::class, 'dismissSystemTask'])->middleware('can:tasks.manage');
    });

    // Subfase 12b (DEC-46 D8): Pelacakan status pekerjaan latar belakang (jobs progress tracking)
    Route::get('/jobs/status/{jobId}', [\App\Http\Controllers\JobStatusController::class, 'show']);
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
