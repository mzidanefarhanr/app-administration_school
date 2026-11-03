<?php

use App\Models\AcademicSetting;
use App\Models\District;
use App\Models\Employee;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\Employment;
use App\Models\Gender;
use App\Models\LeaveRequest;
use App\Models\Letter;
use App\Models\MaritalStatus;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Religion;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentMajor;
use App\Models\StudentRombel;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TeachingSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 3, 9, 0, 0, 'Asia/Jakarta'));

    $this->seedAccessReferenceData();

    $this->admin = $this->makeUserWithRole('admin-sistem');
    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->teacherUser = $this->makeUserWithRole('guru', ['username' => 'guru.dashboard']);
    $this->plainUser = $this->makeUserWithRole();

    $province = Province::firstOrCreate(['name' => 'DKI Jakarta']);
    $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Utara']);
    $district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Koja']);

    $this->genderMale = Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $this->genderFemale = Gender::firstOrCreate(['name' => 'Perempuan']);
    $religion = Religion::firstOrCreate(['name' => 'Islam']);
    $marital = MaritalStatus::firstOrCreate(['name' => 'Menikah']);
    $empStatus = EmployeeStatus::firstOrCreate(['name' => 'PNS'], ['abbreviation' => 'PNS']);
    $employment = Employment::firstOrCreate(['name' => 'Tetap'], ['description' => 'Tetap']);
    $this->teacherType = EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $this->staffType = EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);

    $this->math = Subject::firstOrCreate(['name' => 'Matematika'], ['description' => 'Matematika']);

    $this->teacher = Employee::create([
        'user_id' => $this->teacherUser->id,
        'employee_type_id' => $this->teacherType->id,
        'employee_status_id' => $empStatus->id,
        'employment_id' => $employment->id,
        'subject_id' => $this->math->id,
        'name' => 'Guru Dashboard',
        'gender_id' => $this->genderMale->id,
        'religion_id' => $religion->id,
        'marital_status_id' => $marital->id,
        'district_id' => $district->id,
        'tmt_employee' => '2020-01-01',
    ]);

    $this->staff = Employee::create([
        'user_id' => $this->plainUser->id,
        'employee_type_id' => $this->staffType->id,
        'employee_status_id' => $empStatus->id,
        'employment_id' => $employment->id,
        'subject_id' => null,
        'name' => 'Staff TU',
        'gender_id' => $this->genderFemale->id,
        'religion_id' => $religion->id,
        'marital_status_id' => $marital->id,
        'district_id' => $district->id,
        'tmt_employee' => '2021-01-01',
    ]);

    $this->schoolYear = SchoolYear::create([
        'name' => '2026/2027',
        'start_date' => '2026-07-15',
        'end_date' => '2027-06-30',
    ]);

    $this->semester = Semester::firstOrCreate(
        ['name' => 'Semester I'],
        ['name_other' => 'Ganjil']
    );

    AcademicSetting::updateOrCreate(
        ['id' => AcademicSetting::SINGLETON_ID],
        [
            'active_school_year_id' => $this->schoolYear->id,
            'active_semester_id' => $this->semester->id,
            'updated_by' => $this->admin->id,
        ]
    );

    $level = SchoolLevel::firstOrCreate(['name' => 'X'], ['name_other' => 'Sepuluh']);
    $major = StudentMajor::firstOrCreate(['name' => 'Teknik Komputer'], ['abbreviation' => 'TKJ']);

    $this->rombel = SchoolRombel::create([
        'school_year_id' => $this->schoolYear->id,
        'school_level_id' => $level->id,
        'student_major_id' => $major->id,
        'name' => 'X-TKJ-1',
    ]);

    $this->student = Student::create([
        'name' => 'Siswa Satu',
        'gender_id' => $this->genderMale->id,
        'religion_id' => $religion->id,
        'district_id' => $district->id,
        'place_of_birth' => 'Jakarta',
        'date_of_birth' => '2008-01-01',
        'entry_date' => '2026-07-15',
    ]);

    StudentRombel::create([
        'student_id' => $this->student->id,
        'school_rombel' => $this->rombel->id,
    ]);

    $this->assignment = TeachingAssignment::create([
        'school_year_id' => $this->schoolYear->id,
        'semester_id' => $this->semester->id,
        'school_rombel_id' => $this->rombel->id,
        'subject_id' => $this->math->id,
        'employee_id' => $this->teacher->id,
        'hours_per_week' => 2,
        'status' => 'active',
        'version' => 1,
    ]);

    // Tuesday is day 2
    $this->schedule = TeachingSchedule::create([
        'teaching_assignment_id' => $this->assignment->id,
        'day_of_week' => 2,
        'period_start' => 1,
        'period_end' => 2,
        'start_time' => '07:30',
        'end_time' => '09:00',
        'status' => 'active',
        'version' => 1,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('T1: unauthenticated requests are rejected with 401', function () {
    $this->getJson('/api/dashboard/overview')->assertStatus(401);
    $this->getJson('/api/dashboard/pending-tasks')->assertStatus(401);
    $this->getJson('/api/dashboard/charts')->assertStatus(401);
});

test('T2: deactivated accounts are rejected with 403', function () {
    $this->teacherUser->status_active = false;
    $this->teacherUser->save();

    Sanctum::actingAs($this->teacherUser);

    $this->getJson('/api/dashboard/overview')->assertStatus(403);
    $this->getJson('/api/dashboard/pending-tasks')->assertStatus(403);
    $this->getJson('/api/dashboard/charts')->assertStatus(403);
});

test('T3: admin-sistem receives comprehensive overview with actual counts', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/dashboard/overview');
    $response->assertStatus(200);

    $data = $response->json('data');

    // Academic section
    expect($data['academic']['active_school_year'])->toBe('2026/2027');
    expect($data['academic']['active_semester'])->toBe('Semester I');
    expect($data['academic']['total_active_students'])->toBe(1);
    expect($data['academic']['total_active_rombels'])->toBe(1);

    // Employees section
    expect($data['employees']['total_teachers'])->toBe(1);
    expect($data['employees']['total_staff'])->toBe(1);

    // Letters & Leaves sections are present
    expect($data['letters'])->not->toBeNull();
    expect($data['leaves'])->not->toBeNull();

    // Teaching section has school-wide view
    expect($data['teaching']['view_type'])->toBe('school_wide');
    expect($data['teaching']['scheduled_teachers_count'])->toBe(1);

    // System overview for admin
    expect($data['system'])->not->toBeNull();
});

test('T4: operator-sekolah receives school-wide operational overview', function () {
    Sanctum::actingAs($this->operator);

    $response = $this->getJson('/api/dashboard/overview');
    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data['academic']['total_active_students'])->toBe(1);
    expect($data['employees']['total_teachers'])->toBe(1);
    expect($data['teaching']['view_type'])->toBe('school_wide');
});

test('T5: kepala-sekolah receives leadership overview', function () {
    Sanctum::actingAs($this->principal);

    $response = $this->getJson('/api/dashboard/overview');
    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data['academic']['total_active_students'])->toBe(1);
    expect($data['employees']['total_teachers'])->toBe(1);
    expect($data['teaching']['view_type'])->toBe('school_wide');
});

test('T6: guru receives teacher personal overview and unpermitted sections are omitted', function () {
    Sanctum::actingAs($this->teacherUser);

    $response = $this->getJson('/api/dashboard/overview');
    $response->assertStatus(200);

    $data = $response->json('data');

    // Teacher cannot view school-wide employees or general letters
    expect($data['employees'])->toBeNull();
    expect($data['letters'])->toBeNull();

    // Teaching view is personal
    expect($data['teaching']['view_type'])->toBe('teacher_personal');
    expect($data['teaching']['teacher_name'])->toBe('Guru Dashboard');
    expect($data['teaching']['today_sessions_count'])->toBe(1);
});

test('T7: plain staff without domain abilities has unpermitted domains as null', function () {
    Sanctum::actingAs($this->plainUser);

    $response = $this->getJson('/api/dashboard/overview');
    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data['academic']['total_active_students'])->toBeNull();
    expect($data['employees'])->toBeNull();
    expect($data['letters'])->toBeNull();
    expect($data['teaching'])->toBeNull();
    expect($data['archives'])->toBeNull();
});

test('T8: pending-tasks returns submitted letters and leaves for principal', function () {
    // Create an outgoing letter in submitted (review) status
    Letter::create([
        'direction' => Letter::DIRECTION_OUTGOING,
        'status' => Letter::STATUS_ACTIVE,
        'workflow_status' => Letter::WORKFLOW_REVIEW,
        'subject' => 'Surat Pengantar Dinas',
        'letter_number' => '420/001/SMK/2026',
        'received_date' => '2026-11-02',
        'letter_date' => '2026-11-02',
        'sender' => 'SMK Negeri 1',
        'recipient' => 'Dinas Pendidikan',
        'category' => 'dinas',
        'created_by' => $this->admin->id,
    ]);

    // Create a leave request in submitted status
    LeaveRequest::create([
        'subject_type' => 'student',
        'student_id' => $this->student->id,
        'school_year_id' => $this->schoolYear->id,
        'student_rombel_id' => $this->rombel->id,
        'type' => 'sakit',
        'reason' => 'Sakit demam',
        'start_date' => '2026-11-03',
        'end_date' => '2026-11-04',
        'status' => LeaveRequest::STATUS_SUBMITTED,
        'version' => 1,
        'requested_by' => $this->admin->id,
        'submitted_by' => $this->admin->id,
        'submitted_at' => now(),
    ]);

    Sanctum::actingAs($this->principal);

    $response = $this->getJson('/api/dashboard/pending-tasks');
    $response->assertStatus(200);

    $tasks = $response->json('data');
    expect(count($tasks))->toBeGreaterThanOrEqual(2);

    $domains = array_column($tasks, 'domain');
    expect($domains)->toContain('letters');
    expect($domains)->toContain('leaves');
});

test('T9: pending-tasks for teacher includes missing journals within grace window', function () {
    Sanctum::actingAs($this->teacherUser);

    $response = $this->getJson('/api/dashboard/pending-tasks');
    $response->assertStatus(200);

    $tasks = $response->json('data');
    $teachingTasks = array_filter($tasks, fn ($t) => $t['domain'] === 'teaching');
    expect(count($teachingTasks))->toBeGreaterThanOrEqual(1);

    $first = reset($teachingTasks);
    expect($first['category'])->toBe('Jurnal Mengajar Belum Diisi');
    expect($first['action_url'])->toBe('/public/teaching/journals');
});

test('T10: charts returns distribution and trend structure for authorized user', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/dashboard/charts');
    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data['students_by_level'])->not->toBeNull();
    expect($data['students_by_level']['labels'])->toContain('X-TKJ-1');
    expect($data['teaching_compliance_trend'])->not->toBeNull();
});
