<?php

use App\Models\Activity;
use App\Models\DataQualityReview;
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
use App\Services\DataQuality\IssueKey;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

function dqReviewPayload(array $issue, ?int $schoolYearId = null, array $overrides = []): array
{
    $payload = [
        'status' => 'acknowledged',
        'reason_code' => null,
        'issue_type' => $issue['type'],
        'field' => $issue['field'],
        'subject_id' => $issue['subject']['id'],
        'subject_version' => $issue['subject']['version'],
        'related_subject_id' => $issue['related']['id'] ?? null,
        'related_version' => $issue['related']['version'] ?? null,
        'version' => $issue['review']['version'],
    ];

    if ($schoolYearId !== null) {
        $payload['school_year_id'] = $schoolYearId;
    }

    return array_merge($payload, $overrides);
}

function dqStudentIssues($test, int $yearId, array $params = [])
{
    return $test->getJson('/api/data-quality/students/issues?'.http_build_query(array_merge([
        'school_year_id' => $yearId,
        'status' => 'all',
    ], $params)));
}

beforeEach(function () {
    $this->seedAccessReferenceData();

    $this->level = SchoolLevel::create(['name' => 'DQ API Level']);
    $this->year = SchoolYear::create(['name' => '2030/2031']);
    $this->otherYear = SchoolYear::create(['name' => '2031/2032']);
    $this->rombel = SchoolRombel::create([
        'school_level_id' => $this->level->id,
        'school_year_id' => $this->year->id,
        'name' => 'DQ-API-10A',
    ]);
    $this->otherRombel = SchoolRombel::create([
        'school_level_id' => $this->level->id,
        'school_year_id' => $this->otherYear->id,
        'name' => 'DQ-API-11A',
    ]);

    $this->gender = Gender::firstOrCreate(['name' => 'Laki-Laki']);
    $this->religion = Religion::firstOrCreate(['name' => 'Islam']);
    $this->studentStatus = StudentStatus::firstOrCreate(['id' => 1], ['name' => 'Aktif']);

    $province = Province::firstOrCreate(['name' => 'DQ API Province']);
    $regency = Regency::firstOrCreate(['province_id' => $province->id, 'name' => 'DQ API Regency']);
    $this->district = District::firstOrCreate(['regency_id' => $regency->id, 'name' => 'DQ API District']);
    $this->maritalStatus = MaritalStatus::firstOrCreate(['name' => 'Belum Menikah']);
    $this->employeeStatus = EmployeeStatus::firstOrCreate(['name' => 'Pegawai Tetap'], ['abbreviation' => 'PT']);
    $this->employment = Employment::firstOrCreate(['name' => 'Tetap'], ['description' => 'Tetap']);
    $this->teacherType = EmployeeType::firstOrCreate(['name' => 'Pendidik']);
    $this->staffType = EmployeeType::firstOrCreate(['name' => 'Tenaga Kependidikan']);
    $this->subject = Subject::firstOrCreate(['name' => 'Matematika'], ['description' => 'Matematika']);

    $this->makeStudent = function (array $attrs = []): Student {
        static $seq = 0;
        $seq++;

        return Student::create(array_merge([
            'name' => 'DQ API Student '.$seq,
            'nik' => '02'.str_pad((string) $seq, 14, '0', STR_PAD_LEFT),
            'nis' => 'DQ-API-NIS-'.$seq,
            'nisn' => str_pad((string) $seq, 10, '0', STR_PAD_LEFT),
            'gender_id' => $this->gender->id,
            'religion_id' => $this->religion->id,
            'birthplace' => 'DQ City',
            'birthdate' => '2010-01-15',
        ], $attrs));
    };

    $this->placeStudent = function (Student $student, ?SchoolRombel $rombel = null): StudentRombel {
        return StudentRombel::create([
            'student_id' => $student->id,
            'student_nik' => $student->nik,
            'school_rombel' => ($rombel ?? $this->rombel)->id,
            'student_status_id' => $this->studentStatus->id,
        ]);
    };

    $this->makeEmployee = function (array $attrs = []): Employee {
        static $seq = 0;
        $seq++;

        return Employee::create(array_merge([
            'name' => 'DQ API Employee '.$seq,
            'nik' => '33'.str_pad((string) $seq, 14, '0', STR_PAD_LEFT),
            'nuptk' => 'DQ-API-NUPTK-'.$seq,
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

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->plainUser = $this->makeUserWithRole();
});

// ─────────────────────────────────────────────────────────────────────────────
// Akses
// ─────────────────────────────────────────────────────────────────────────────

test('data quality endpoints require authentication and the matching abilities', function () {
    $this->getJson('/api/data-quality/students/summary?school_year_id='.$this->year->id)->assertStatus(401);
    $this->getJson('/api/data-quality/employees/summary')->assertStatus(401);

    Sanctum::actingAs($this->plainUser);
    $this->getJson('/api/data-quality/students/summary?school_year_id='.$this->year->id)->assertStatus(403);
    $this->getJson('/api/data-quality/employees/summary')->assertStatus(403);
    $this->putJson('/api/data-quality/students/reviews/'.str_repeat('a', 64))->assertStatus(403);
    $this->putJson('/api/data-quality/employees/reviews/'.str_repeat('a', 64))->assertStatus(403);

    Sanctum::actingAs($this->principal);
    $this->getJson('/api/data-quality/students/summary?school_year_id='.$this->year->id)->assertOk();
    $this->getJson('/api/data-quality/employees/summary')->assertOk();
    $this->putJson('/api/data-quality/students/reviews/'.str_repeat('a', 64))->assertStatus(403);
    $this->putJson('/api/data-quality/employees/reviews/'.str_repeat('a', 64))->assertStatus(403);

    Sanctum::actingAs($this->operator);
    $this->getJson('/api/data-quality/students/summary?school_year_id='.$this->year->id)->assertOk();
    $this->getJson('/api/data-quality/employees/summary')->assertOk();
});

// ─────────────────────────────────────────────────────────────────────────────
// Ringkasan dan daftar siswa
// ─────────────────────────────────────────────────────────────────────────────

test('student data quality summary and issues expose denominators without personal identifiers', function () {
    $reviewable = ($this->makeStudent)(['name' => 'A Reviewable Student', 'nik' => null]);
    ($this->placeStudent)($reviewable);
    ($this->placeStudent)($reviewable, $this->otherRombel);

    $complete = ($this->makeStudent)(['name' => 'B Complete Student']);
    ($this->placeStudent)($complete);

    $unplaced = ($this->makeStudent)(['name' => 'C Unplaced Student', 'nik' => null]);

    Sanctum::actingAs($this->operator);

    $summary = $this->getJson('/api/data-quality/students/summary?school_year_id='.$this->year->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.denominator_basis', 'placed_students')
        ->assertJsonPath('data.population.placed', 2)
        ->assertJsonPath('data.population.unplaced', 1)
        ->assertJsonPath('data.population.archived_excluded', 0)
        ->json('data');

    expect($summary['fields'])->not->toBeEmpty();

    $issuesResponse = dqStudentIssues($this, $this->year->id)->assertOk()->json();
    $missing = collect($issuesResponse['data'])->firstWhere('type', 'missing');
    $blocked = collect($issuesResponse['data'])->firstWhere('type', 'blocked');

    expect($issuesResponse['meta']['total'])->toBe(2)
        ->and($missing['subject']['id'])->toBe($reviewable->id)
        ->and($missing['subject']['placement'])->toBe('placed')
        ->and($missing['review'])->toBe([
            'status' => 'open',
            'reason_code' => null,
            'version' => 0,
            'reviewed_at' => null,
        ])
        ->and($blocked['subject']['id'])->toBe($unplaced->id)
        ->and($blocked['subject']['placement'])->toBe('unplaced')
        ->and(array_keys($missing['subject']))->toBe([
            'type', 'id', 'version', 'name', 'nis', 'nisn', 'placement', 'gender', 'rombel',
        ]);

    $encoded = json_encode($issuesResponse['data']);
    expect($encoded)->not->toContain($complete->nik)
        ->and($encoded)->not->toContain('2010-01-15')
        ->and($encoded)->not->toContain('DQ City');

    $otherYear = dqStudentIssues($this, $this->otherYear->id)->assertOk()->json();
    expect(collect($otherYear['data'])->pluck('issue_key'))
        ->not->toContain($missing['issue_key']);
});

test('student issue filters, pagination, and validation are enforced', function () {
    $missing = ($this->makeStudent)(['name' => 'Alpha Missing', 'nik' => null]);
    ($this->placeStudent)($missing);

    $blocked = ($this->makeStudent)(['name' => 'Beta Blocked', 'nik' => null]);

    Sanctum::actingAs($this->operator);

    expect(dqStudentIssues($this, $this->year->id, ['type' => 'missing'])->json('meta.total'))->toBe(1)
        ->and(dqStudentIssues($this, $this->year->id, ['type' => 'blocked'])->json('meta.total'))->toBe(1)
        ->and(dqStudentIssues($this, $this->year->id, ['field' => 'nik'])->json('meta.total'))->toBe(2)
        ->and(dqStudentIssues($this, $this->year->id, ['search' => 'Beta'])->json('meta.total'))->toBe(1)
        ->and(dqStudentIssues($this, $this->year->id, ['per_page' => 10])->json('meta.per_page'))->toBe(10);

    dqStudentIssues($this, $this->year->id, ['per_page' => 7])->assertStatus(422)->assertJsonValidationErrors('per_page');
    dqStudentIssues($this, $this->year->id, ['field' => 'name'])->assertStatus(422)->assertJsonValidationErrors('field');
    dqStudentIssues($this, $this->year->id, ['type' => 'unknown'])->assertStatus(422)->assertJsonValidationErrors('type');
    dqStudentIssues($this, $this->year->id, ['status' => 'unknown'])->assertStatus(422)->assertJsonValidationErrors('status');
    $this->getJson('/api/data-quality/students/issues')->assertStatus(422)->assertJsonValidationErrors('school_year_id');
    $this->getJson('/api/data-quality/students/summary')->assertStatus(422)->assertJsonValidationErrors('school_year_id');

    $studentCount = Student::count();
    $reviewCount = DataQualityReview::count();
    $activityCount = Activity::count();
    $issues = dqStudentIssues($this, $this->year->id)->assertOk()->json();
    expect(Student::count())->toBe($studentCount)
        ->and(DataQualityReview::count())->toBe($reviewCount)
        ->and(Activity::count())->toBe($activityCount)
        ->and(collect($issues['data'])->pluck('subject.id')->all())->toBe([$missing->id, $blocked->id]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Review siswa
// ─────────────────────────────────────────────────────────────────────────────

test('student review lifecycle is versioned, year-scoped, audited, and does not change domain data', function () {
    $student = ($this->makeStudent)(['name' => 'Review Lifecycle Student', 'nik' => null]);
    ($this->placeStudent)($student);
    ($this->placeStudent)($student, $this->otherRombel);

    Sanctum::actingAs($this->operator);

    $issue = collect(dqStudentIssues($this, $this->year->id)->json('data'))->first();
    $originalVersion = (int) $student->fresh()->version;

    $this->putJson(
        '/api/data-quality/students/reviews/'.$issue['issue_key'],
        dqReviewPayload($issue, $this->year->id, ['status' => 'acknowledged'])
    )
        ->assertOk()
        ->assertJsonPath('status', 'acknowledged')
        ->assertJsonPath('version', 1)
        ->assertJsonPath('reviewed_by', $this->operator->id)
        ->assertJsonPath('reason_code', null);

    $this->putJson(
        '/api/data-quality/students/reviews/'.$issue['issue_key'],
        dqReviewPayload($issue, $this->year->id, ['status' => 'ignored', 'reason_code' => null, 'version' => 1])
    )->assertStatus(422)->assertJsonValidationErrors('reason_code');

    $this->putJson(
        '/api/data-quality/students/reviews/'.$issue['issue_key'],
        dqReviewPayload($issue, $this->year->id, ['status' => 'ignored', 'reason_code' => 'bogus', 'version' => 1])
    )->assertStatus(422)->assertJsonValidationErrors('reason_code');

    $this->putJson(
        '/api/data-quality/students/reviews/'.$issue['issue_key'],
        dqReviewPayload($issue, $this->year->id, ['status' => 'acknowledged', 'reason_code' => 'pending_document', 'version' => 1])
    )->assertStatus(422)->assertJsonValidationErrors('reason_code');

    $this->putJson(
        '/api/data-quality/students/reviews/'.$issue['issue_key'],
        dqReviewPayload($issue, $this->year->id, ['status' => 'ignored', 'reason_code' => 'pending_document', 'version' => 1])
    )
        ->assertOk()
        ->assertJsonPath('status', 'ignored')
        ->assertJsonPath('reason_code', 'pending_document')
        ->assertJsonPath('version', 2);

    $this->putJson(
        '/api/data-quality/students/reviews/'.$issue['issue_key'],
        dqReviewPayload($issue, $this->year->id, ['status' => 'open', 'version' => 1])
    )->assertStatus(409)->assertJsonPath('current_version', 2);

    expect(collect(dqStudentIssues($this, $this->year->id, ['status' => 'open'])->json('data'))->pluck('issue_key'))
        ->not->toContain($issue['issue_key'])
        ->and(collect(dqStudentIssues($this, $this->year->id, ['status' => 'ignored'])->json('data'))->pluck('issue_key'))
        ->toContain($issue['issue_key'])
        ->and(collect(dqStudentIssues($this, $this->otherYear->id)->json('data'))->pluck('type'))
        ->toContain('missing');

    $this->putJson(
        '/api/data-quality/students/reviews/'.$issue['issue_key'],
        dqReviewPayload($issue, $this->year->id, ['status' => 'open', 'version' => 2])
    )
        ->assertOk()
        ->assertJsonPath('status', 'open')
        ->assertJsonPath('reason_code', null)
        ->assertJsonPath('version', 3);

    $review = DataQualityReview::query()->where('issue_key', $issue['issue_key'])->firstOrFail();
    expect((int) $review->school_year_id)->toBe($this->year->id)
        ->and((int) $review->reviewed_by)->toBe($this->operator->id)
        ->and($review->reviewed_at)->not->toBeNull()
        ->and((int) $student->fresh()->version)->toBe($originalVersion);

    $summary = $this->getJson('/api/data-quality/students/summary?school_year_id='.$this->year->id)
        ->assertOk()
        ->json('data');
    expect($summary['reviews'])->toBe(['open' => 1, 'acknowledged' => 0, 'ignored' => 0, 'unresolved' => 1]);

    $audit = Activity::query()->where('table_name', 'data_quality_reviews')->get();
    expect($audit)->not->toBeEmpty();
    $auditJson = $audit->map(fn (Activity $activity) => json_encode([
        $activity->before,
        $activity->after,
        $activity->new,
    ]))->implode(' ');
    expect($auditJson)->not->toContain($student->name)
        ->and($auditJson)->not->toContain($student->nis)
        ->and($audit->every(fn (Activity $activity) => (int) $activity->user_id === $this->operator->id))
        ->toBeTrue();
});

test('student review rejects stale records, mismatched issue keys, and unknown subjects', function () {
    $student = ($this->makeStudent)(['name' => 'Stale Review Student', 'nik' => null]);
    ($this->placeStudent)($student);

    Sanctum::actingAs($this->operator);

    $issue = collect(dqStudentIssues($this, $this->year->id)->json('data'))->first();
    $payload = dqReviewPayload($issue, $this->year->id, ['version' => 0]);

    $this->putJson('/api/data-quality/students/reviews/'.str_repeat('b', 64), $payload)
        ->assertStatus(409);

    $student->increment('version');
    $bumpedVersion = (int) $student->fresh()->version;

    $this->putJson('/api/data-quality/students/reviews/'.$issue['issue_key'], $payload)
        ->assertStatus(409)
        ->assertJsonPath('current_version', $bumpedVersion);

    $unknownKey = IssueKey::make('students', 'missing', 'nik', $this->year->id, 'student', 999999, 1);
    $unknownPayload = [
        'status' => 'acknowledged',
        'reason_code' => null,
        'issue_type' => 'missing',
        'field' => 'nik',
        'school_year_id' => $this->year->id,
        'subject_id' => 999999,
        'subject_version' => 1,
        'related_subject_id' => null,
        'related_version' => null,
        'version' => 0,
    ];

    $this->putJson('/api/data-quality/students/reviews/'.$unknownKey, $unknownPayload)
        ->assertStatus(404);
});

test('duplicate issues accept verified distinct while non-duplicates reject it', function () {
    $a = ($this->makeStudent)(['name' => 'Duplicate Pair', 'birthdate' => '2012-03-04']);
    $b = ($this->makeStudent)(['name' => ' duplicate pair ', 'birthdate' => '2012-03-04']);
    $missing = ($this->makeStudent)(['name' => 'Missing NIK For Reason', 'nik' => null]);
    ($this->placeStudent)($missing);

    Sanctum::actingAs($this->operator);

    $duplicate = collect(dqStudentIssues($this, $this->year->id, ['type' => 'duplicate'])->json('data'))->first();
    $missingIssue = collect(dqStudentIssues($this, $this->year->id, ['type' => 'missing'])->json('data'))->first();

    $this->putJson(
        '/api/data-quality/students/reviews/'.$missingIssue['issue_key'],
        dqReviewPayload($missingIssue, $this->year->id, [
            'status' => 'ignored',
            'reason_code' => 'verified_distinct',
            'version' => 0,
        ])
    )->assertStatus(422)->assertJsonValidationErrors('reason_code');

    $this->putJson(
        '/api/data-quality/students/reviews/'.$duplicate['issue_key'],
        dqReviewPayload($duplicate, $this->year->id, [
            'status' => 'ignored',
            'reason_code' => 'verified_distinct',
            'version' => 0,
        ])
    )
        ->assertOk()
        ->assertJsonPath('status', 'ignored')
        ->assertJsonPath('reason_code', 'verified_distinct')
        ->assertJsonPath('version', 1);

    expect($duplicate['subject']['id'])->toBe(min($a->id, $b->id))
        ->and($duplicate['related']['id'])->toBe(max($a->id, $b->id));
});

test('student review accepts an issue outside the first default page of the issue list', function () {
    for ($i = 1; $i <= 21; $i++) {
        ($this->placeStudent)(($this->makeStudent)(['name' => 'A Missing NIK '.$i, 'nik' => null]));
    }

    $target = ($this->makeStudent)(['name' => 'Zulu Missing NIK', 'nik' => null]);
    ($this->placeStudent)($target);

    Sanctum::actingAs($this->operator);

    $firstPage = dqStudentIssues($this, $this->year->id)->assertOk()->json();
    expect($firstPage['meta']['total'])->toBe(22)
        ->and(collect($firstPage['data'])->pluck('subject.id'))->not->toContain($target->id);

    $issue = collect(dqStudentIssues($this, $this->year->id, ['per_page' => 100])->json('data'))
        ->firstWhere('subject.id', $target->id);
    expect($issue)->not->toBeNull();

    $this->putJson(
        '/api/data-quality/students/reviews/'.$issue['issue_key'],
        dqReviewPayload($issue, $this->year->id, ['status' => 'acknowledged'])
    )
        ->assertOk()
        ->assertJsonPath('status', 'acknowledged')
        ->assertJsonPath('version', 1);
});

// ─────────────────────────────────────────────────────────────────────────────
// Pegawai
// ─────────────────────────────────────────────────────────────────────────────

test('employee review accepts an issue outside the first default page of the issue list', function () {
    for ($i = 1; $i <= 21; $i++) {
        ($this->makeEmployee)(['name' => 'A Missing NUPTK '.$i, 'nuptk' => null]);
    }

    $target = ($this->makeEmployee)(['name' => 'Zulu Missing NUPTK', 'nuptk' => null]);

    Sanctum::actingAs($this->operator);

    $firstPage = $this->getJson('/api/data-quality/employees/issues?status=all')->assertOk()->json();
    expect($firstPage['meta']['total'])->toBe(22)
        ->and(collect($firstPage['data'])->pluck('subject.id'))->not->toContain($target->id);

    $issue = collect($this->getJson('/api/data-quality/employees/issues?status=all&per_page=100')->json('data'))
        ->firstWhere('subject.id', $target->id);
    expect($issue)->not->toBeNull();

    $this->putJson(
        '/api/data-quality/employees/reviews/'.$issue['issue_key'],
        dqReviewPayload($issue, null, ['status' => 'acknowledged'])
    )
        ->assertOk()
        ->assertJsonPath('status', 'acknowledged')
        ->assertJsonPath('version', 1);
});

test('employee data quality summary, privacy, and review validation are enforced', function () {
    $teacher = ($this->makeEmployee)(['name' => 'Gap Teacher', 'nuptk' => null]);
    $staff = ($this->makeEmployee)([
        'name' => 'Staff With Subject',
        'employee_type_id' => $this->staffType->id,
        'nuptk' => null,
    ]);

    Sanctum::actingAs($this->operator);

    $summary = $this->getJson('/api/data-quality/employees/summary')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.denominator_basis', 'active_employees')
        ->assertJsonPath('data.population.active', 2)
        ->json('data');

    expect($summary['population']['separated'])->toBe(0);

    $issuesResponse = $this->getJson('/api/data-quality/employees/issues?status=all')
        ->assertOk()
        ->json();
    $nuptkIssue = collect($issuesResponse['data'])->firstWhere('field', 'nuptk');
    $subjectIssue = collect($issuesResponse['data'])->firstWhere('type', 'invalid');
    $json = json_encode($issuesResponse['data']);

    expect($issuesResponse['meta']['total'])->toBe(2)
        ->and($nuptkIssue['subject']['id'])->toBe($teacher->id)
        ->and($nuptkIssue['subject']['employee_type'])->toBe('Teacher')
        ->and($subjectIssue['subject']['id'])->toBe($staff->id)
        ->and($subjectIssue['subject']['employee_type'])->toBe('Non-teaching Employee')
        ->and(array_keys($nuptkIssue['subject']))->toBe([
            'type', 'id', 'version', 'name', 'nuptk', 'employee_type', 'employee_status', 'employment', 'subject',
        ])
        ->and($json)->not->toContain($teacher->nik)
        ->and($json)->not->toContain('DQ API District');

    $this->putJson(
        '/api/data-quality/employees/reviews/'.$nuptkIssue['issue_key'],
        dqReviewPayload($nuptkIssue, null, ['version' => 0, 'school_year_id' => $this->year->id])
    )->assertStatus(422)->assertJsonValidationErrors('school_year_id');

    $this->putJson(
        '/api/data-quality/employees/reviews/'.$nuptkIssue['issue_key'],
        dqReviewPayload($nuptkIssue, null, ['status' => 'ignored', 'reason_code' => 'pending_document', 'version' => 0])
    )
        ->assertOk()
        ->assertJsonPath('status', 'ignored')
        ->assertJsonPath('version', 1);

    expect(DataQualityReview::query()->where('issue_key', $nuptkIssue['issue_key'])->firstOrFail()->school_year_id)
        ->toBeNull();
});
