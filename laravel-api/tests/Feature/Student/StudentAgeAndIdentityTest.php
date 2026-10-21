<?php

use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('age is dynamically calculated from birthdate using Asia/Jakarta timezone', function () {
    // Siswa lahir 21 Oktober 2010
    $student = new Student([
        'name' => 'Age Test Student',
        'birthdate' => '2010-10-21',
    ]);

    // 1 hari sebelum ulang tahun ke-16: 20 Oktober 2026
    $beforeBirthday = Carbon::create(2026, 10, 20, 12, 0, 0, 'Asia/Jakarta');
    expect($student->getAgeAsOf($beforeBirthday))->toBe(15);

    // Tepat di hari ulang tahun ke-16: 21 Oktober 2026
    $onBirthday = Carbon::create(2026, 10, 21, 0, 0, 0, 'Asia/Jakarta');
    expect($student->getAgeAsOf($onBirthday))->toBe(16);

    // 1 hari setelah ulang tahun ke-16: 22 Oktober 2026
    $afterBirthday = Carbon::create(2026, 10, 22, 18, 0, 0, 'Asia/Jakarta');
    expect($student->getAgeAsOf($afterBirthday))->toBe(16);
});

test('age returns null for null, empty, invalid, or future birthdate', function () {
    $studentNull = new Student(['name' => 'Null Birthdate', 'birthdate' => null]);
    expect($studentNull->age)->toBeNull();
    expect($studentNull->calculated_age)->toBeNull();

    $studentFuture = new Student([
        'name' => 'Future Student',
        'birthdate' => Carbon::now('Asia/Jakarta')->addDays(10)->toDateString(),
    ]);
    expect($studentFuture->age)->toBeNull();

    $studentInvalid = new Student(['name' => 'Invalid Birthdate', 'birthdate' => 'not-a-date']);
    expect($studentInvalid->age)->toBeNull();
});

test('identity string fields strictly preserve leading zeros', function () {
    $student = Student::create([
        'name' => 'Leading Zero Student',
        'nik' => '0012345678901234',
        'nis' => '007',
        'nisn' => '0001234567',
    ]);

    $fresh = Student::find($student->id);
    expect($fresh->nik)->toBe('0012345678901234');
    expect($fresh->nis)->toBe('007');
    expect($fresh->nisn)->toBe('0001234567');
    expect(str_starts_with($fresh->nik, '00'))->toBeTrue();
    expect(str_starts_with($fresh->nis, '00'))->toBeTrue();
    expect(str_starts_with($fresh->nisn, '000'))->toBeTrue();
});

test('student model fillable includes check_bpms along with other welfare aids', function () {
    $student = new Student;
    expect($student->getFillable())->toContain('check_bpms', 'check_kjp', 'check_pip', 'check_ppdbbersama');
});

test('student audit allowlist excludes medical history and parent salaries', function () {
    $student = new Student;
    $auditable = $student->getAuditableAttributes();

    expect($auditable)->toContain('name', 'nis', 'nisn', 'nik', 'gender_id', 'religion_id', 'version');

    // Strict privacy exclusion (NFR-01, DEC-20)
    expect($auditable)->not->toContain('history_illness');
    expect($auditable)->not->toContain('father_income');
    expect($auditable)->not->toContain('mother_income');
    expect($auditable)->not->toContain('guardian_income');
    expect($auditable)->not->toContain('father_hp_num');
    expect($auditable)->not->toContain('mother_hp_num');
});
