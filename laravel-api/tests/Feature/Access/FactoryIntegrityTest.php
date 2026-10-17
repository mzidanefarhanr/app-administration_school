<?php

use App\Models\Employee;
use App\Models\Student;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

test('student factory produces valid record with schema compliance and valid foreign keys', function () {
    $this->seedAccessReferenceData();

    $student = Student::factory()->create();

    expect($student)->not->toBeNull();
    expect($student->id)->toBeGreaterThan(0);
    expect(strlen($student->nik))->toBe(16);
    expect(is_string($student->nik))->toBeTrue();
    expect($student->gender_id)->toBeGreaterThan(0);
    expect($student->religion_id)->toBeGreaterThan(0);
    expect($student->education_level_id)->toBeGreaterThan(0);
    expect($student->family_status_id)->toBeGreaterThan(0);
});

test('student factory preserves leading zero on identification numbers', function () {
    $this->seedAccessReferenceData();

    $student = Student::factory()->create([
        'nik' => '0123456789012345',
        'nis' => '00123',
        'nisn' => '0001234567',
    ]);

    $fresh = Student::find($student->id);
    expect($fresh->nik)->toBe('0123456789012345');
    expect(str_starts_with($fresh->nik, '0'))->toBeTrue();
    expect($fresh->nis)->toBe('00123');
    expect(str_starts_with($fresh->nis, '00'))->toBeTrue();
    expect($fresh->nisn)->toBe('0001234567');
    expect(str_starts_with($fresh->nisn, '000'))->toBeTrue();
});

test('employee factory produces valid record with user_nik relationship and foreign keys', function () {
    $this->seedAccessReferenceData();

    $employee = Employee::factory()->create();

    expect($employee)->not->toBeNull();
    expect($employee->id)->toBeGreaterThan(0);
    expect(strlen($employee->user_nik))->toBe(16);
    expect(is_string($employee->user_nik))->toBeTrue();
    expect($employee->employee_status_id)->toBeGreaterThan(0);
    expect($employee->employee_type_id)->toBeGreaterThan(0);
    expect($employee->employment_id)->toBeGreaterThan(0);
    expect($employee->subject_id)->toBeGreaterThan(0);
    expect($employee->religion_id)->toBeGreaterThan(0);
    expect($employee->district_id)->toBeGreaterThan(0);
    expect($employee->marital_status_id)->toBeGreaterThan(0);
    expect($employee->gender_id)->toBeGreaterThan(0);
});
