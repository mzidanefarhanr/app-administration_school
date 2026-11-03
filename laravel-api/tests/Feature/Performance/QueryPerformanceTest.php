<?php

use App\Models\Employee;
use App\Models\Gender;
use App\Models\OperatorReminder;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

beforeEach(function () {
    $this->seedAccessReferenceData();

    $this->admin = $this->makeUserWithRole('admin-sistem');
    $this->operator = $this->makeUserWithRole('operator-sekolah');

    // Warm up roles and abilities on auth actors to prevent first-request auth overhead in query counts
    $this->admin->load('roles.abilities');
    $this->operator->load('roles.abilities');

    $this->schoolYear = SchoolYear::firstOrCreate(
        ['name' => '2026/2027'],
        ['start_date' => '2026-07-01', 'end_date' => '2027-06-30']
    );

    $this->level = SchoolLevel::firstOrCreate(['name' => 'Grade 10']);
    $this->rombel = SchoolRombel::firstOrCreate(
        ['name' => '10-A', 'school_year_id' => $this->schoolYear->id],
        ['school_level_id' => $this->level->id]
    );

    $this->male = Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $this->female = Gender::firstOrCreate(['name' => 'Perempuan']);
    $this->activeStatus = StudentStatus::firstOrCreate(['id' => 1], ['name' => 'Aktif']);
    $this->employeeStatus = \App\Models\EmployeeStatus::firstOrCreate(['name' => 'PNS'], ['abbreviation' => 'PNS']);
    $this->employment = \App\Models\Employment::firstOrCreate(['name' => 'Guru Kelas'], ['description' => 'Guru Kelas']);
});

test('P1: per_page hard limit of 100 is strictly enforced across resource list endpoints', function () {
    Sanctum::actingAs($this->admin);

    // 1. Students
    $this->getJson("/api/students?school_year_id={$this->schoolYear->id}&per_page=100")->assertOk();
    $this->getJson("/api/students?school_year_id={$this->schoolYear->id}&per_page=101")->assertStatus(422);

    // 2. Employees
    $this->getJson('/api/employees?per_page=100')->assertOk();
    $this->getJson('/api/employees?per_page=101')->assertStatus(422);

    // 3. Tasks
    $this->getJson('/api/tasks?per_page=100')->assertOk();
    $this->getJson('/api/tasks?per_page=101')->assertStatus(422);

    // 4. Teaching Schedules
    $this->getJson('/api/teaching/schedules?per_page=100')->assertOk();
    $this->getJson('/api/teaching/schedules?per_page=101')->assertStatus(422);

    // 5. Teaching Assignments
    $this->getJson('/api/teaching/assignments?per_page=100')->assertOk();
    $this->getJson('/api/teaching/assignments?per_page=101')->assertStatus(422);

    // 6. Teaching Journals
    $this->getJson('/api/teaching/journals?per_page=100')->assertOk();
    $this->getJson('/api/teaching/journals?per_page=101')->assertStatus(422);

    // 7. Incoming Letters
    $this->getJson('/api/letters/incoming?per_page=100')->assertOk();
    $this->getJson('/api/letters/incoming?per_page=101')->assertStatus(422);

    // 8. Outgoing Letters
    $this->getJson('/api/letters/outgoing?per_page=100')->assertOk();
    $this->getJson('/api/letters/outgoing?per_page=101')->assertStatus(422);

    // 9. Leave Requests
    $this->getJson('/api/leaves?per_page=100')->assertOk();
    $this->getJson('/api/leaves?per_page=101')->assertStatus(422);

    // 10. Archives
    $this->getJson('/api/archives?per_page=100')->assertOk();
    $this->getJson('/api/archives?per_page=101')->assertStatus(422);

    // 11. Activities
    $this->getJson('/api/activities?per_page=100')->assertOk();
    $this->getJson('/api/activities?per_page=101')->assertStatus(422);
});

test('P2: operational tasks endpoint exhibits O(1) query scaling and zero N+1 queries', function () {
    Sanctum::actingAs($this->operator);

    // Warmup request to isolate Sanctum authentication queries
    $this->getJson('/api/tasks?tab=manual&per_page=1')->assertOk();

    // Create 5 reminders
    for ($i = 1; $i <= 5; $i++) {
        OperatorReminder::create([
            'user_id' => $this->operator->id,
            'title' => "Reminder {$i}",
            'priority' => OperatorReminder::PRIORITY_HIGH,
            'status' => OperatorReminder::STATUS_PENDING,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson('/api/tasks?tab=manual&per_page=5')->assertOk();
    $queriesFor5 = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Create 20 more reminders (total 25)
    for ($i = 6; $i <= 25; $i++) {
        OperatorReminder::create([
            'user_id' => $this->operator->id,
            'title' => "Reminder {$i}",
            'priority' => OperatorReminder::PRIORITY_INFO,
            'status' => OperatorReminder::STATUS_PENDING,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson('/api/tasks?tab=manual&per_page=25')->assertOk();
    $queriesFor25 = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Query count must remain identical regardless of whether 5 or 25 items are returned
    expect($queriesFor25)->toBe($queriesFor5)
        ->and($queriesFor25)->toBeLessThanOrEqual(5);
});

test('P3: student list endpoint exhibits O(1) query scaling when data volume increases', function () {
    Sanctum::actingAs($this->admin);

    // Warmup request with valid per_page
    $this->getJson("/api/students?school_year_id={$this->schoolYear->id}&per_page=10")->assertOk();

    // Seed 5 students with rombel membership
    for ($i = 1; $i <= 5; $i++) {
        $student = Student::create([
            'name' => "Student {$i}",
            'nik' => '317101'.str_pad((string) $i, 10, '0', STR_PAD_LEFT),
            'nis' => 'NIS'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'nisn' => 'NISN'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
            'gender_id' => $this->male->id,
        ]);
        StudentRombel::create([
            'student_id' => $student->id,
            'student_nik' => $student->nik,
            'school_rombel' => $this->rombel->id,
            'student_status_id' => $this->activeStatus->id,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson("/api/students?school_year_id={$this->schoolYear->id}&per_page=10")->assertOk();
    $queriesFor5 = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Add 20 more students (total 25)
    for ($i = 6; $i <= 25; $i++) {
        $student = Student::create([
            'name' => "Student {$i}",
            'nik' => '317101'.str_pad((string) $i, 10, '0', STR_PAD_LEFT),
            'nis' => 'NIS'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'nisn' => 'NISN'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
            'gender_id' => $this->female->id,
        ]);
        StudentRombel::create([
            'student_id' => $student->id,
            'student_nik' => $student->nik,
            'school_rombel' => $this->rombel->id,
            'student_status_id' => $this->activeStatus->id,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson("/api/students?school_year_id={$this->schoolYear->id}&per_page=20")->assertOk();
    $queriesFor25 = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Scale invariant: query count must not grow with row count
    expect($queriesFor25)->toBe($queriesFor5)
        ->and($queriesFor25)->toBeLessThanOrEqual(15);
});

test('P4: employee list endpoint exhibits O(1) query scaling when data volume increases', function () {
    Sanctum::actingAs($this->admin);

    // Warmup request with valid per_page
    $this->getJson('/api/employees?per_page=10')->assertOk();

    // Seed 5 employees using factory
    for ($i = 1; $i <= 5; $i++) {
        Employee::factory()->create([
            'name' => "Employee {$i}",
            'nik' => '327101'.str_pad((string) $i, 10, '0', STR_PAD_LEFT),
            'nuptk' => 'NUPTK'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson('/api/employees?per_page=10')->assertOk();
    $queriesFor5 = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Add 20 more employees
    for ($i = 6; $i <= 25; $i++) {
        Employee::factory()->create([
            'name' => "Employee {$i}",
            'nik' => '327101'.str_pad((string) $i, 10, '0', STR_PAD_LEFT),
            'nuptk' => 'NUPTK'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson('/api/employees?per_page=20')->assertOk();
    $queriesFor25 = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Scale invariant: query count must not grow with row count
    expect($queriesFor25)->toBe($queriesFor5)
        ->and($queriesFor25)->toBeLessThanOrEqual(10);
});

test('P5: dashboard pending tasks endpoint meets the strict query budget (<= 10 SQL queries)', function () {
    Sanctum::actingAs($this->admin);

    // Warmup request
    $this->getJson('/api/dashboard/pending-tasks')->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->getJson('/api/dashboard/pending-tasks');
    $queriesCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonStructure(['success', 'data']);

    expect($queriesCount)->toBeLessThanOrEqual(10);
});

test('P6: student and employee CSV exports stream memory-safe data with UTF-8 BOM', function () {
    Sanctum::actingAs($this->admin);

    // Create a student and employee
    Student::create([
        'name' => 'Export Student',
        'nik' => '3171019999999999',
        'nis' => 'NIS9999',
        'nisn' => 'NISN999999',
        'gender_id' => $this->male->id,
    ]);

    $studentExport = $this->get("/api/students/export?school_year_id={$this->schoolYear->id}&placement=all");
    $studentExport->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('x-content-type-options', 'nosniff');

    // Verify UTF-8 BOM is present at beginning of stream
    $content = $studentExport->streamedContent();
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($content)->toContain('Name')
        ->and($content)->toContain('Export Student');

    Employee::factory()->create([
        'name' => 'Export Employee',
        'nik' => '3271018888888888',
        'nuptk' => 'NUPTK888888',
        'gender_id' => $this->male->id,
    ]);

    $employeeExport = $this->get('/api/employees/export');
    $employeeExport->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('x-content-type-options', 'nosniff');

    $empContent = $employeeExport->streamedContent();
    expect(str_starts_with($empContent, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($empContent)->toContain('Name')
        ->and($empContent)->toContain('Export Employee');
});
