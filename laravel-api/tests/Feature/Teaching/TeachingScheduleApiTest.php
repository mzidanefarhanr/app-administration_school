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
use App\Models\TeachingSchedule;
use App\Models\TeachingScheduleException;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 3, 9, 0, 0, 'Asia/Jakarta'));

    $this->seedAccessReferenceData();

    $this->admin = $this->makeUserWithRole('admin-sistem');
    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->vicePrincipal = $this->makeUserWithRole('wakil-kesiswaan');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->teacherUser = $this->makeUserWithRole('guru');
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
    $this->englishSubject = Subject::firstOrCreate(['name' => 'English'], ['description' => 'English']);

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
        'nuptk' => '1001000000000001',
        'employee_type_id' => $this->teacherType->id,
        'user_id' => $this->teacherUser->id,
    ]));

    $this->teacherTwo = Employee::create(array_merge($baseEmployee, [
        'name' => 'Synthetic Teacher Beta',
        'nuptk' => '1001000000000002',
        'employee_type_id' => $this->teacherType->id,
        'subject_id' => $this->physicsSubject->id,
    ]));

    $this->nonTeachingStaff = Employee::create(array_merge($baseEmployee, [
        'name' => 'Synthetic Staff Gamma',
        'nuptk' => '1001000000000003',
        'employee_type_id' => $this->staffType->id,
        'subject_id' => null,
    ]));

    $this->exitedTeacher = Employee::create(array_merge($baseEmployee, [
        'name' => 'Synthetic Exited Teacher',
        'nuptk' => '1001000000000004',
        'employee_type_id' => $this->teacherType->id,
        'exit_date' => '2025-12-31',
    ]));

    $this->activeYear = SchoolYear::create([
        'name' => '2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);

    $this->otherYear = SchoolYear::create([
        'name' => '2025/2026',
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
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
    $this->semesterTwo = Semester::firstOrCreate(['name' => 'II'], ['name_other' => 'Genap']);

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

    $this->otherYearClass = SchoolRombel::create([
        'name' => 'X-2025',
        'school_year_id' => $this->otherYear->id,
        'school_level_id' => $this->level->id,
        'student_major_id' => $this->major->id,
        'version' => 1,
    ]);

    $this->lockedYearClass = SchoolRombel::create([
        'name' => 'X-2024',
        'school_year_id' => $this->lockedYear->id,
        'school_level_id' => $this->level->id,
        'student_major_id' => $this->major->id,
        'version' => 1,
    ]);
});

// ─── T1: RBAC & Route Registration ───────────────────────────────────────────
test('T1 teaching routes are registered and enforce teaching.view and teaching.manage across default roles', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/teaching'))
        ->values();

    expect($routes)->toHaveCount(18);

    // Unauthenticated -> 401
    $this->getJson('/api/teaching/options')->assertStatus(401);

    // Plain user without teaching.view -> 403
    Sanctum::actingAs($this->plainUser);
    $this->getJson('/api/teaching/options')->assertStatus(403);
    $this->getJson('/api/teaching/assignments')->assertStatus(403);
    $this->getJson('/api/teaching/schedules')->assertStatus(403);
    $this->getJson('/api/teaching/exceptions')->assertStatus(403);

    // Teacher (guru) and Principal (kepala-sekolah) have teaching.view but NOT teaching.manage
    foreach ([$this->teacherUser, $this->principal] as $viewer) {
        Sanctum::actingAs($viewer);
        $this->getJson('/api/teaching/options')
            ->assertOk()
            ->assertJsonPath('data.can.view', true)
            ->assertJsonPath('data.can.manage', false);

        $this->getJson('/api/teaching/assignments')->assertOk();
        $this->getJson('/api/teaching/schedules')->assertOk();
        $this->getJson('/api/teaching/exceptions')->assertOk();

        $this->postJson('/api/teaching/assignments', [])->assertStatus(403);
        $this->postJson('/api/teaching/schedules', [])->assertStatus(403);
        $this->postJson('/api/teaching/exceptions', [])->assertStatus(403);
    }

    // Admin, Operator, and Vice Principal have both teaching.view and teaching.manage
    foreach ([$this->admin, $this->operator, $this->vicePrincipal] as $manager) {
        Sanctum::actingAs($manager);
        $this->getJson('/api/teaching/options')
            ->assertOk()
            ->assertJsonPath('data.can.view', true)
            ->assertJsonPath('data.can.manage', true);
    }
});

// ─── T2: Assignment CRUD & Audit Allowlist ───────────────────────────────────
test('T2 operator can create and update teaching assignments with audit allowlist excluding free-text notes', function () {
    Sanctum::actingAs($this->operator);

    $createRes = $this->postJson('/api/teaching/assignments', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classA->id,
        'subject_id' => $this->physicsSubject->id, // Secondary subject allowed per D2
        'employee_id' => $this->teacherOne->id,
        'hours_per_week' => 4,
        'status' => 'active',
        'notes' => 'Sensitive internal assignment note not for audit log',
    ])->assertCreated();

    $assignmentId = $createRes->json('data.id');
    expect($createRes->json('data.version'))->toBe(1)
        ->and($createRes->json('data.hours_per_week'))->toBe(4)
        ->and($createRes->json('data.employee.primary_subject_id'))->toBe($this->mathSubject->id);

    // Check Activity audit log excludes notes
    $activity = Activity::query()
        ->where('table_name', 'teaching_assignments')
        ->where('record_id', $assignmentId)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect(json_encode($activity->new))->not->toContain('Sensitive internal assignment note');

    // Update assignment
    $updateRes = $this->putJson("/api/teaching/assignments/{$assignmentId}", [
        'expected_version' => 1,
        'hours_per_week' => 6,
        'notes' => 'Updated note',
    ])->assertOk();

    expect($updateRes->json('data.version'))->toBe(2)
        ->and($updateRes->json('data.hours_per_week'))->toBe(6);
});

// ─── T3: Cross-Entity Assignment Validation ──────────────────────────────────
test('T3 teaching assignment rejects mismatched class school year, non-teaching or exited staff, and duplicates', function () {
    Sanctum::actingAs($this->operator);

    // 1. Class belongs to 2025/2026, but school_year_id is 2026/2027
    $this->postJson('/api/teaching/assignments', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->otherYearClass->id,
        'subject_id' => $this->mathSubject->id,
        'employee_id' => $this->teacherOne->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['school_rombel_id']);

    // 2. Non-teaching staff (Tenaga Kependidikan) rejected
    $this->postJson('/api/teaching/assignments', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classA->id,
        'subject_id' => $this->mathSubject->id,
        'employee_id' => $this->nonTeachingStaff->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['employee_id']);

    // 3. Exited teacher rejected
    $this->postJson('/api/teaching/assignments', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classA->id,
        'subject_id' => $this->mathSubject->id,
        'employee_id' => $this->exitedTeacher->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['employee_id']);

    // 4. Valid creation followed by duplicate (same year, semester, class, subject, teacher)
    $payload = [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classA->id,
        'subject_id' => $this->mathSubject->id,
        'employee_id' => $this->teacherOne->id,
    ];
    $this->postJson('/api/teaching/assignments', $payload)->assertCreated();
    $this->postJson('/api/teaching/assignments', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['employee_id']);
});

// ─── T4–T8: Timetable Slots, Matrix View & Collision Detection (Teacher/Class/Room/Inactive) ───
test('T4-T8 schedule slot creation, matrix view, and teacher, class, room, and reactivation collision detection', function () {
    Sanctum::actingAs($this->operator);

    $assignClassATeacher1Math = TeachingAssignment::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classA->id,
        'subject_id' => $this->mathSubject->id,
        'employee_id' => $this->teacherOne->id,
        'hours_per_week' => 4,
        'status' => 'active',
    ]);

    $assignClassBTeacher1Math = TeachingAssignment::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classB->id,
        'subject_id' => $this->mathSubject->id,
        'employee_id' => $this->teacherOne->id,
        'hours_per_week' => 4,
        'status' => 'active',
    ]);

    $assignClassATeacher2Physics = TeachingAssignment::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classA->id,
        'subject_id' => $this->physicsSubject->id,
        'employee_id' => $this->teacherTwo->id,
        'hours_per_week' => 4,
        'status' => 'active',
    ]);

    $assignClassBTeacher2Physics = TeachingAssignment::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classB->id,
        'subject_id' => $this->physicsSubject->id,
        'employee_id' => $this->teacherTwo->id,
        'hours_per_week' => 4,
        'status' => 'active',
    ]);

    // T4: Create initial valid schedule slot (Monday P1-P2, 07:00-08:30, room Lab-Komp-1)
    $slot1Res = $this->postJson('/api/teaching/schedules', [
        'teaching_assignment_id' => $assignClassATeacher1Math->id,
        'day_of_week' => 1,
        'period_start' => 1,
        'period_end' => 2,
        'start_time' => '07:00',
        'end_time' => '08:30',
        'room' => ' lab-komp-1 ',
    ])->assertCreated();

    $slot1Id = $slot1Res->json('data.id');

    // T5: Teacher collision — Teacher 1 cannot teach Class B on Monday 07:45-09:15 (overlaps 07:00-08:30)
    $this->postJson('/api/teaching/schedules', [
        'teaching_assignment_id' => $assignClassBTeacher1Math->id,
        'day_of_week' => 1,
        'period_start' => 2,
        'period_end' => 3,
        'start_time' => '07:45',
        'end_time' => '09:15',
        'room' => 'R-102',
    ])->assertStatus(422)->assertJsonValidationErrors(['teaching_assignment_id']);

    // T6: Class collision — Class A cannot have Teacher 2 (Physics) on Monday 07:45-09:15
    $this->postJson('/api/teaching/schedules', [
        'teaching_assignment_id' => $assignClassATeacher2Physics->id,
        'day_of_week' => 1,
        'period_start' => 2,
        'period_end' => 3,
        'start_time' => '07:45',
        'end_time' => '09:15',
        'room' => 'R-103',
    ])->assertStatus(422)->assertJsonValidationErrors(['start_time']);

    // T7: Room collision (case-insensitive normalized) — Class B + Teacher 2 cannot use LAB-KOMP-1 on Monday 07:00-08:30
    $this->postJson('/api/teaching/schedules', [
        'teaching_assignment_id' => $assignClassBTeacher2Physics->id,
        'day_of_week' => 1,
        'period_start' => 1,
        'period_end' => 2,
        'start_time' => '07:00',
        'end_time' => '08:30',
        'room' => 'LAB-KOMP-1',
    ])->assertStatus(422)->assertJsonValidationErrors(['room']);

    // Different room (or null room) for Class B + Teacher 2 on Monday 07:00-08:30 succeeds!
    $slot2Res = $this->postJson('/api/teaching/schedules', [
        'teaching_assignment_id' => $assignClassBTeacher2Physics->id,
        'day_of_week' => 1,
        'period_start' => 1,
        'period_end' => 2,
        'start_time' => '07:00',
        'end_time' => '08:30',
        'room' => null,
    ])->assertCreated();
    $slot2Id = $slot2Res->json('data.id');

    // Matrix view returns 6 day groups (Monday..Saturday)
    $matrixRes = $this->getJson("/api/teaching/schedules?school_year_id={$this->activeYear->id}&semester_id={$this->semesterOne->id}&view_mode=matrix")
        ->assertOk();
    expect($matrixRes->json('matrix'))->toHaveCount(6)
        ->and($matrixRes->json('matrix.0.slots'))->toHaveCount(2);

    // T8: Deactivating slot1 frees LAB-KOMP-1 so slot2 can take LAB-KOMP-1; reactivating slot1 then fails with 422!
    $this->putJson("/api/teaching/schedules/{$slot1Id}", [
        'expected_version' => 1,
        'status' => 'inactive',
    ])->assertOk();

    $this->putJson("/api/teaching/schedules/{$slot2Id}", [
        'expected_version' => 1,
        'room' => 'LAB-KOMP-1',
    ])->assertOk();

    $this->putJson("/api/teaching/schedules/{$slot1Id}", [
        'expected_version' => 2,
        'status' => 'active',
    ])->assertStatus(422)->assertJsonValidationErrors(['room']);
});

// ─── T9–T10: Calendar Exceptions (Holiday, Cancelled, Rescheduled & Reschedule Collisions) ───
test('T9-T10 calendar exceptions validate date range, day of week, holiday blocks, and reschedule collisions', function () {
    Sanctum::actingAs($this->operator);

    $assignA = TeachingAssignment::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classA->id,
        'subject_id' => $this->mathSubject->id,
        'employee_id' => $this->teacherOne->id,
        'status' => 'active',
    ]);

    $assignB = TeachingAssignment::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classB->id,
        'subject_id' => $this->physicsSubject->id,
        'employee_id' => $this->teacherTwo->id,
        'status' => 'active',
    ]);

    // Monday slot for Class A (Teacher 1): P1-P2 (07:00-08:30)
    $mondaySlotA = TeachingSchedule::create([
        'teaching_assignment_id' => $assignA->id,
        'day_of_week' => 1, // Monday
        'period_start' => 1,
        'period_end' => 2,
        'start_time' => '07:00',
        'end_time' => '08:30',
        'room' => 'R-101',
        'room_normalized' => 'R-101',
        'status' => 'active',
    ]);

    // Tuesday slot for Class B (Teacher 2): P1-P2 (07:00-08:30) in LAB-FISIKA
    $tuesdaySlotB = TeachingSchedule::create([
        'teaching_assignment_id' => $assignB->id,
        'day_of_week' => 2, // Tuesday
        'period_start' => 1,
        'period_end' => 2,
        'start_time' => '07:00',
        'end_time' => '08:30',
        'room' => 'LAB-FISIKA',
        'room_normalized' => 'LAB-FISIKA',
        'status' => 'active',
    ]);

    // T9a: Holiday outside school year range rejected
    $this->postJson('/api/teaching/exceptions', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => 'holiday',
        'exception_date' => '2028-01-01',
        'reason' => 'Out of range holiday test',
    ])->assertStatus(422)->assertJsonValidationErrors(['exception_date']);

    // T9b: Valid school-wide holiday on Wednesday 2026-11-11
    $this->postJson('/api/teaching/exceptions', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => 'holiday',
        'exception_date' => '2026-11-11',
        'reason' => 'National school holiday observance',
    ])->assertCreated();

    // T9c: Cancelled exception on wrong day of week (2026-11-10 is Tuesday, but $mondaySlotA is Monday) -> 422
    $this->postJson('/api/teaching/exceptions', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => 'cancelled',
        'exception_date' => '2026-11-10',
        'teaching_schedule_id' => $mondaySlotA->id,
        'reason' => 'Teacher attending official workshop',
    ])->assertStatus(422)->assertJsonValidationErrors(['exception_date']);

    // T10a: Rescheduling Monday 2026-11-09 onto holiday Wednesday 2026-11-11 -> 422
    $this->postJson('/api/teaching/exceptions', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => 'rescheduled',
        'exception_date' => '2026-11-09',
        'teaching_schedule_id' => $mondaySlotA->id,
        'rescheduled_date' => '2026-11-11',
        'rescheduled_period_start' => 1,
        'rescheduled_period_end' => 2,
        'rescheduled_start_time' => '07:00',
        'rescheduled_end_time' => '08:30',
        'reason' => 'Move session to Wednesday holiday',
    ])->assertStatus(422)->assertJsonValidationErrors(['rescheduled_date']);

    // T10b: Rescheduling Monday 2026-11-09 onto Tuesday 2026-11-10 P1-P2 in LAB-FISIKA collides with $tuesdaySlotB's room -> 422
    $this->postJson('/api/teaching/exceptions', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => 'rescheduled',
        'exception_date' => '2026-11-09',
        'teaching_schedule_id' => $mondaySlotA->id,
        'rescheduled_date' => '2026-11-10',
        'rescheduled_period_start' => 1,
        'rescheduled_period_end' => 2,
        'rescheduled_start_time' => '07:00',
        'rescheduled_end_time' => '08:30',
        'rescheduled_room' => 'lab-fisika',
        'reason' => 'Move session to Tuesday in Physics Lab',
    ])->assertStatus(422)->assertJsonValidationErrors(['rescheduled_room']);

    // T10c: Rescheduling Monday 2026-11-09 onto Tuesday 2026-11-10 P3-P4 (08:30-10:00) in R-101 succeeds!
    $reschedRes = $this->postJson('/api/teaching/exceptions', [
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => 'rescheduled',
        'exception_date' => '2026-11-09',
        'teaching_schedule_id' => $mondaySlotA->id,
        'rescheduled_date' => '2026-11-10',
        'rescheduled_period_start' => 3,
        'rescheduled_period_end' => 4,
        'rescheduled_start_time' => '08:30',
        'rescheduled_end_time' => '10:00',
        'rescheduled_room' => 'R-101',
        'reason' => 'Rescheduled due to morning ceremony',
    ])->assertCreated();

    $excId = $reschedRes->json('data.id');

    // Audit log for exception excludes reason
    $excActivity = Activity::query()
        ->where('table_name', 'teaching_schedule_exceptions')
        ->where('record_id', $excId)
        ->latest('id')
        ->first();

    expect($excActivity)->not->toBeNull();
    expect(json_encode($excActivity->new))->not->toContain('Rescheduled due to morning ceremony');
});

// ─── T11–T14: Locked School Year, 409 Conflict, Delete Guards & Pagination ≥100 ───
test('T11-T14 locked school year rejection, 409 optimistic locking, master/child delete guards, and pagination at >=100 rows', function () {
    Sanctum::actingAs($this->operator);

    // T11: Locked school year rejects creating assignments and exceptions
    $this->postJson('/api/teaching/assignments', [
        'school_year_id' => $this->lockedYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->lockedYearClass->id,
        'subject_id' => $this->mathSubject->id,
        'employee_id' => $this->teacherOne->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['school_year_id']);

    $this->postJson('/api/teaching/exceptions', [
        'school_year_id' => $this->lockedYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => 'holiday',
        'exception_date' => '2024-08-17',
        'reason' => 'Independence day holiday in locked year',
    ])->assertStatus(422)->assertJsonValidationErrors(['school_year_id']);

    // Create assignment, slot, and exception in activeYear
    $assignment = TeachingAssignment::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'school_rombel_id' => $this->classA->id,
        'subject_id' => $this->mathSubject->id,
        'employee_id' => $this->teacherOne->id,
        'hours_per_week' => 2,
        'status' => 'active',
        'version' => 2,
    ]);

    $schedule = TeachingSchedule::create([
        'teaching_assignment_id' => $assignment->id,
        'day_of_week' => 1,
        'period_start' => 1,
        'period_end' => 2,
        'start_time' => '07:00',
        'end_time' => '08:30',
        'status' => 'active',
        'version' => 2,
    ]);

    $exception = TeachingScheduleException::create([
        'school_year_id' => $this->activeYear->id,
        'semester_id' => $this->semesterOne->id,
        'exception_type' => 'cancelled',
        'exception_date' => '2026-11-09',
        'school_rombel_id' => $this->classA->id,
        'teaching_schedule_id' => $schedule->id,
        'reason' => 'Cancelled due to regional competition',
        'version' => 2,
    ]);

    // T12: Stale expected_version returns 409 Conflict with latest snapshot
    $this->putJson("/api/teaching/assignments/{$assignment->id}", [
        'expected_version' => 1,
        'hours_per_week' => 4,
    ])->assertStatus(409)->assertJsonPath('conflict', true)->assertJsonPath('latest.version', 2);

    $this->putJson("/api/teaching/schedules/{$schedule->id}", [
        'expected_version' => 1,
        'room' => 'R-105',
    ])->assertStatus(409)->assertJsonPath('conflict', true)->assertJsonPath('latest.version', 2);

    $this->putJson("/api/teaching/exceptions/{$exception->id}", [
        'expected_version' => 1,
        'reason' => 'Updated reason with stale version',
    ])->assertStatus(409)->assertJsonPath('conflict', true)->assertJsonPath('latest.version', 2);

    // T13: Delete guards on SchoolRombel, TeachingAssignment, and TeachingSchedule
    Sanctum::actingAs($this->admin);
    $this->deleteJson("/api/school-rombels/{$this->classA->id}", [
        'expected_version' => $this->classA->version,
    ])->assertStatus(422);

    $this->deleteJson("/api/teaching/assignments/{$assignment->id}")->assertStatus(422);
    $this->deleteJson("/api/teaching/schedules/{$schedule->id}")->assertStatus(422);

    // Deleting exception first allows deleting schedule, which then allows deleting assignment
    $this->deleteJson("/api/teaching/exceptions/{$exception->id}")->assertOk();
    $this->deleteJson("/api/teaching/schedules/{$schedule->id}")->assertOk();
    $this->deleteJson("/api/teaching/assignments/{$assignment->id}")->assertOk();

    // T14: Scale & pagination check with >= 105 assignments
    $subjects = [];
    for ($i = 1; $i <= 105; $i++) {
        $subj = Subject::create([
            'name' => sprintf('Synthetic Subject %03d', $i),
            'description' => "Synthetic Subject {$i}",
        ]);
        TeachingAssignment::create([
            'school_year_id' => $this->activeYear->id,
            'semester_id' => $this->semesterOne->id,
            'school_rombel_id' => $this->classA->id,
            'subject_id' => $subj->id,
            'employee_id' => $this->teacherOne->id,
            'hours_per_week' => 2,
            'status' => 'active',
        ]);
    }

    $pageRes = $this->getJson("/api/teaching/assignments?school_year_id={$this->activeYear->id}&per_page=25&page=2")
        ->assertOk();

    expect($pageRes->json('meta.total'))->toBe(105)
        ->and($pageRes->json('meta.per_page'))->toBe(25)
        ->and($pageRes->json('meta.current_page'))->toBe(2)
        ->and($pageRes->json('data'))->toHaveCount(25);
});
