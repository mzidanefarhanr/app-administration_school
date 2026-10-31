<?php

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
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use App\Models\Subject;
use App\Services\DataQuality\EmployeeCompletenessInspector;
use App\Services\DataQuality\StudentCompletenessInspector;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->asOf = CarbonImmutable::parse('2026-10-31 10:00:00', 'Asia/Jakarta');

    $this->level = SchoolLevel::create(['name' => 'DQ Level']);
    $this->year = SchoolYear::create(['name' => '2030/2031']);
    $this->rombel = SchoolRombel::create([
        'school_level_id' => $this->level->id,
        'school_year_id' => $this->year->id,
        'name' => 'DQ-10A',
    ]);

    $this->gender = Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $this->religion = Religion::firstOrCreate(['name' => 'Islam']);
    $this->studentStatus = StudentStatus::firstOrCreate(['id' => 1], ['name' => 'Aktif']);

    $this->makeStudent = function (array $attrs = []): Student {
        static $seq = 0;
        $seq++;

        return Student::create(array_merge([
            'name' => 'DQ Student '.$seq,
            'nik' => '01'.str_pad((string) $seq, 14, '0', STR_PAD_LEFT),
            'nis' => 'DQ-NIS-'.$seq,
            'nisn' => str_pad((string) $seq, 10, '0', STR_PAD_LEFT),
            'gender_id' => $this->gender->id,
            'religion_id' => $this->religion->id,
            'birthplace' => 'DQ City',
            'birthdate' => '2010-01-15',
        ], $attrs));
    };

    $this->placeStudent = function (Student $student): StudentRombel {
        return StudentRombel::create([
            'student_id' => $student->id,
            'student_nik' => $student->nik,
            'school_rombel' => $this->rombel->id,
            'student_status_id' => $this->studentStatus->id,
        ]);
    };

    $province = Province::firstOrCreate(['name' => 'DQ Province']);
    $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'DQ Regency']);
    $this->district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'DQ District']);
    $this->maritalStatus = MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);
    $this->employeeStatus = EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap'], ['abbreviation' => 'PT']);
    $this->employment = Employment::firstOrCreate(['name' => 'Tetap'], ['description' => 'Tetap']);
    $this->teacherType = EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $this->staffType = EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
    $this->subject = Subject::firstOrCreate(['name' => 'Matematika'], ['description' => 'Matematika']);

    $this->makeEmployee = function (array $attrs = []): Employee {
        static $seq = 0;
        $seq++;

        return Employee::create(array_merge([
            'name' => 'DQ Employee '.$seq,
            'nik' => '32'.str_pad((string) $seq, 14, '0', STR_PAD_LEFT),
            'nuptk' => 'DQ-NUPTK-'.$seq,
            'employee_type_id' => $this->teacherType->id,
            'employee_status_id' => $this->employeeStatus->id,
            'employment_id' => $this->employment->id,
            'gender_id' => $this->gender->id,
            'religion_id' => $this->religion->id,
            'marital_status_id' => $this->maritalStatus->id,
            'district_id' => $this->district->id,
            'subject_id' => $this->subject->id,
            'tmt_employee' => '2020-01-01',
        ], $attrs));
    };
});

// ─────────────────────────────────────────────────────────────────────────────
// Siswa
// ─────────────────────────────────────────────────────────────────────────────

test('student summary separates placed incomplete, blocked, not evaluated, and archived', function () {
    $complete = ($this->makeStudent)(['name' => 'A Complete']);
    ($this->placeStudent)($complete);

    $missingNik = ($this->makeStudent)(['name' => 'B Missing NIK', 'nik' => null]);
    ($this->placeStudent)($missingNik);

    $invalidNik = ($this->makeStudent)(['name' => 'C Invalid NIK', 'nik' => '12345']);
    ($this->placeStudent)($invalidNik);

    $invalidNisn = ($this->makeStudent)(['name' => 'D Invalid NISN', 'nisn' => '123']);
    ($this->placeStudent)($invalidNisn);

    $futureBirthdate = ($this->makeStudent)(['name' => 'E Future Birthdate', 'birthdate' => '2030-01-01']);
    ($this->placeStudent)($futureBirthdate);

    $blocked = ($this->makeStudent)(['name' => 'F Unplaced No NIK', 'nik' => null]);
    $notEvaluated = ($this->makeStudent)(['name' => 'G Unplaced Complete']);

    $archived = ($this->makeStudent)(['name' => 'H Archived', 'nik' => null, 'nis' => null, 'nisn' => null]);
    $archived->delete();

    $summary = (new StudentCompletenessInspector($this->asOf))->summary($this->year->id);

    expect($summary['denominator_basis'])->toBe('placed_students')
        ->and($summary['school_year']['id'])->toBe($this->year->id)
        ->and($summary['population'])->toMatchArray([
            'active_students' => 7,
            'placed' => 5,
            'unplaced' => 2,
            'not_evaluated' => 1,
            'archived_excluded' => 1,
        ])
        ->and($summary['records'])->toMatchArray([
            'complete' => 1,
            'incomplete' => 4,
            'complete_percent' => 20.0,
        ]);

    $fields = collect($summary['fields'])->keyBy('field');
    expect($fields['nik']['denominator'])->toBe(5)
        ->and($fields['nik']['missing'])->toBe(1)
        ->and($fields['nik']['invalid'])->toBe(1)
        ->and($fields['nik']['complete'])->toBe(3)
        ->and($fields['nik']['completeness_percent'])->toBe(60.0)
        ->and($fields['nik']['blocked'])->toBe(['missing' => 1, 'invalid' => 0])
        ->and($fields['nisn']['invalid'])->toBe(1)
        ->and($fields['birthdate']['invalid'])->toBe(1)
        ->and($fields['birthdate']['complete'])->toBe(4);

    $issues = (new StudentCompletenessInspector($this->asOf))
        ->issues($this->year->id, ['status' => 'all'])
        ->items();

    expect($issues)->toHaveCount(5)
        ->and(collect($issues)->where('type', 'blocked'))->toHaveCount(1)
        ->and(collect($issues)->firstWhere('type', 'blocked')['subject']['placement'])->toBe('unplaced')
        ->and(collect($issues)->firstWhere('type', 'blocked')['subject']['name'])->toBe('F Unplaced No NIK')
        ->and($summary['reviews']['open'])->toBe(5);

    $encoded = json_encode($issues);
    expect($encoded)->not->toContain($complete->nik)
        ->and($encoded)->not->toContain('2010-01-15');
});

test('student duplicates require name and birthdate and exclude archived records', function () {
    $a = ($this->makeStudent)(['name' => 'Same Person', 'birthdate' => '2011-02-03']);
    $b = ($this->makeStudent)(['name' => ' same person ', 'birthdate' => '2011-02-03']);
    $c = ($this->makeStudent)(['name' => 'Same Person', 'birthdate' => '2011-02-04']);
    $archived = ($this->makeStudent)(['name' => 'Same Person', 'birthdate' => '2011-02-03']);
    $archived->delete();

    foreach ([$a, $b, $c] as $student) {
        ($this->placeStudent)($student);
    }

    $before = Student::withTrashed()->count();
    $inspector = new StudentCompletenessInspector($this->asOf);
    $summary = $inspector->summary($this->year->id);
    $issues = $inspector->issues($this->year->id, ['type' => 'duplicate', 'status' => 'all'])->items();

    expect($summary['duplicates'])->toBe(['candidate_pairs' => 1, 'affected_subjects' => 2])
        ->and($issues)->toHaveCount(1)
        ->and($issues[0]['field'])->toBe('name_birthdate')
        ->and($issues[0]['subject']['id'])->toBe(min($a->id, $b->id))
        ->and($issues[0]['related']['id'])->toBe(max($a->id, $b->id))
        ->and($issues[0]['message'])->toBe('Possible duplicate: same name and birthdate.')
        ->and(Student::withTrashed()->count())->toBe($before);
});

test('student summary returns null percentages when the denominator is empty', function () {
    $summary = (new StudentCompletenessInspector($this->asOf))->summary($this->year->id);

    expect($summary['population'])->toMatchArray([
        'active_students' => 0,
        'placed' => 0,
        'unplaced' => 0,
        'not_evaluated' => 0,
        'archived_excluded' => 0,
    ])
        ->and($summary['records']['complete_percent'])->toBeNull()
        ->and(collect($summary['fields'])->every(fn ($field) => $field['completeness_percent'] === null))->toBeTrue()
        ->and($summary['reviews'])->toBe(['open' => 0, 'acknowledged' => 0, 'ignored' => 0, 'unresolved' => 0]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Pegawai
// ─────────────────────────────────────────────────────────────────────────────

test('employee summary separates active, separated, archived, and teacher-specific fields', function () {
    $completeTeacher = ($this->makeEmployee)(['name' => 'A Complete Teacher']);
    $gapTeacher = ($this->makeEmployee)([
        'name' => 'B Gap Teacher',
        'nuptk' => null,
        'subject_id' => null,
    ]);
    $staffWithSubject = ($this->makeEmployee)([
        'name' => 'C Staff With Subject',
        'employee_type_id' => $this->staffType->id,
        'nuptk' => null,
    ]);
    $staffMissingNik = ($this->makeEmployee)([
        'name' => 'D Staff Missing NIK',
        'employee_type_id' => $this->staffType->id,
        'nik' => null,
        'nuptk' => null,
        'subject_id' => null,
    ]);

    $separated = ($this->makeEmployee)([
        'name' => 'E Separated',
        'nik' => null,
        'nuptk' => null,
        'subject_id' => null,
        'exit_date' => '2020-01-01',
    ]);

    $archived = ($this->makeEmployee)([
        'name' => 'F Archived',
        'nik' => null,
        'nuptk' => null,
        'subject_id' => null,
    ]);
    $archived->delete();

    $summary = (new EmployeeCompletenessInspector($this->asOf))->summary();

    expect($summary['denominator_basis'])->toBe('active_employees')
        ->and($summary['population'])->toMatchArray([
            'active' => 4,
            'separated' => 1,
            'archived_excluded' => 1,
        ])
        ->and($summary['records'])->toMatchArray([
            'complete' => 1,
            'incomplete' => 3,
            'complete_percent' => 25.0,
        ]);

    $fields = collect($summary['fields'])->keyBy('field');
    expect($fields['nik']['denominator'])->toBe(4)
        ->and($fields['nik']['missing'])->toBe(1)
        ->and($fields['nik']['complete'])->toBe(3)
        ->and($fields['nik']['completeness_percent'])->toBe(75.0)
        ->and($fields['nuptk']['denominator'])->toBe(2)
        ->and($fields['nuptk']['missing'])->toBe(1)
        ->and($fields['nuptk']['completeness_percent'])->toBe(50.0)
        ->and($fields['subject_id']['denominator'])->toBe(4)
        ->and($fields['subject_id']['missing'])->toBe(1)
        ->and($fields['subject_id']['invalid'])->toBe(1)
        ->and($fields['subject_id']['complete'])->toBe(2);

    $issues = (new EmployeeCompletenessInspector($this->asOf))
        ->issues(['status' => 'all'])
        ->items();

    expect($issues)->toHaveCount(4)
        ->and(collect($issues)->where('field', 'subject_id')->pluck('type')->sort()->values()->all())
        ->toBe(['invalid', 'missing'])
        ->and(collect($issues)->firstWhere('type', 'invalid')['message'])
        ->toBe('Subject must be empty for a non-teaching employee.');

    $encoded = json_encode($issues);
    expect($encoded)->not->toContain($completeTeacher->nik)
        ->and($encoded)->not->toContain($gapTeacher->nik)
        ->and($encoded)->not->toContain('2020-01-01');
});

test('employee duplicates use active NUPTK and name plus birthdate, while archived and separated are excluded', function () {
    $teacherA = ($this->makeEmployee)(['name' => 'Nuptk Pair A', 'nuptk' => 'SHARED-NUPTK']);
    $teacherB = ($this->makeEmployee)(['name' => 'Nuptk Pair B', 'nuptk' => 'SHARED-NUPTK']);

    $staffA = ($this->makeEmployee)([
        'name' => 'Name Pair',
        'employee_type_id' => $this->staffType->id,
        'nuptk' => null,
        'subject_id' => null,
        'date_of_birth' => '1990-05-05',
    ]);
    $staffB = ($this->makeEmployee)([
        'name' => ' name pair ',
        'employee_type_id' => $this->staffType->id,
        'nuptk' => null,
        'subject_id' => null,
        'date_of_birth' => '1990-05-05',
    ]);

    $separatedSameNuptk = ($this->makeEmployee)([
        'name' => 'Separated Same NUPTK',
        'nuptk' => 'SHARED-NUPTK',
        'subject_id' => null,
        'exit_date' => '2020-01-01',
    ]);

    $archivedSameNuptk = ($this->makeEmployee)([
        'name' => 'Archived Same NUPTK',
        'nuptk' => 'SHARED-NUPTK',
        'subject_id' => null,
    ]);
    $archivedSameNuptk->delete();

    $summary = (new EmployeeCompletenessInspector($this->asOf))->summary();
    $issues = (new EmployeeCompletenessInspector($this->asOf))
        ->issues(['type' => 'duplicate', 'status' => 'all'])
        ->items();

    expect($summary['duplicates'])->toBe(['candidate_pairs' => 2, 'affected_subjects' => 4])
        ->and(collect($issues)->where('field', 'nuptk'))->toHaveCount(1)
        ->and(collect($issues)->where('field', 'name_birthdate'))->toHaveCount(1)
        ->and(collect($issues)->pluck('subject.id')->all())
        ->not->toContain($separatedSameNuptk->id)
        ->not->toContain($archivedSameNuptk->id)
        ->and(collect($issues)->firstWhere('field', 'name_birthdate')['related']['id'])
        ->toBe(max($staffA->id, $staffB->id));
});

test('employee summary returns null percentages and no issues when there are no employees', function () {
    $summary = (new EmployeeCompletenessInspector($this->asOf))->summary();
    $issues = (new EmployeeCompletenessInspector($this->asOf))->issues(['status' => 'all'])->items();

    expect($summary['population'])->toBe(['active' => 0, 'separated' => 0, 'archived_excluded' => 0])
        ->and($summary['records']['complete_percent'])->toBeNull()
        ->and(collect($summary['fields'])->every(fn ($field) => $field['completeness_percent'] === null))->toBeTrue()
        ->and($issues)->toBe([]);
});
