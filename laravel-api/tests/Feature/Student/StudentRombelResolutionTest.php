<?php

use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->year = SchoolYear::create(['name' => '2026/2027']);
    $this->level = SchoolLevel::create(['name' => 'Grade 10']);
    $this->rombel = SchoolRombel::create([
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'name' => '10-A',
    ]);
    $this->statusActive = StudentStatus::firstOrCreate(['id' => 1], ['name' => 'Aktif']);
    $this->statusInactive = StudentStatus::firstOrCreate(['id' => 2], ['name' => 'Mutasi Keluar']);
});

test('primary relation student_id is never overridden by different student_nik', function () {
    $primaryStudent = Student::create([
        'name' => 'Real Primary Student',
        'nik' => '1111222233334444',
    ]);

    $decoyStudent = Student::create([
        'name' => 'Decoy Student',
        'nik' => '9999888877776666',
    ]);

    // Rombel has student_id pointing to primary, but student_nik set to decoy
    $sr = new StudentRombel([
        'student_id' => $primaryStudent->id,
        'student_nik' => $decoyStudent->nik,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    $resolved = $sr->resolveStudent();
    expect($resolved)->not->toBeNull();
    expect($resolved->id)->toBe($primaryStudent->id);
    expect($resolved->name)->toBe('Real Primary Student');
});

test('fallback studentNik resolves only when unique and student_id is null', function () {
    $student = Student::create([
        'name' => 'Legacy Unique Student',
        'nik' => '5555666677778888',
    ]);

    $sr = new StudentRombel([
        'student_id' => null,
        'student_nik' => '5555666677778888',
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    $resolved = $sr->resolveStudent();
    expect($resolved)->not->toBeNull();
    expect($resolved->id)->toBe($student->id);
    expect($resolved->name)->toBe('Legacy Unique Student');
});

test('fallback studentNik returns null for unmatched orphan nik', function () {
    $sr = new StudentRombel([
        'student_id' => null,
        'student_nik' => '0000000000000000', // Unmatched
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    expect($sr->resolveStudent())->toBeNull();
});

test('fallback studentNik returns null for ambiguous nik without picking first candidate', function () {
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = OFF');
        \Illuminate\Support\Facades\Schema::dropIfExists('students_temp');
        DB::statement('CREATE TEMPORARY TABLE students_temp AS SELECT * FROM students');
        \Illuminate\Support\Facades\Schema::dropIfExists('students');
        \Illuminate\Support\Facades\Schema::create('students', function ($table) {
            $table->id();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('name');
            $table->string('nik')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        DB::statement('INSERT INTO students (id, version, name, nik, created_at, updated_at) SELECT id, version, name, nik, created_at, updated_at FROM students_temp');
        \Illuminate\Support\Facades\Schema::dropIfExists('students_temp');
    }

    DB::table('students')->insert([
        'name' => 'Ambiguous Candidate 1',
        'nik' => '7777777777777777',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('students')->insert([
        'name' => 'Ambiguous Candidate 2',
        'nik' => '7777777777777777',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sr = new StudentRombel([
        'student_id' => null,
        'student_nik' => '7777777777777777',
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    // MUST NOT pick Candidate 1 or Candidate 2
    expect($sr->resolveStudent())->toBeNull();
});

test('export safely handles unresolved legacy rows without attributing to wrong student', function () {
    // 1 valid student
    $validStudent = Student::create([
        'name' => 'Valid Student',
        'nik' => '1212121212121212',
        'nis' => '010',
    ]);
    StudentRombel::create([
        'student_id' => $validStudent->id,
        'student_nik' => $validStudent->nik,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    // 1 unlinked / orphan legacy row (inactive status)
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = OFF');
    }
    DB::table('student_rombels')->insert([
        'version' => 1,
        'student_id' => null,
        'student_nik' => '9999999999999999',
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusInactive->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $operator = User::factory()->create(['status_active' => true]);
    $role = \App\Models\Role::firstOrCreate(['name' => 'School Operator', 'slug' => 'operator-sekolah']);
    $role->abilities()->sync(
        \App\Models\Ability::whereIn('name', ['students.view', 'students.export'])->pluck('id')
    );
    $operator->roles()->attach($role->id, ['assigned_by' => $operator->id]);

    Sanctum::actingAs($operator);

    $response = $this->get("/api/students/export?school_year_id={$this->year->id}");
    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Valid Student');
    expect($csv)->toContain('1212121212121212');
    // Does NOT crash, and does not leak false student names
    expect($csv)->not->toContain('Decoy');
});
