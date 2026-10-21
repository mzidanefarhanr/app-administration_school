<?php

use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->year2026 = SchoolYear::create(['name' => '2026/2027']);
    $this->year2027 = SchoolYear::create(['name' => '2027/2028']);

    $this->level10 = SchoolLevel::create(['name' => 'Grade 10']);
    $this->level11 = SchoolLevel::create(['name' => 'Grade 11']);

    $this->rombel10A = SchoolRombel::create([
        'school_year_id' => $this->year2026->id,
        'school_level_id' => $this->level10->id,
        'name' => '10-A',
    ]);

    $this->rombel10B = SchoolRombel::create([
        'school_year_id' => $this->year2026->id,
        'school_level_id' => $this->level10->id,
        'name' => '10-B',
    ]);

    $this->rombel11A = SchoolRombel::create([
        'school_year_id' => $this->year2027->id,
        'school_level_id' => $this->level11->id,
        'name' => '11-A',
    ]);

    $this->statusActive = StudentStatus::firstOrCreate(['name' => 'Aktif']);
    $this->statusMutasi = StudentStatus::firstOrCreate(['name' => 'Mutasi Keluar']);
});

test('first active rombel membership in an academic year succeeds', function () {
    $student = Student::create([
        'name' => 'Active Student One',
        'nik' => '1234567890123456',
    ]);

    $sr = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel10A->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    expect($sr->id)->toBeGreaterThan(0);
    expect($sr->student_id)->toBe($student->id);
    expect($sr->student_nik)->toBe('1234567890123456');
});

test('second active rombel membership for same student in same academic year is rejected', function () {
    $student = Student::create([
        'name' => 'Active Student Two',
        'nik' => '2234567890123456',
    ]);

    // First active enrollment in 10-A (year 2026/2027)
    StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel10A->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    // Second active enrollment in 10-B (same year 2026/2027) -> MUST FAIL!
    expect(function () use ($student) {
        StudentRombel::create([
            'student_id' => $student->id,
            'school_rombel' => $this->rombel10B->id,
            'student_status_id' => $this->statusActive->id,
        ]);
    })->toThrow(\DomainException::class);
});

test('active rombel in different academic year is permitted for the same student', function () {
    $student = Student::create([
        'name' => 'Promoted Student',
        'nik' => '3234567890123456',
    ]);

    // Enrolled in 10-A (2026/2027)
    $sr1 = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel10A->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    // Next year: enrolled in 11-A (2027/2028)
    $sr2 = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel11A->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    expect($sr1->id)->toBeGreaterThan(0);
    expect($sr2->id)->toBeGreaterThan(0);
    expect($sr1->id)->not->toBe($sr2->id);
});

test('multiple rombels in same year are permitted if previous membership is inactive preserving movement history', function () {
    $student = Student::create([
        'name' => 'Transfer Student',
        'nik' => '4234567890123456',
    ]);

    // Enrolled in 10-A, but then marked as Mutasi (status 2) or has exit date
    $sr1 = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel10A->id,
        'student_status_id' => $this->statusMutasi->id,
        'latest_student_exit_date' => '2026-09-01',
    ]);

    // Transferred to 10-B as Active
    $sr2 = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel10B->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    expect($sr1->id)->toBeGreaterThan(0);
    expect($sr2->id)->toBeGreaterThan(0);
    // History in 10-A is preserved!
    expect(StudentRombel::where('student_id', $student->id)->count())->toBe(2);
});

test('updating own active rombel row succeeds without triggering self-conflict', function () {
    $student = Student::create([
        'name' => 'Update Self Student',
        'nik' => '5234567890123456',
    ]);

    $sr = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel10A->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    $sr->latest_information = 'Updated notes';
    $sr->save();

    expect($sr->fresh()->latest_information)->toBe('Updated notes');
});

test('student without NIK can be enrolled in rombel via student_id', function () {
    $studentWithoutNik = Student::create([
        'name' => 'Student No NIK',
        'nik' => null,
    ]);

    $sr = StudentRombel::create([
        'student_id' => $studentWithoutNik->id,
        'school_rombel' => $this->rombel10A->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    expect($sr->id)->toBeGreaterThan(0);
    expect($sr->student_id)->toBe($studentWithoutNik->id);
    expect($sr->student_nik)->toBeNull();
});
