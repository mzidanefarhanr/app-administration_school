<?php

use App\Models\AcademicSetting;
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
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    // Tuesday, 2026-11-03 09:00:00 WIB (7 days grace = 2026-10-27)
    Carbon::setTestNow(Carbon::create(2026, 11, 3, 9, 0, 0, 'Asia/Jakarta'));

    $this->seedAccessReferenceData();

    $this->admin = $this->makeUserWithRole('admin-sistem');
    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->vicePrincipal = $this->makeUserWithRole('wakil-kesiswaan');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->teacherUserOne = $this->makeUserWithRole('guru', ['username' => 'guru.one']);
    $this->teacherUserTwo = $this->makeUserWithRole('guru', ['username' => 'guru.two']);
    $this->plainUser = $this->makeUserWithRole();

    $province = Province::firstOrCreate(['name' => 'DKI Jakarta']);
    $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Utara']);
    $district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Koja']);

    $gender = Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $religion = Religion::firstOrCreate(['name' => 'Islam']);
    $maritalStatus = MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);
    $status = EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap Yayasan'], ['abbreviation' => 'PTY']);
    $this->teacherType = EmployeeType::firstOrCreate(['name' => 'Pendidik']);
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

    // Teacher without any sessions/assignments
    $this->teacherThree = Employee::create(array_merge($baseEmployee, [
        'name' => 'Synthetic Teacher Gamma (No Sessions)',
        'nuptk' => '2001000000000003',
        'employee_type_id' => $this->teacherType->id,
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

    // Monday Schedule for Teacher One (day_of_week = 1)
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

    // Tuesday Schedule for Teacher One (day_of_week = 2)
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

    // Wednesday Schedule for Teacher Two (day_of_week = 3)
    $this->wednesdayScheduleTwo = TeachingSchedule::create([
        'teaching_assignment_id' => $this->assignmentTwo->id,
        'day_of_week' => 3,
        'period_start' => 3,
        'period_end' => 4,
        'start_time' => '08:45',
        'end_time' => '10:15',
        'room' => 'R-102',
        'room_normalized' => 'R-102',
        'status' => TeachingSchedule::STATUS_ACTIVE,
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

// ─────────────────────────────────────────────────────────────────────────────
// T1: Denominator Accuracy - Teachers with 0 expected sessions excluded (DEC-41 D3)
// ─────────────────────────────────────────────────────────────────────────────
it('T1: excludes teachers with 0 scheduled sessions from teacher compliance summary', function () {
    Sanctum::actingAs($this->admin);

    $res = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-03',
    ]));

    $res->assertOk();
    $byTeacher = $res->json('data.by_teacher');

    $teacherIds = array_column($byTeacher, 'employee_id');
    expect($teacherIds)->toContain($this->teacherOne->id)
        ->and($teacherIds)->not->toContain($this->teacherThree->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// T2: Holiday Exclusion - School-wide and rombel holiday excluded from denominator (DEC-41 D2)
// ─────────────────────────────────────────────────────────────────────────────
it('T2: excludes holiday sessions from expected sessions denominator', function () {
    Sanctum::actingAs($this->admin);

    // Monday 2026-11-02 holiday
    TeachingScheduleException::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => TeachingScheduleException::TYPE_HOLIDAY,
        'exception_date' => '2026-11-02',
        'reason' => 'Hari Libur Nasional',
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    $res = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-11-02',
        'date_to' => '2026-11-02',
    ]));

    $res->assertOk();
    $summary = $res->json('data.summary');

    // Monday slot was holiday => 0 expected sessions, 1 excluded slot
    expect($summary['total_expected_sessions'])->toBe(0)
        ->and($summary['total_missing_journals'])->toBe(0)
        ->and($summary['total_excluded_slots'])->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// T3: Session Cancellation Exclusion (DEC-41 D2)
// ─────────────────────────────────────────────────────────────────────────────
it('T3: excludes cancelled sessions from expected sessions denominator', function () {
    Sanctum::actingAs($this->admin);

    TeachingScheduleException::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'teaching_schedule_id' => $this->tuesdayScheduleOne->id,
        'exception_type' => TeachingScheduleException::TYPE_CANCELLED,
        'exception_date' => '2026-11-03',
        'reason' => 'Guru Dinas Luar',
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    $res = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-11-03',
        'date_to' => '2026-11-03',
    ]));

    $res->assertOk();
    $summary = $res->json('data.summary');

    expect($summary['total_expected_sessions'])->toBe(0)
        ->and($summary['total_missing_journals'])->toBe(0)
        ->and($summary['total_excluded_slots'])->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// T4: Rescheduled Out Exclusion from Original Date (DEC-41 D2)
// ─────────────────────────────────────────────────────────────────────────────
it('T4: excludes rescheduled out slot from original date denominator', function () {
    Sanctum::actingAs($this->admin);

    TeachingScheduleException::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'exception_type' => TeachingScheduleException::TYPE_RESCHEDULED,
        'exception_date' => '2026-11-02',
        'rescheduled_date' => '2026-11-05',
        'rescheduled_period_start' => 5,
        'rescheduled_period_end' => 6,
        'rescheduled_start_time' => '10:30',
        'rescheduled_end_time' => '12:00',
        'reason' => 'Dipindah ke Kamis',
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    $res = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-11-02',
        'date_to' => '2026-11-02',
    ]));

    $res->assertOk();
    $summary = $res->json('data.summary');

    expect($summary['total_expected_sessions'])->toBe(0)
        ->and($summary['total_missing_journals'])->toBe(0)
        ->and($summary['total_excluded_slots'])->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// T5: Rescheduled In Inclusion on Target Date under Effective Teacher (DEC-41 D2)
// ─────────────────────────────────────────────────────────────────────────────
it('T5: includes rescheduled session on target replacement date under effective teacher', function () {
    Sanctum::actingAs($this->admin);

    TeachingScheduleException::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'exception_type' => TeachingScheduleException::TYPE_RESCHEDULED,
        'exception_date' => '2026-11-02',
        'rescheduled_date' => '2026-11-05',
        'rescheduled_period_start' => 5,
        'rescheduled_period_end' => 6,
        'rescheduled_start_time' => '10:30',
        'rescheduled_end_time' => '12:00',
        'substitute_employee_id' => $this->teacherTwo->id,
        'reason' => 'Dipindah ke Kamis digantikan Guru Beta',
        'version' => 1,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    $res = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-11-05',
        'date_to' => '2026-11-05',
    ]));

    $res->assertOk();
    $summary = $res->json('data.summary');
    $drilldown = $res->json('data.drilldown.data');

    expect($summary['total_expected_sessions'])->toBe(1)
        ->and($summary['total_missing_journals'])->toBe(1)
        ->and($drilldown[0]['teacher_employee_id'])->toBe($this->teacherTwo->id)
        ->and($drilldown[0]['source_type'])->toBe('rescheduled');
});

// ─────────────────────────────────────────────────────────────────────────────
// T6: Recorded Journal Matching (DEC-41 D3)
// ─────────────────────────────────────────────────────────────────────────────
it('T6: marks session with teaching_journals record as recorded journal', function () {
    Sanctum::actingAs($this->admin);

    TeachingJournal::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classA->id,
        'subject_id' => $this->mathSubject->id,
        'teaching_assignment_id' => $this->assignmentOne->id,
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'assigned_employee_id' => $this->teacherOne->id,
        'teacher_employee_id' => $this->teacherOne->id,
        'session_date' => '2026-11-02',
        'source_type' => TeachingJournal::SOURCE_REGULAR,
        'period_start' => 1,
        'period_end' => 2,
        'scheduled_start_time' => '07:00',
        'scheduled_end_time' => '08:30',
        'actual_start_time' => '07:00',
        'actual_end_time' => '08:30',
        'topic' => 'Aljabar Matriks Dasar',
        'method' => 'lecture',
        'status' => 'completed',
        'version' => 1,
        'created_by' => $this->teacherUserOne->id,
        'updated_by' => $this->teacherUserOne->id,
    ]);

    $res = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-11-02',
        'date_to' => '2026-11-02',
    ]));

    $res->assertOk();
    $summary = $res->json('data.summary');

    expect($summary['total_expected_sessions'])->toBe(1)
        ->and($summary['total_recorded_journals'])->toBe(1)
        ->and($summary['total_missing_journals'])->toBe(0)
        ->and((float) $summary['overall_compliance_percentage'])->toEqual(100.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// T7: Missing Journal Detection (DEC-41 D3)
// ─────────────────────────────────────────────────────────────────────────────
it('T7: marks active session without teaching_journals record as missing', function () {
    Sanctum::actingAs($this->admin);

    $res = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-11-02',
        'date_to' => '2026-11-02',
    ]));

    $res->assertOk();
    $summary = $res->json('data.summary');
    $drilldown = $res->json('data.drilldown.data');

    expect($summary['total_expected_sessions'])->toBe(1)
        ->and($summary['total_recorded_journals'])->toBe(0)
        ->and($summary['total_missing_journals'])->toBe(1)
        ->and((float) $summary['overall_compliance_percentage'])->toEqual(0.0)
        ->and($drilldown[0]['is_missing'])->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// T8: Grace Window vs Overdue Segmentation (DEC-41 D4)
// ─────────────────────────────────────────────────────────────────────────────
it('T8: categorizes missing sessions within 7 days as grace and older as overdue', function () {
    Sanctum::actingAs($this->admin);

    // Today is Tuesday 2026-11-03 WIB
    // Yesterday Monday 2026-11-02 is within 7 days grace window
    // Monday 2026-10-19 (15 days ago) is overdue (> 7 days)
    $res = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-10-19',
        'date_to' => '2026-11-03',
    ]));

    $res->assertOk();
    $summary = $res->json('data.summary');

    expect($summary['total_missing_within_grace'])->toBeGreaterThan(0)
        ->and($summary['total_missing_overdue'])->toBeGreaterThan(0)
        ->and($summary['total_missing_journals'])->toBe(
            $summary['total_missing_within_grace'] + $summary['total_missing_overdue']
        );
});

// ─────────────────────────────────────────────────────────────────────────────
// T9: Mathematical Consistency of Aggregates (DEC-41 D3 & D4)
// ─────────────────────────────────────────────────────────────────────────────
it('T9: guarantees mathematical aggregate consistency across summary and teachers', function () {
    Sanctum::actingAs($this->admin);

    // Record 1 journal on Monday 2026-11-02
    TeachingJournal::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classA->id,
        'subject_id' => $this->mathSubject->id,
        'teaching_assignment_id' => $this->assignmentOne->id,
        'teaching_schedule_id' => $this->mondayScheduleOne->id,
        'assigned_employee_id' => $this->teacherOne->id,
        'teacher_employee_id' => $this->teacherOne->id,
        'session_date' => '2026-11-02',
        'source_type' => TeachingJournal::SOURCE_REGULAR,
        'period_start' => 1,
        'period_end' => 2,
        'scheduled_start_time' => '07:00',
        'scheduled_end_time' => '08:30',
        'actual_start_time' => '07:00',
        'actual_end_time' => '08:30',
        'topic' => 'Matriks',
        'method' => 'lecture',
        'status' => 'completed',
        'version' => 1,
        'created_by' => $this->teacherUserOne->id,
        'updated_by' => $this->teacherUserOne->id,
    ]);

    $res = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-10-26',
        'date_to' => '2026-11-03',
    ]));

    $res->assertOk();
    $summary = $res->json('data.summary');
    $byTeacher = $res->json('data.by_teacher');

    expect($summary['total_expected_sessions'])->toBe(
        $summary['total_recorded_journals'] + $summary['total_missing_journals']
    )->and($summary['total_missing_journals'])->toBe(
        $summary['total_missing_within_grace'] + $summary['total_missing_overdue']
    );

    foreach ($byTeacher as $t) {
        expect($t['expected_sessions'])->toBe(
            $t['recorded_journals'] + $t['missing_journals']
        )->and($t['missing_journals'])->toBe(
            $t['missing_within_grace'] + $t['missing_overdue']
        );
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// T10: Data Filtering (Rombel, Subject, Teacher, Status) (DEC-41 D5)
// ─────────────────────────────────────────────────────────────────────────────
it('T10: filters sessions accurately by rombel, subject, teacher, and status', function () {
    Sanctum::actingAs($this->admin);

    // Filter by Rombel A
    $resRombel = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classA->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-03',
    ]));
    $resRombel->assertOk();
    $drilldownA = $resRombel->json('data.drilldown.data');
    foreach ($drilldownA as $item) {
        expect($item['school_rombel_id'])->toBe($this->classA->id);
    }

    // Filter by Subject Physics
    $resSubject = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'subject_id' => $this->physicsSubject->id,
        'date_from' => '2026-10-25',
        'date_to' => '2026-11-03',
    ]));
    $resSubject->assertOk();
    $drilldownSubject = $resSubject->json('data.drilldown.data');
    foreach ($drilldownSubject as $item) {
        expect($item['subject_id'])->toBe($this->physicsSubject->id);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// T11: Date Range Validation & 90 Days Constraint (DEC-41 D5)
// ─────────────────────────────────────────────────────────────────────────────
it('T11: rejects invalid date range and ranges exceeding 90 days with 422', function () {
    Sanctum::actingAs($this->admin);

    // date_to < date_from
    $resInverted = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-11-05',
        'date_to' => '2026-11-01',
    ]));
    $resInverted->assertStatus(422)
        ->assertJsonValidationErrors(['date_to']);

    // range > 90 days (100 days)
    $resTooLong = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-07-01',
        'date_to' => '2026-10-20',
    ]));
    $resTooLong->assertStatus(422)
        ->assertJsonValidationErrors(['date_to']);
});

// ─────────────────────────────────────────────────────────────────────────────
// T12: Teacher RBAC Scoping - scope=mine enforced, 403 on other teachers (DEC-41 D6)
// ─────────────────────────────────────────────────────────────────────────────
it('T12: enforces scope=mine for teacher role and rejects reading other teachers with 403', function () {
    Sanctum::actingAs($this->teacherUserOne);

    // Teacher one can request report without employee_id (server scopes to mine)
    $resOwn = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-03',
    ]));
    $resOwn->assertOk();
    expect($resOwn->json('meta.scope'))->toBe('mine');
    $byTeacher = $resOwn->json('data.by_teacher');
    expect(count($byTeacher))->toBe(1)
        ->and($byTeacher[0]['employee_id'])->toBe($this->teacherOne->id);

    // Teacher one attempts to request teacher two's data => 403 Forbidden
    $resOther = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'employee_id' => $this->teacherTwo->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-03',
    ]));
    $resOther->assertForbidden();
});

// ─────────────────────────────────────────────────────────────────────────────
// T13: Streaming CSV Export (DEC-41 D7)
// ─────────────────────────────────────────────────────────────────────────────
it('T13: streams CSV export with UTF-8 BOM, valid header row, and nosniff header', function () {
    Sanctum::actingAs($this->admin);

    $res = $this->get('/api/teaching/reports/missing-journals/export?'.http_build_query([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2026-11-01',
        'date_to' => '2026-11-03',
    ]));

    $res->assertOk();
    $res->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $res->assertHeader('x-content-type-options', 'nosniff');

    $content = $res->streamedContent();

    // Verify UTF-8 BOM (\xEF\xBB\xBF)
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();

    // Verify CSV Header Row
    expect($content)->toContain('Session Date')
        ->and($content)->toContain('Class')
        ->and($content)->toContain('Subject')
        ->and($content)->toContain('Assigned Teacher')
        ->and($content)->toContain('Effective Teacher')
        ->and($content)->toContain('Journal Status');
});

// ─────────────────────────────────────────────────────────────────────────────
// T14: Locked School Year Read-Only (DEC-41 D8)
// ─────────────────────────────────────────────────────────────────────────────
it('T14: allows reading reports for locked school year with can_record false', function () {
    Sanctum::actingAs($this->admin);

    $res = $this->getJson('/api/teaching/reports/missing-journals?'.http_build_query([
        'school_year_id' => $this->lockedYear->id,
        'semester_id' => $this->semesterOne->id,
        'date_from' => '2024-08-01',
        'date_to' => '2024-08-30',
    ]));

    $res->assertOk();
    expect($res->json('meta.is_year_locked'))->toBeTrue();

    $drilldown = $res->json('data.drilldown.data');
    foreach ($drilldown as $item) {
        expect($item['can_record'])->toBeFalse();
    }
});
