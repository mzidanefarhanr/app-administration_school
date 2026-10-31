<?php

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
use App\Models\Student;
use App\Models\StudentMajor;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    $this->seedAccessReferenceData();

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->plainUser = $this->makeUserWithRole();

    $province = Province::firstOrCreate(['name' => 'DKI Jakarta']);
    $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'Jakarta Utara']);
    $district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'Koja']);

    $gender = Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $religion = Religion::firstOrCreate(['name' => 'Islam']);
    $maritalStatus = MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);
    $status = EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap Yayasan'], ['abbreviation' => 'PTY']);
    $this->teacherType = EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $staffType = EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
    $employment = Employment::firstOrCreate(['name' => 'Tetap Yayasan'], ['description' => 'Tetap']);
    $subject = Subject::firstOrCreate(['name' => 'Matematika'], ['description' => 'Matematika']);

    $base = [
        'employee_status_id' => $status->id,
        'employment_id' => $employment->id,
        'subject_id' => $subject->id,
        'religion_id' => $religion->id,
        'district_id' => $district->id,
        'marital_status_id' => $maritalStatus->id,
        'gender_id' => $gender->id,
        'tmt_employee' => '2020-01-01',
    ];

    $this->teacher = Employee::create(array_merge($base, [
        'name' => 'Active Teacher',
        'employee_type_id' => $this->teacherType->id,
        'user_id' => $this->operator->id,
    ]));

    Employee::create(array_merge($base, [
        'name' => 'Active Staff',
        'employee_type_id' => $staffType->id,
    ]));

    $archivedTeacher = Employee::create(array_merge($base, [
        'name' => 'Archived Teacher',
        'employee_type_id' => $this->teacherType->id,
    ]));
    $archivedTeacher->delete();

    Employee::create(array_merge($base, [
        'name' => 'Exited Teacher',
        'employee_type_id' => $this->teacherType->id,
        'exit_date' => '2020-01-01',
    ]));

    $this->year = SchoolYear::create([
        'name' => '2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $this->otherYear = SchoolYear::create(['name' => '2025/2026']);
    $this->level = SchoolLevel::create(['name' => 'Grade 10']);
    $this->otherLevel = SchoolLevel::create(['name' => 'Grade 11']);
    $this->major = StudentMajor::create(['name' => 'Science']);

    $this->class = SchoolRombel::create([
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'name' => '10-A',
    ]);

    $this->activeStatus = StudentStatus::firstOrCreate(['id' => 1], ['name' => 'Aktif']);
});

function phase06ClassPayload(array $overrides = []): array
{
    return array_merge([
        'name' => '10-B',
        'school_year_id' => test()->year->id,
        'school_level_id' => test()->level->id,
        'student_major_id' => null,
        'class_teach_employee_id' => null,
    ], $overrides);
}

function phase06Membership(int $rombelId): StudentRombel
{
    static $sequence = 0;
    $sequence++;

    $student = Student::create([
        'name' => 'Synthetic Phase 06 Student '.$sequence,
        'nik' => '9000000000'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
        'version' => 1,
    ]);

    return StudentRombel::create([
        'student_id' => $student->id,
        'student_nik' => $student->nik,
        'school_rombel' => $rombelId,
        'student_status_id' => test()->activeStatus->id,
        'latest_student_entry_date' => '2026-07-15',
        'version' => 1,
    ]);
}

test('guest and read-only accounts cannot mutate classes', function () {
    $this->postJson('/api/school-rombels', phase06ClassPayload())->assertStatus(401);

    Sanctum::actingAs($this->principal);

    $this->getJson('/api/school-rombels')->assertStatus(200);
    $this->postJson('/api/school-rombels', phase06ClassPayload())->assertStatus(403);
    $this->putJson("/api/school-rombels/{$this->class->id}", phase06ClassPayload(['expected_version' => 1]))->assertStatus(403);
    $this->deleteJson("/api/school-rombels/{$this->class->id}", ['expected_version' => 1])->assertStatus(403);
});

test('operator creates a class with optional major and active teacher', function () {
    Sanctum::actingAs($this->operator);

    $response = $this->postJson('/api/school-rombels', phase06ClassPayload([
        'name' => ' 10-B ',
        'student_major_id' => $this->major->id,
        'class_teach_employee_id' => $this->teacher->id,
    ]));

    $response->assertStatus(201)
        ->assertJsonPath('data.name', '10-B')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.student_major.id', $this->major->id)
        ->assertJsonPath('data.class_teach_employee.id', $this->teacher->id)
        ->assertJsonPath('data.class_teach_id', $this->operator->id);

    expect(SchoolRombel::where('name', '10-B')->exists())->toBeTrue();

    $audit = Activity::where('table_name', 'school_rombels')
        ->where('information', 'Created')
        ->latest('id')
        ->first();
    expect($audit)->not->toBeNull()->and($audit->user_id)->toBe($this->operator->id);
});

test('class creation validates required fields, teacher eligibility, and soft-deleted years', function () {
    Sanctum::actingAs($this->operator);

    $this->postJson('/api/school-rombels', phase06ClassPayload(['name' => '']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');

    $this->postJson('/api/school-rombels', phase06ClassPayload(['school_level_id' => 999999]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('school_level_id');

    $staff = Employee::where('name', 'Active Staff')->firstOrFail();
    $this->postJson('/api/school-rombels', phase06ClassPayload(['class_teach_employee_id' => $staff->id]))
        ->assertStatus(422)
        ->assertJsonPath('errors.class_teach_employee_id.0', 'The selected homeroom teacher is not an active teacher.');

    $archivedTeacher = Employee::withTrashed()->where('name', 'Archived Teacher')->firstOrFail();
    $this->postJson('/api/school-rombels', phase06ClassPayload(['class_teach_employee_id' => $archivedTeacher->id]))
        ->assertStatus(422);

    $exitedTeacher = Employee::where('name', 'Exited Teacher')->firstOrFail();
    $this->postJson('/api/school-rombels', phase06ClassPayload(['class_teach_employee_id' => $exitedTeacher->id]))
        ->assertStatus(422);

    $deletedYear = SchoolYear::create(['name' => '2023/2024']);
    $deletedYear->delete();

    $this->postJson('/api/school-rombels', phase06ClassPayload(['school_year_id' => $deletedYear->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('school_year_id');
});

test('class names are unique per school year, case-insensitively', function () {
    Sanctum::actingAs($this->operator);

    $this->postJson('/api/school-rombels', phase06ClassPayload(['name' => '10-a']))
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'The class name has already been taken for this school year.');

    $this->postJson('/api/school-rombels', phase06ClassPayload([
        'name' => '10-A',
        'school_year_id' => $this->otherYear->id,
    ]))->assertStatus(201);
});

test('class update enforces expected_version and increments it', function () {
    Sanctum::actingAs($this->operator);

    $this->putJson("/api/school-rombels/{$this->class->id}", phase06ClassPayload(['name' => '10-A2']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('expected_version');

    $this->putJson("/api/school-rombels/{$this->class->id}", phase06ClassPayload([
        'name' => '10-A2',
        'expected_version' => 99,
    ]))->assertStatus(409)
        ->assertJsonPath('current_version', 1);

    $this->putJson("/api/school-rombels/{$this->class->id}", phase06ClassPayload([
        'name' => '10-A2',
        'student_major_id' => $this->major->id,
        'class_teach_employee_id' => $this->teacher->id,
        'expected_version' => 1,
    ]))->assertStatus(200)
        ->assertJsonPath('data.name', '10-A2')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.class_teach_employee.id', $this->teacher->id);

    $this->class->refresh();
    expect($this->class->version)->toBe(2);
});

test('class structural fields cannot change once membership history exists, but name and teacher can', function () {
    Sanctum::actingAs($this->operator);

    phase06Membership($this->class->id);

    $this->putJson("/api/school-rombels/{$this->class->id}", phase06ClassPayload([
        'name' => '10-A3',
        'school_level_id' => $this->otherLevel->id,
        'expected_version' => 1,
    ]))->assertStatus(422)->assertJsonValidationErrors('school_level_id');

    $this->putJson("/api/school-rombels/{$this->class->id}", phase06ClassPayload([
        'name' => '10-A3',
        'school_year_id' => $this->otherYear->id,
        'expected_version' => 1,
    ]))->assertStatus(422)->assertJsonValidationErrors('school_year_id');

    $this->putJson("/api/school-rombels/{$this->class->id}", phase06ClassPayload([
        'name' => '10-A3',
        'class_teach_employee_id' => $this->teacher->id,
        'expected_version' => 1,
    ]))->assertStatus(200)
        ->assertJsonPath('data.name', '10-A3')
        ->assertJsonPath('data.version', 2);
});

test('class without history can move to another year if the name is free', function () {
    Sanctum::actingAs($this->operator);

    $this->putJson("/api/school-rombels/{$this->class->id}", phase06ClassPayload([
        'name' => '10-A',
        'school_year_id' => $this->otherYear->id,
        'expected_version' => 1,
    ]))->assertStatus(200)
        ->assertJsonPath('data.school_year.id', $this->otherYear->id);
});

test('class delete requires expected_version and is blocked by any membership history', function () {
    Sanctum::actingAs($this->operator);

    $this->deleteJson("/api/school-rombels/{$this->class->id}", [])->assertStatus(422);

    $this->deleteJson("/api/school-rombels/{$this->class->id}", ['expected_version' => 99])
        ->assertStatus(409)
        ->assertJsonPath('current_version', 1);

    $membership = phase06Membership($this->class->id);
    $membership->update(['latest_student_exit_date' => '2026-08-01']);

    $this->deleteJson("/api/school-rombels/{$this->class->id}", ['expected_version' => 1])
        ->assertStatus(422)
        ->assertJsonPath('errors.id.0', 'This class already has membership history and cannot be deleted.');

    $freeClass = SchoolRombel::create([
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'name' => '10-C',
    ]);

    $this->deleteJson("/api/school-rombels/{$freeClass->id}", ['expected_version' => 1])
        ->assertStatus(200);

    $deleted = SchoolRombel::withTrashed()->find($freeClass->id);
    expect($deleted)->not->toBeNull()
        ->and($deleted->trashed())->toBeTrue()
        ->and($deleted->version)->toBe(2);

    $audit = Activity::where('table_name', 'school_rombels')
        ->where('record_id', $freeClass->id)
        ->where('information', 'Deleted')
        ->first();
    expect($audit)->not->toBeNull();
});

test('class list can filter by school year and reports membership counts', function () {
    Sanctum::actingAs($this->operator);

    phase06Membership($this->class->id);

    SchoolRombel::create([
        'school_year_id' => $this->otherYear->id,
        'school_level_id' => $this->level->id,
        'name' => '9-A',
    ]);

    $response = $this->getJson('/api/school-rombels?school_year_id='.$this->year->id)
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', '10-A')
        ->assertJsonPath('data.0.memberships_count', 1)
        ->assertJsonPath('data.0.active_members_count', 1)
        ->assertJsonPath('data.0.school_year.is_locked', false);

    expect($response->json('data.0.school_level.name'))->toBe('Grade 10');
});

test('teacher options expose only active Pendidik profiles without extra personal data', function () {
    Sanctum::actingAs($this->operator);

    $response = $this->getJson('/api/employees/teacher-options')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Teacher');

    $keys = array_keys($response->json('data.0'));
    sort($keys);
    expect($keys)->toBe(['id', 'name']);

    $this->getJson('/api/employees/teacher-options?search=Active Staff')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');

    Sanctum::actingAs($this->plainUser);
    $this->getJson('/api/employees/teacher-options')->assertStatus(403);
});
