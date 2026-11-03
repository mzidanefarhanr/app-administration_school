<?php

use App\Models\AcademicSetting;
use App\Models\Activity;
use App\Models\District;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\Employment;
use App\Models\Gender;
use App\Models\MaritalStatus;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Religion;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\StudentMajor;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TeachingJournal;
use App\Models\TeachingSchedule;
use App\Models\TeachingScheduleException;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    // Tuesday, 2026-11-03 09:00:00 WIB (grace deadline = 2026-10-27)
    Carbon::setTestNow(Carbon::create(2026, 11, 3, 9, 0, 0, 'Asia/Jakarta'));

    $this->seedAccessReferenceData();

    $this->admin = $this->makeUserWithRole('admin-sistem');
    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->vicePrincipal = $this->makeUserWithRole('wakil-kesiswaan');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->teacherUserOne = $this->makeUserWithRole('guru', ['username' => 'guru.one']);
    $this->teacherUserTwo = $this->makeUserWithRole('guru', ['username' => 'guru.two']);
    $this->unlinkedTeacherUser = $this->makeUserWithRole('guru', ['username' => 'guru.unlinked']);
    $this->plainUser = $this->makeUserWithRole();

    $province = Province::firstOrCreate(['name' => 'DKI Jakarta']);
    $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Utara']);
    $district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Koja']);

    $gender = Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $religion = Religion::firstOrCreate(['name' => 'Islam']);
    $maritalStatus = MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);
    $status = EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap Yayasan'], ['abbreviation' => 'PTY']);
    $this->teacherType = EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $this->staffType = EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
    $employment = Employment::firstOrCreate(['name' => 'Tetap Yayasan'], ['description' => 'Tetap']);

    $this->mathSubject = Subject::firstOrCreate(['name' => 'Mathematics'], ['description' => 'Mathematics']);
    $this->physicsSubject = Subject::firstOrCreate(['name' => 'Physics'], ['description' => 'Physics']);

    $baseEmployee = [
        'employee_status_id' => $status->id,
        'employment_id' => $employment->id,
        'subject_id' => $this->mathSubject->id,
        'religion_id' => $religion->id,
        'district_id' => $district->id,
        'marital_status_id' => $maritalStatus->id,
        'gender_id' => $gender->id,
        'tmt_employee' => '2020-01-01',
    ];

    $this->teacherOne = Employee::create(array_merge($baseEmployee, [
        'name' => 'Synthetic Teacher Alpha',
        'nuptk' => '2001000000000001',
        'employee_type_id' => $this->teacherType->id,
        'user_id' => $this->teacherUserOne->id,
    ]));

    $this->teacherTwo = Employee::create(array_merge($baseEmployee, [
        'name' => 'Synthetic Teacher Beta',
        'nuptk' => '2001000000000002',
        'employee_type_id' => $this->teacherType->id,
        'subject_id' => $this->physicsSubject->id,
        'user_id' => $this->teacherUserTwo->id,
    ]));

    $this->activeYear = SchoolYear::create([
        'name' => '2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);

    $this->lockedYear = SchoolYear::create([
        'name' => '2024/2025',
        'start_date' => '2024-07-01',
        'end_date' => '2025-06-30',
        'locked_at' => now(),
        'locked_by' => $this->admin->id,
        'lock_reason' => 'Closed historical school year',
    ]);

    $this->semesterOne = Semester::firstOrCreate(['name' => 'I'], ['name_other' => 'Ganjil']);

    AcademicSetting::query()->updateOrCreate(
        ['id' => AcademicSetting::SINGLETON_ID],
        [
            'active_school_year_id' => $this->activeYear->id,
            'active_semester_id' => $this->semesterOne->id,
            'updated_by' => $this->admin->id,
        ]
    );

    $this->level = SchoolLevel::firstOrCreate(['name' => 'X'], ['name_other' => 'Sepuluh']);
    $this->major = StudentMajor::firstOrCreate(['name' => 'MIPA'], ['abbreviation' => 'MIPA']);

    $this->classA = SchoolRombel::create([
        'name' => 'X-MIPA-1',
        'school_year_id' => $this->activeYear->id,
        'school_level_id' => $this->level->id,
        'student_major_id' => $this->major->id,
        'class_teach_employee_id' => $this->teacherOne->id,
        'version' => 1,
    ]);

    $this->classB = SchoolRombel::create([
        'name' => 'X-MIPA-2',
        'school_year_id' => $this->activeYear->id,
        'school_level_id' => $this->level->id,
        'student_major_id' => $this->major->id,
        'class_teach_employee_id' => $this->teacherTwo->id,
        'version' => 1,
    ]);

    $this->lockedYearClass = SchoolRombel::create([
        'name' => 'X-2024',
        'school_year_id' => $this->lockedYear->id,
        'school_level_id' => $this->level->id,
        'student_major_id' => $this->major->id,
        'version' => 1,
    ]);

    $this->assignmentOne = TeachingAssignment::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classA->id,
        'subject_id' => $this->mathSubject->id,
        'employee_id' => $this->teacherOne->id,
        'hours_per_week' => 4,
        'status' => TeachingAssignment::STATUS_ACTIVE,
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    $this->assignmentTwo = TeachingAssignment::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classB->id,
        'subject_id' => $this->physicsSubject->id,
        'employee_id' => $this->teacherTwo->id,
        'hours_per_week' => 4,
        'status' => TeachingAssignment::STATUS_ACTIVE,
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    // Monday (day_of_week = 1): 2026-11-02, 2026-10-26, 2026-10-19
    $this->mondayScheduleOne = TeachingSchedule::create([
        'teaching_assignment_id' => $this->assignmentOne->id,
        'day_of_week' => 1,
        'period_start' => 1,
        'period_end' => 2,
        'start_time' => '07:00',
        'end_time' => '08:30',
        'room' => 'R-101',
        'room_normalized' => 'R-101',
        'status' => TeachingSchedule::STATUS_ACTIVE,
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    // Tuesday (day_of_week = 2): 2026-11-03 (today)
    $this->tuesdayScheduleOne = TeachingSchedule::create([
        'teaching_assignment_id' => $this->assignmentOne->id,
        'day_of_week' => 2,
        'period_start' => 1,
        'period_end' => 2,
        'start_time' => '07:00',
        'end_time' => '08:30',
        'room' => 'R-101',
        'room_normalized' => 'R-101',
        'status' => TeachingSchedule::STATUS_ACTIVE,
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    $this->tuesdayScheduleTwo = TeachingSchedule::create([
        'teaching_assignment_id' => $this->assignmentTwo->id,
        'day_of_week' => 2,
        'period_start' => 3,
        'period_end' => 4,
        'start_time' => '08:30',
        'end_time' => '10:00',
        'room' => 'R-102',
        'room_normalized' => 'R-102',
        'status' => TeachingSchedule::STATUS_ACTIVE,
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
});

// ─── T1: Route Registration & RBAC Matrix ────────────────────────────────────
test('T1 registers 18 teaching routes and enforces RBAC abilities for sessions and journals', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/teaching'))
        ->values();

    expect($routes)->toHaveCount(18);

    // Unauthenticated -> 401
    $this->getJson('/api/teaching/sessions')->assertStatus(401);
    $this->getJson('/api/teaching/journals')->assertStatus(401);
    $this->postJson('/api/teaching/journals', [])->assertStatus(401);

    // Plain user without teaching.view -> 403
    Sanctum::actingAs($this->plainUser);
    $this->getJson('/api/teaching/sessions?school_year_id='.$this->activeYear->id.'&semester_id='.$this->semesterOne->id)
        ->assertStatus(403);
    $this->getJson('/api/teaching/journals')->assertStatus(403);
    $this->postJson('/api/teaching/journals', [])->assertStatus(403);

    // Principal (kepala-sekolah) has teaching.view ONLY -> 200 on GET, 403 on POST/PUT journals
    Sanctum::actingAs($this->principal);
    $this->getJson('/api/teaching/options')
        ->assertOk()
        ->assertJsonPath('data.can.view', true)
        ->assertJsonPath('data.can.manage', false)
        ->assertJsonPath('data.can.create_journals', false)
        ->assertJsonPath('data.can.update_journals', false);

    $this->getJson('/api/teaching/sessions?school_year_id='.$this->activeYear->id.'&semester_id='.$this->semesterOne->id)
        ->assertOk();
    $this->getJson('/api/teaching/journals')->assertOk();
    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->tuesdayScheduleOne->id,
        'session_date' => '2026-11-03',
        'topic' => 'Linear Equations',
        'method' => 'lecture',
        'summary' => 'Attempted by principal without create_journals ability.',
    ])->assertStatus(403);

    // Teacher (guru) has teaching.view + teaching.journals.create + teaching.journals.update + self_employee
    Sanctum::actingAs($this->teacherUserOne);
    $this->getJson('/api/teaching/options')
        ->assertOk()
        ->assertJsonPath('data.can.view', true)
        ->assertJsonPath('data.can.manage', false)
        ->assertJsonPath('data.can.create_journals', true)
        ->assertJsonPath('data.can.update_journals', true)
        ->assertJsonPath('data.self_employee.id', $this->teacherOne->id);
});

// ─── T2: Deterministic Daily Session Evaluation ──────────────────────────────
test('T2 evaluates regular, holiday, cancelled, and rescheduled sessions deterministically', function () {
    // 1. Holiday on Monday 2026-10-19 (whole school)
    TeachingScheduleException::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => TeachingScheduleException::TYPE_HOLIDAY,
        'exception_date' => '2026-10-19',
        'school_rombel_id' => null,
        'reason' => 'School Anniversary Holiday',
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    // 2. Cancelled on Monday 2026-10-26 for mondayScheduleOne
    TeachingScheduleException::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => TeachingScheduleException::TYPE_CANCELLED,
        'exception_date' => '2026-10-26',
        'school_rombel_id' => $this->classA->id,
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'reason' => 'Class field trip',
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    // 3. Rescheduled on Monday 2026-11-02 -> moved to Wednesday 2026-11-04 Period 5-6 (10:15-11:45) with substitute teacherTwo
    TeachingScheduleException::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => TeachingScheduleException::TYPE_RESCHEDULED,
        'exception_date' => '2026-11-02',
        'school_rombel_id' => $this->classA->id,
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'rescheduled_date' => '2026-11-04',
        'rescheduled_period_start' => 5,
        'rescheduled_period_end' => 6,
        'rescheduled_start_time' => '10:15',
        'rescheduled_end_time' => '11:45',
        'rescheduled_room' => 'LAB-FIS',
        'rescheduled_room_normalized' => 'LAB-FIS',
        'substitute_employee_id' => $this->teacherTwo->id,
        'reason' => 'Teacher workshop on Monday',
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    Sanctum::actingAs($this->operator);

    // Check Holiday (2026-10-19)
    $holidayRes = $this->getJson('/api/teaching/sessions?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date' => '2026-10-19',
    ]))->assertOk();
    expect($holidayRes->json('summary.effective_sessions'))->toBe(0)
        ->and($holidayRes->json('summary.excluded_slots'))->toBe(1)
        ->and($holidayRes->json('data.0.session_status'))->toBe('holiday')
        ->and($holidayRes->json('data.0.is_effective'))->toBeFalse();

    // Check Cancelled (2026-10-26)
    $cancelledRes = $this->getJson('/api/teaching/sessions?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date' => '2026-10-26',
    ]))->assertOk();
    expect($cancelledRes->json('summary.effective_sessions'))->toBe(0)
        ->and($cancelledRes->json('data.0.session_status'))->toBe('cancelled')
        ->and($cancelledRes->json('data.0.is_effective'))->toBeFalse();

    // Check Rescheduled Out (2026-11-02)
    $rescheduledOutRes = $this->getJson('/api/teaching/sessions?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date' => '2026-11-02',
    ]))->assertOk();
    expect($rescheduledOutRes->json('summary.effective_sessions'))->toBe(0)
        ->and($rescheduledOutRes->json('data.0.session_status'))->toBe('rescheduled_out')
        ->and($rescheduledOutRes->json('data.0.is_effective'))->toBeFalse();

    // Check Rescheduled In on Wednesday 2026-11-04
    $rescheduledInRes = $this->getJson('/api/teaching/sessions?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date' => '2026-11-04',
    ]))->assertOk();
    expect($rescheduledInRes->json('summary.effective_sessions'))->toBe(1)
        ->and($rescheduledInRes->json('data.0.source_type'))->toBe('rescheduled')
        ->and($rescheduledInRes->json('data.0.session_status'))->toBe('effective')
        ->and($rescheduledInRes->json('data.0.is_effective'))->toBeTrue()
        ->and($rescheduledInRes->json('data.0.effective_employee_id'))->toBe($this->teacherTwo->id)
        ->and($rescheduledInRes->json('data.0.is_substitute'))->toBeTrue()
        ->and($rescheduledInRes->json('data.0.effective_room'))->toBe('LAB-FIS');

    // Check Regular Effective Sessions on Tuesday 2026-11-03 (today)
    $regularRes = $this->getJson('/api/teaching/sessions?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date' => '2026-11-03',
    ]))->assertOk();
    expect($regularRes->json('summary.effective_sessions'))->toBe(2)
        ->and($regularRes->json('summary.missing_journals'))->toBe(2)
        ->and($regularRes->json('summary.recorded_journals'))->toBe(0);
});

// ─── T3: Teacher Self-Service Journal Creation within Grace Window ───────────
test('T3 teacher can record a teaching journal for their own effective session within the 7-day grace window', function () {
    Sanctum::actingAs($this->teacherUserOne);

    $res = $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->tuesdayScheduleOne->id,
        'session_date' => '2026-11-03',
        'topic' => 'Sistem Persamaan Linear Tiga Variabel',
        'method' => 'discussion',
        'summary' => 'Siswa berdiskusi kelompok menyelesaikan 4 soal kontekstual SPLTV dan mempresentasikan hasilnya.',
        'notes' => '2 siswa izin sakit, diberikan tugas latihan mandiri.',
    ])->assertCreated();

    expect($res->json('idempotent_replay'))->toBeFalse()
        ->and($res->json('data.version'))->toBe(1)
        ->and($res->json('data.teacher_employee_id'))->toBe($this->teacherOne->id)
        ->and($res->json('data.source_type'))->toBe('regular')
        ->and($res->json('data.actual_start_time'))->toBe('07:00')
        ->and($res->json('data.actual_end_time'))->toBe('08:30');

    // Verify GET /api/teaching/sessions now reflects 1 recorded and 1 missing on 2026-11-03 (when scope=all)
    $sessionsRes = $this->getJson('/api/teaching/sessions?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date' => '2026-11-03',
        'scope' => 'all',
    ]))->assertOk();

    expect($sessionsRes->json('summary.recorded_journals'))->toBe(1)
        ->and($sessionsRes->json('summary.missing_journals'))->toBe(1);
});

// ─── T4: Idempotent Replay (200 OK) vs Conflicting Payload (409 Conflict) ────
test('T4 deduplicates identical POST payloads with 200 OK and rejects differing duplicate POST payloads with 409 Conflict', function () {
    Sanctum::actingAs($this->teacherUserOne);

    $payload = [
        'teaching_schedule_id' => $this->tuesdayScheduleOne->id,
        'session_date' => '2026-11-03',
        'topic' => 'Fungsi Kuadrat & Grafik Parabola',
        'method' => 'lecture',
        'summary' => 'Penjelasan sifat-sifat grafik fungsi kuadrat berdasarkan nilai diskriminan dan titik puncak.',
        'notes' => 'Tidak ada kendala.',
    ];

    $firstRes = $this->postJson('/api/teaching/journals', $payload)->assertCreated();
    $journalId = $firstRes->json('data.id');

    // Identical replay -> 200 OK without creating a second row or incrementing version
    $replayRes = $this->postJson('/api/teaching/journals', $payload)->assertOk();
    expect($replayRes->json('idempotent_replay'))->toBeTrue()
        ->and($replayRes->json('data.id'))->toBe($journalId)
        ->and($replayRes->json('data.version'))->toBe(1)
        ->and(TeachingJournal::count())->toBe(1);

    // Differing payload on the same (teaching_schedule_id, session_date) -> 409 Conflict with latest
    $conflictRes = $this->postJson('/api/teaching/journals', array_merge($payload, [
        'topic' => 'Topik Berbeda yang Bentrok',
    ]))->assertStatus(409);

    expect($conflictRes->json('conflict'))->toBeTrue()
        ->and($conflictRes->json('latest.id'))->toBe($journalId)
        ->and($conflictRes->json('latest.topic'))->toBe('Fungsi Kuadrat & Grafik Parabola');
});

// ─── T5: Ownership Enforcement & Unlinked Teacher Account Rejection ──────────
test('T5 forbids a teacher from recording or updating another teachers journal or acting without a linked Pendidik profile', function () {
    // Teacher One tries to record a journal for Teacher Two's schedule -> 403
    Sanctum::actingAs($this->teacherUserOne);
    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->tuesdayScheduleTwo->id,
        'session_date' => '2026-11-03',
        'topic' => 'Hukum Newton tentang Gerak',
        'method' => 'lecture',
        'summary' => 'Percobaan pengisian jurnal milik guru lain yang harus ditolak 403.',
    ])->assertStatus(403);

    // Unlinked teacher user tries to record a journal -> 403
    Sanctum::actingAs($this->unlinkedTeacherUser);
    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->tuesdayScheduleOne->id,
        'session_date' => '2026-11-03',
        'topic' => 'Trigonometri Dasar',
        'method' => 'lecture',
        'summary' => 'Percobaan pengisian oleh akun guru yang belum ditautkan ke pegawai.',
    ])->assertStatus(403);

    // Teacher Two records their own journal
    Sanctum::actingAs($this->teacherUserTwo);
    $created = $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->tuesdayScheduleTwo->id,
        'session_date' => '2026-11-03',
        'topic' => 'Hukum Newton tentang Gerak',
        'method' => 'demonstration',
        'summary' => 'Demonstrasi hukum kelembaman dan percepatan benda di kelas X-MIPA-2.',
    ])->assertCreated();

    $journalId = $created->json('data.id');

    // Teacher One tries to update Teacher Two's journal -> 403
    Sanctum::actingAs($this->teacherUserOne);
    $this->putJson("/api/teaching/journals/{$journalId}", [
        'expected_version' => 1,
        'topic' => 'Perubahan Tidak Sah oleh Guru Lain',
    ])->assertStatus(403);
});

// ─── T6: Substitute Teacher Authorization on Rescheduled Sessions ────────────
test('T6 allows the substitute teacher to record and update a rescheduled session journal while blocking the original teacher', function () {
    // Reschedule mondayScheduleOne (Teacher One) from Monday 2026-11-02 to Tuesday 2026-11-03 Period 5-6 with Substitute Teacher Two
    $exception = TeachingScheduleException::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => TeachingScheduleException::TYPE_RESCHEDULED,
        'exception_date' => '2026-11-02',
        'school_rombel_id' => $this->classA->id,
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'rescheduled_date' => '2026-11-03',
        'rescheduled_period_start' => 5,
        'rescheduled_period_end' => 6,
        'rescheduled_start_time' => '10:15',
        'rescheduled_end_time' => '11:45',
        'rescheduled_room' => 'R-105',
        'rescheduled_room_normalized' => 'R-105',
        'substitute_employee_id' => $this->teacherTwo->id,
        'reason' => 'Teacher One on official duty',
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    // Original Teacher One is blocked (403) because Teacher Two is the effective substitute teacher
    Sanctum::actingAs($this->teacherUserOne);
    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'session_date' => '2026-11-03',
        'topic' => 'Matriks & Determinan',
        'method' => 'lecture',
        'summary' => 'Guru asal mencoba mengisi sesi yang sudah dialihkan ke guru pengganti.',
    ])->assertStatus(403);

    // Substitute Teacher Two succeeds (201 Created)
    Sanctum::actingAs($this->teacherUserTwo);
    $createRes = $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'session_date' => '2026-11-03',
        'topic' => 'Matriks & Determinan (Kelas Pengganti)',
        'method' => 'assignment',
        'summary' => 'Guru pengganti mendampingi latihan soal determinan matriks ordo 2x2 dan 3x3.',
    ])->assertCreated();

    expect($createRes->json('data.source_type'))->toBe('rescheduled')
        ->and($createRes->json('data.teaching_schedule_exception_id'))->toBe($exception->id)
        ->and($createRes->json('data.teacher_employee_id'))->toBe($this->teacherTwo->id)
        ->and($createRes->json('data.actual_start_time'))->toBe('10:15')
        ->and($createRes->json('data.actual_end_time'))->toBe('11:45');
});

// ─── T7: Future Date Rejection, 7-Day Grace Window & Admin Correction Reason ─
test('T7 rejects future dates and enforces the 7-day grace window for teachers while allowing admin backfill with correction_reason', function () {
    Sanctum::actingAs($this->teacherUserOne);

    // 1. Future date (Next Monday 2026-11-09 > today 2026-11-03) -> 422 even for admin/teacher
    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'session_date' => '2026-11-09',
        'topic' => 'Topik Minggu Depan',
        'method' => 'lecture',
        'summary' => 'Percobaan mengisi jurnal untuk tanggal masa depan yang belum terjadi.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['session_date']);

    // 2. Past grace window (Monday 2026-10-19 < deadline 2026-10-27) by regular teacher -> 422
    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'session_date' => '2026-10-19',
        'topic' => 'Eksponen & Logaritma',
        'method' => 'lecture',
        'summary' => 'Percobaan pengisian mandiri oleh guru setelah lewat batas waktu 7 hari.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['session_date']);

    // 3. Operator (teaching.manage) tries past grace window WITHOUT correction_reason -> 422
    Sanctum::actingAs($this->operator);
    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'session_date' => '2026-10-19',
        'topic' => 'Eksponen & Logaritma',
        'method' => 'lecture',
        'summary' => 'Operator mengisi jurnal susulan lewat batas 7 hari tanpa alasan koreksi.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['correction_reason']);

    // 4. Operator WITH valid correction_reason (10-255 chars) -> 201 Created
    $backfillRes = $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'session_date' => '2026-10-19',
        'topic' => 'Eksponen & Logaritma',
        'method' => 'lecture',
        'summary' => 'Pembahasan sifat-sifat logaritma dan penyelesaian persamaan logaritma.',
        'correction_reason' => 'Backfill atas berita acara manual guru yang terkendala jaringan.',
    ])->assertCreated();

    expect($backfillRes->json('data.correction_reason'))->toBe('Backfill atas berita acara manual guru yang terkendala jaringan.')
        ->and($backfillRes->json('data.corrected_by'))->toBe($this->operator->id)
        ->and($backfillRes->json('data.corrected_at'))->not->toBeNull();
});

// ─── T8: Optimistic Locking on PUT /api/teaching/journals/{id} ───────────────
test('T8 enforces expected_version optimistic locking on journal updates and returns 409 with latest on conflict', function () {
    Sanctum::actingAs($this->teacherUserOne);

    $created = $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->tuesdayScheduleOne->id,
        'session_date' => '2026-11-03',
        'topic' => 'Barisan dan Deret Aritmetika',
        'method' => 'lecture',
        'summary' => 'Penurunan rumus suku ke-n dan jumlah n suku pertama deret aritmetika.',
    ])->assertCreated();

    $journalId = $created->json('data.id');

    // Valid update with expected_version = 1 -> version becomes 2
    $updated = $this->putJson("/api/teaching/journals/{$journalId}", [
        'expected_version' => 1,
        'topic' => 'Barisan & Deret Aritmetika (Revisi)',
        'method' => 'discussion',
        'summary' => 'Penurunan rumus suku ke-n disertai latihan kelompok aplikasi deret aritmetika.',
    ])->assertOk();

    expect($updated->json('data.version'))->toBe(2)
        ->and($updated->json('data.method'))->toBe('discussion');

    // Stale expected_version = 1 -> 409 Conflict with latest version 2
    $conflict = $this->putJson("/api/teaching/journals/{$journalId}", [
        'expected_version' => 1,
        'topic' => 'Perubahan dengan Versi Basi',
    ])->assertStatus(409);

    expect($conflict->json('conflict'))->toBeTrue()
        ->and($conflict->json('latest.id'))->toBe($journalId)
        ->and($conflict->json('latest.version'))->toBe(2)
        ->and($conflict->json('latest.topic'))->toBe('Barisan & Deret Aritmetika (Revisi)');
});

// ─── T9: Locked Academic Year Enforcement ────────────────────────────────────
test('T9 blocks creating or updating journals in a locked academic year while preserving read access', function () {
    $lockedAssignment = TeachingAssignment::create([
        'school_year_id' => $this->lockedYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->lockedYearClass->id,
        'subject_id' => $this->mathSubject->id,
        'employee_id' => $this->teacherOne->id,
        'hours_per_week' => 4,
        'status' => TeachingAssignment::STATUS_ACTIVE,
        'version' => 1,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $lockedSchedule = TeachingSchedule::create([
        'teaching_assignment_id' => $lockedAssignment->id,
        'day_of_week' => 1,
        'period_start' => 1,
        'period_end' => 2,
        'start_time' => '07:00',
        'end_time' => '08:30',
        'room' => 'R-2024',
        'room_normalized' => 'R-2024',
        'status' => TeachingSchedule::STATUS_ACTIVE,
        'version' => 1,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    $lockedJournal = TeachingJournal::create([
        'school_year_id' => $this->lockedYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->lockedYearClass->id,
        'subject_id' => $this->mathSubject->id,
        'teaching_assignment_id' => $lockedAssignment->id,
        'teaching_schedule_id' => $lockedSchedule->id,
        'assigned_employee_id' => $this->teacherOne->id,
        'teacher_employee_id' => $this->teacherOne->id,
        'session_date' => '2024-10-07',
        'source_type' => TeachingJournal::SOURCE_REGULAR,
        'period_start' => 1,
        'period_end' => 2,
        'scheduled_start_time' => '07:00',
        'scheduled_end_time' => '08:30',
        'actual_start_time' => '07:00',
        'actual_end_time' => '08:30',
        'room' => 'R-2024',
        'topic' => 'Arsip Jurnal Tahun Terkunci',
        'method' => 'lecture',
        'summary' => 'Jurnal historis dari tahun ajaran 2024/2025 yang sudah dikunci.',
        'version' => 1,
        'created_by' => $this->admin->id,
        'updated_by' => $this->admin->id,
    ]);

    Sanctum::actingAs($this->admin);

    // Read endpoints remain accessible (200 OK)
    $this->getJson('/api/teaching/sessions?'.http_build_query([
        'school_year_id' => $this->lockedYear->id,
        'semester_id' => $this->semesterOne->id,
        'date' => '2024-10-07',
    ]))->assertOk()
        ->assertJsonPath('meta.is_year_locked', true)
        ->assertJsonPath('data.0.can_update', false);

    $this->getJson('/api/teaching/journals?school_year_id='.$this->lockedYear->id)
        ->assertOk()
        ->assertJsonPath('data.0.id', $lockedJournal->id)
        ->assertJsonPath('data.0.can_update', false);

    // Write endpoints return 422 on school_year_id
    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $lockedSchedule->id,
        'session_date' => '2024-10-14',
        'topic' => 'Percobaan Tulis pada Tahun Terkunci',
        'method' => 'lecture',
        'summary' => 'Harus ditolak karena tahun ajaran 2024/2025 berstatus terkunci.',
        'correction_reason' => 'Alasan koreksi yang valid namun tahun terkunci.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['school_year_id']);

    $this->putJson("/api/teaching/journals/{$lockedJournal->id}", [
        'expected_version' => 1,
        'topic' => 'Edit pada Tahun Terkunci',
        'correction_reason' => 'Alasan koreksi yang valid namun tahun terkunci.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['school_year_id']);
});

// ─── T10: Bidirectional Consistency with Schedules & Exceptions (D6) ─────────
test('T10 enforces bidirectional consistency between teaching journals, schedules, and calendar exceptions', function () {
    Sanctum::actingAs($this->operator);

    // 1. Create a holiday on Monday 2026-11-02 and verify recording a journal on that date is rejected (422)
    $holidayRes = $this->postJson('/api/teaching/exceptions', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => 'holiday',
        'exception_date' => '2026-11-02',
        'reason' => 'Cut Bersama Nasional',
    ])->assertCreated();

    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'session_date' => '2026-11-02',
        'topic' => 'Jurnal pada Hari Libur',
        'method' => 'lecture',
        'summary' => 'Tidak boleh mencatat jurnal pada tanggal yang ditandai libur.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['session_date']);

    // 2. Record a valid journal on Tuesday 2026-11-03
    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->tuesdayScheduleOne->id,
        'session_date' => '2026-11-03',
        'topic' => 'Jurnal Valid Hari Selasa',
        'method' => 'lecture',
        'summary' => 'Jurnal pembelajaran reguler yang sudah tercatat secara sah.',
    ])->assertCreated();

    // 3. Attempting to delete tuesdayScheduleOne now fails with 422 because it has a recorded journal
    $this->deleteJson('/api/teaching/schedules/'.$this->tuesdayScheduleOne->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id']);

    // 4. Attempting to change day_of_week on tuesdayScheduleOne fails with 422
    $this->putJson('/api/teaching/schedules/'.$this->tuesdayScheduleOne->id, [
        'expected_version' => 1,
        'day_of_week' => 3,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['day_of_week']);

    // 5. Attempting to mark 2026-11-03 as a holiday or cancelled slot now fails with 422
    $this->postJson('/api/teaching/exceptions', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => 'holiday',
        'exception_date' => '2026-11-03',
        'reason' => 'Libur Mendadak Setelah Jurnal Diisi',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['exception_date']);

    $this->postJson('/api/teaching/exceptions', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => 'cancelled',
        'exception_date' => '2026-11-03',
        'teaching_schedule_id' => $this->tuesdayScheduleOne->id,
        'reason' => 'Pembatalan Setelah Jurnal Diisi',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['exception_date']);
});

// ─── T11: Audit Log Allowlist Privacy (D8) ───────────────────────────────────
test('T11 logs structural journal attributes in activities without leaking topic, summary, notes, or correction_reason', function () {
    Sanctum::actingAs($this->operator);

    $res = $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'session_date' => '2026-10-19',
        'topic' => 'SECRET_TOPIC_DO_NOT_AUDIT',
        'method' => 'practicum',
        'summary' => 'SECRET_SUMMARY_CONTENT_DO_NOT_AUDIT_IN_ACTIVITY_LOG',
        'notes' => 'SECRET_NOTES_DO_NOT_AUDIT',
        'correction_reason' => 'SECRET_CORRECTION_REASON_DO_NOT_AUDIT',
    ])->assertCreated();

    $journalId = $res->json('data.id');

    $activity = Activity::query()
        ->where('table_name', 'teaching_journals')
        ->where('record_id', $journalId)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();

    $encodedNew = json_encode($activity->new);
    expect($encodedNew)->toContain('practicum')
        ->and($encodedNew)->not->toContain('SECRET_TOPIC_DO_NOT_AUDIT')
        ->and($encodedNew)->not->toContain('SECRET_SUMMARY_CONTENT_DO_NOT_AUDIT_IN_ACTIVITY_LOG')
        ->and($encodedNew)->not->toContain('SECRET_NOTES_DO_NOT_AUDIT')
        ->and($encodedNew)->not->toContain('SECRET_CORRECTION_REASON_DO_NOT_AUDIT');
});

// ─── T12: Filtering & Pagination on GET /api/teaching/journals ───────────────
test('T12 filters and paginates recorded teaching journals by scope, teacher, class, method, date range, and search', function () {
    Sanctum::actingAs($this->operator);

    // Record journal 1 (Teacher One, Class A, lecture, 2026-11-02)
    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'session_date' => '2026-11-02',
        'topic' => 'Integral Tak Tentu',
        'method' => 'lecture',
        'summary' => 'Konsep dasar antiturunan dan rumus dasar integral fungsi aljabar.',
    ])->assertCreated();

    // Record journal 2 (Teacher One, Class A, practicum, 2026-11-03)
    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->tuesdayScheduleOne->id,
        'session_date' => '2026-11-03',
        'topic' => 'Aplikasi Integral Luas Daerah',
        'method' => 'practicum',
        'summary' => 'Praktikum visualisasi luas daerah di bawah kurva dengan GeoGebra.',
    ])->assertCreated();

    // Record journal 3 (Teacher Two, Class B, discussion, 2026-11-03)
    $this->postJson('/api/teaching/journals', [
        'teaching_schedule_id' => $this->tuesdayScheduleTwo->id,
        'session_date' => '2026-11-03',
        'topic' => 'Kinematika Gerak Lurus',
        'method' => 'discussion',
        'summary' => 'Diskusi grafik kecepatan terhadap waktu pada GLB dan GLBB.',
    ])->assertCreated();

    // 1. All journals in active year -> 3
    $allRes = $this->getJson('/api/teaching/journals?school_year_id='.$this->activeYear->id)
        ->assertOk();
    expect($allRes->json('meta.total'))->toBe(3);

    // 2. Filter by scope=mine as Teacher Two -> 1
    Sanctum::actingAs($this->teacherUserTwo);
    $mineRes = $this->getJson('/api/teaching/journals?scope=mine')
        ->assertOk();
    expect($mineRes->json('meta.total'))->toBe(1)
        ->and($mineRes->json('data.0.teacher_employee_id'))->toBe($this->teacherTwo->id);

    // 3. Filter by method=practicum -> 1
    $methodRes = $this->getJson('/api/teaching/journals?method=practicum')
        ->assertOk();
    expect($methodRes->json('meta.total'))->toBe(1)
        ->and($methodRes->json('data.0.topic'))->toBe('Aplikasi Integral Luas Daerah');

    // 4. Filter by search keyword 'GeoGebra' -> 1
    $searchRes = $this->getJson('/api/teaching/journals?search=GeoGebra')
        ->assertOk();
    expect($searchRes->json('meta.total'))->toBe(1);
});
