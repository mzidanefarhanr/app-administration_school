<?php

use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEntry;
use App\Models\StudentStatus;
use App\Services\StudentBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
    $this->statusMutasi = StudentStatus::firstOrCreate(['id' => 2], ['name' => 'Mutasi Keluar']);
    $this->entryNew = StudentEntry::firstOrCreate(['name' => 'Siswa Baru']);
});

test('backfill correctly resolves valid 16-digit NIK with leading zeros', function () {
    $student = Student::create([
        'name' => 'Student Leading Zero',
        'nik' => '0012345678901234',
        'birthdate' => '2010-05-15',
    ]);

    // Insert legacy row with student_id = NULL
    $rombelRowId = DB::table('student_rombels')->insertGetId([
        'version' => 1,
        'student_id' => null,
        'student_nik' => '0012345678901234',
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = new StudentBackfillService;
    $stats = $service->backfill(false);

    expect($stats['matched'])->toBe(1);
    expect($stats['total_unresolved'])->toBe(0);

    $updated = DB::table('student_rombels')->where('id', $rombelRowId)->first();
    expect($updated->student_id)->toBe($student->id);
    expect($updated->student_nik)->toBe('0012345678901234');
});

test('backfill leaves null or empty NIK unresolved without touching row', function () {
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = OFF');
    }

    $id1 = DB::table('student_rombels')->insertGetId([
        'version' => 1,
        'student_id' => null,
        'student_nik' => null,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $id2 = DB::table('student_rombels')->insertGetId([
        'version' => 1,
        'student_id' => null,
        'student_nik' => '',
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusMutasi->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = new StudentBackfillService;
    $stats = $service->backfill(false);

    expect($stats['unresolved_empty'])->toBe(2);
    expect($stats['matched'])->toBe(0);

    expect(DB::table('student_rombels')->where('id', $id1)->value('student_id'))->toBeNull();
    expect(DB::table('student_rombels')->where('id', $id2)->value('student_id'))->toBeNull();
});

test('backfill leaves invalid NIK format unresolved', function () {
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = OFF');
    }

    $id1 = DB::table('student_rombels')->insertGetId([
        'version' => 1,
        'student_id' => null,
        'student_nik' => '12345', // Short
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $id2 = DB::table('student_rombels')->insertGetId([
        'version' => 1,
        'student_id' => null,
        'student_nik' => '123456789012345A', // Contains letter
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusMutasi->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = new StudentBackfillService;
    $stats = $service->backfill(false);

    expect($stats['unresolved_invalid_format'])->toBe(2);
    expect($stats['matched'])->toBe(0);

    expect(DB::table('student_rombels')->where('id', $id1)->value('student_id'))->toBeNull();
    expect(DB::table('student_rombels')->where('id', $id2)->value('student_id'))->toBeNull();
});

test('backfill leaves unmatched orphan NIK unresolved', function () {
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = OFF');
    }

    $id = DB::table('student_rombels')->insertGetId([
        'version' => 1,
        'student_id' => null,
        'student_nik' => '9999999999999999', // Valid 16-digit, but no student in table
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = new StudentBackfillService;
    $stats = $service->backfill(false);

    expect($stats['unresolved_unmatched'])->toBe(1);
    expect($stats['matched'])->toBe(0);

    expect(DB::table('student_rombels')->where('id', $id)->value('student_id'))->toBeNull();
});

test('backfill leaves ambiguous duplicate NIK in students table unresolved without picking first', function () {
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = OFF');
        \Illuminate\Support\Facades\Schema::dropIfExists('students_temp');
        DB::statement('CREATE TEMPORARY TABLE students_temp AS SELECT * FROM students');
        \Illuminate\Support\Facades\Schema::dropIfExists('students');
        \Illuminate\Support\Facades\Schema::create('students', function ($table) {
            $table->id();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('name');
            $table->string('nik')->nullable(); // non-unique for ambiguity test
            $table->timestamps();
        });
        DB::statement('INSERT INTO students (id, version, name, nik, created_at, updated_at) SELECT id, version, name, nik, created_at, updated_at FROM students_temp');
        \Illuminate\Support\Facades\Schema::dropIfExists('students_temp');
    }

    // Insert duplicate directly via query builder
    DB::table('students')->insert([
        'name' => 'Duplicate One',
        'nik' => '3201010101010001',
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('students')->insert([
        'name' => 'Duplicate Two',
        'nik' => '3201010101010001',
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $id = DB::table('student_rombels')->insertGetId([
        'version' => 1,
        'student_id' => null,
        'student_nik' => '3201010101010001',
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = new StudentBackfillService;
    $stats = $service->backfill(false);

    expect($stats['unresolved_ambiguous'])->toBe(1);
    expect($stats['matched'])->toBe(0);

    // Must NOT pick candidate 1 or candidate 2
    expect(DB::table('student_rombels')->where('id', $id)->value('student_id'))->toBeNull();
});

test('backfill is strictly idempotent and does not overwrite existing student_id', function () {
    $studentA = Student::create([
        'name' => 'Student A',
        'nik' => '1111111111111111',
    ]);
    $studentB = Student::create([
        'name' => 'Student B',
        'nik' => '2222222222222222',
    ]);

    // Already resolved row pointing to Student A, but with student_nik = B
    $id = DB::table('student_rombels')->insertGetId([
        'version' => 1,
        'student_id' => $studentA->id,
        'student_nik' => '2222222222222222',
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = new StudentBackfillService;
    $stats = $service->backfill(false);

    expect($stats['already_resolved'])->toBe(1);
    expect($stats['matched'])->toBe(0);

    // Existing student_id must NOT be overwritten!
    expect(DB::table('student_rombels')->where('id', $id)->value('student_id'))->toBe($studentA->id);
});

test('dry run mode reports counts without mutating rows', function () {
    $student = Student::create([
        'name' => 'Student Dry Run',
        'nik' => '3333333333333333',
    ]);

    $id = DB::table('student_rombels')->insertGetId([
        'version' => 1,
        'student_id' => null,
        'student_nik' => '3333333333333333',
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = new StudentBackfillService;
    $stats = $service->backfill(true); // dryRun = true

    expect($stats['matched'])->toBe(1);

    // Row must still be NULL!
    expect(DB::table('student_rombels')->where('id', $id)->value('student_id'))->toBeNull();
});

test('artisan command app:student-backfill executes cleanly without leaking PII', function () {
    $student = Student::create([
        'name' => 'Secret Student Name',
        'nik' => '4444444444444444',
    ]);

    DB::table('student_rombels')->insertGetId([
        'version' => 1,
        'student_id' => null,
        'student_nik' => '4444444444444444',
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('app:student-backfill', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Total Rombel Rows Evaluated')
        ->expectsOutputToContain('Matched & Backfilled')
        ->doesntExpectOutputToContain('Secret Student Name')
        ->doesntExpectOutputToContain('4444444444444444');
});
