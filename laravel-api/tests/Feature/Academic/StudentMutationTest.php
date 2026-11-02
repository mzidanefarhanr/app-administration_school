<?php

use App\Models\AcademicSetting;
use App\Models\Activity;
use App\Models\District;
use App\Models\EducationLevel;
use App\Models\EducationSchool;
use App\Models\Province;
use App\Models\Regency;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEntry;
use App\Models\StudentLockedCorrection;
use App\Models\StudentMutation;
use App\Models\StudentProgressionBatch;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 11, 15, 9, 0, 0, 'Asia/Jakarta'));

    $this->seedAccessReferenceData();

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->admin = $this->makeUserWithRole('admin-sistem');

    StudentStatus::insertOrIgnore([
        ['id' => 1, 'name' => 'Aktif', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Mutasi Keluar', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 6, 'name' => 'Lulus', 'created_at' => now(), 'updated_at' => now()],
    ]);

    StudentEntry::insertOrIgnore([
        ['id' => 1, 'name' => 'Siswa Baru', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Mutasi Masuk', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $province = Province::create(['name' => 'UAT P06C Province']);
    $regency = Regency::create(['province_id' => $province->id, 'name' => 'UAT P06C Regency']);
    $district = District::create(['regency_id' => $regency->id, 'name' => 'UAT P06C District']);
    $educationLevel = EducationLevel::create(['name' => 'UAT P06C Level']);

    $this->originSchool = EducationSchool::create([
        'version' => 1,
        'name' => 'UAT P06C Origin School',
        'npsn' => '81000001',
        'education_level_id' => $educationLevel->id,
        'status_education' => 'Negeri',
        'district_id' => $district->id,
    ]);

    $this->destinationSchool = EducationSchool::create([
        'version' => 1,
        'name' => 'UAT P06C Destination School',
        'npsn' => '81000002',
        'education_level_id' => $educationLevel->id,
        'status_education' => 'Swasta',
        'district_id' => $district->id,
    ]);

    $this->activeYear = SchoolYear::create([
        'name' => '2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $this->nextYear = SchoolYear::create([
        'name' => '2027/2028',
        'start_date' => '2027-07-01',
        'end_date' => '2028-06-30',
    ]);
    $this->lockedYear = SchoolYear::create([
        'name' => '2025/2026',
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
        'locked_at' => now(),
        'locked_by' => $this->admin->id,
        'lock_reason' => 'UAT locked period.',
    ]);
    $this->noDateYear = SchoolYear::create([
        'name' => '2028/2029',
        'start_date' => null,
        'end_date' => null,
    ]);

    AcademicSetting::find(AcademicSetting::SINGLETON_ID)
        ->update(['active_school_year_id' => $this->activeYear->id]);

    $this->levelTen = SchoolLevel::create(['name' => 'Grade 10']);
    $this->levelEleven = SchoolLevel::create(['name' => 'Grade 11']);

    $this->activeClass = SchoolRombel::create([
        'version' => 1,
        'school_year_id' => $this->activeYear->id,
        'school_level_id' => $this->levelTen->id,
        'name' => '10-A',
    ]);
    $this->activeClassB = SchoolRombel::create([
        'version' => 1,
        'school_year_id' => $this->activeYear->id,
        'school_level_id' => $this->levelTen->id,
        'name' => '10-B',
    ]);
    $this->nextClass = SchoolRombel::create([
        'version' => 1,
        'school_year_id' => $this->nextYear->id,
        'school_level_id' => $this->levelEleven->id,
        'name' => '11-A',
    ]);
    $this->lockedClass = SchoolRombel::create([
        'version' => 1,
        'school_year_id' => $this->lockedYear->id,
        'school_level_id' => $this->levelTen->id,
        'name' => '10-Locked-A',
    ]);
    $this->lockedClassB = SchoolRombel::create([
        'version' => 1,
        'school_year_id' => $this->lockedYear->id,
        'school_level_id' => $this->levelTen->id,
        'name' => '10-Locked-B',
    ]);
    $this->noDateClass = SchoolRombel::create([
        'version' => 1,
        'school_year_id' => $this->noDateYear->id,
        'school_level_id' => $this->levelTen->id,
        'name' => '10-NoDate',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
    Activity::$testCreatingHook = null;
});

function p06cStudent(string $name, array $overrides = []): Student
{
    static $sequence = 0;
    $sequence++;

    return Student::create(array_merge([
        'name' => $name,
        'nik' => '8600000'.str_pad((string) $sequence, 9, '0', STR_PAD_LEFT),
        'version' => 1,
    ], $overrides));
}

function p06cMembership(Student $student, SchoolRombel $class, array $overrides = []): StudentRombel
{
    return StudentRombel::create(array_merge([
        'version' => 1,
        'student_id' => $student->id,
        'student_nik' => $student->nik,
        'school_rombel' => $class->id,
        'student_status_id' => 1,
        'student_entry_id' => 1,
        'latest_student_entry_date' => '2026-07-10',
    ], $overrides));
}

function p06cInPayload(array $overrides = []): array
{
    return array_merge([
        'school_year_id' => test()->activeYear->id,
        'school_rombel_id' => test()->activeClass->id,
        'effective_date' => '2026-08-01',
        'school_npsn' => '81000001',
        'reason' => 'Transferred in from a partner school.',
        'expected_student_version' => 1,
    ], $overrides);
}

function p06cOutPayload(StudentRombel $membership, Student $student, array $overrides = []): array
{
    return array_merge([
        'student_rombel_id' => $membership->id,
        'effective_date' => '2026-10-01',
        'school_npsn' => '81000002',
        'reason' => 'Transferred out to another school.',
        'expected_student_version' => (int) $student->version,
        'expected_membership_version' => (int) $membership->version,
    ], $overrides);
}

function p06cMutationRow(int $studentId, int $yearId, int $classId, string $type, string $date, string $npsn = '81000001', string $status = 'active', ?int $membershipId = null, int $version = 1): int
{
    return DB::table('student_mutations')->insertGetId([
        'version' => $version,
        'type' => $type,
        'school_year_id' => $yearId,
        'student_id' => $studentId,
        'student_rombel_id' => $membershipId,
        'school_rombel_id' => $classId,
        'effective_date' => $date,
        'school_npsn' => $npsn,
        'reason' => 'Synthetic mutation log.',
        'status' => $status,
        'created_by' => test()->operator->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function p06cCorrectionRow(int $studentId, int $yearId, array $overrides = []): int
{
    return DB::table('student_locked_corrections')->insertGetId(array_merge([
        'school_year_id' => $yearId,
        'student_id' => $studentId,
        'action' => 'membership_exit',
        'target_type' => 'membership',
        'target_id' => 1,
        'mutation_id' => null,
        'student_rombel_id' => null,
        'before' => json_encode([]),
        'after' => json_encode([]),
        'reason' => 'Synthetic locked correction record.',
        'approval_reference' => null,
        'created_by' => test()->operator->id,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

test('guests and view-only accounts cannot read or write mutation endpoints', function () {
    $student = p06cStudent('P06C Permission Student');

    $this->getJson('/api/academic/mutations/options')->assertStatus(401);
    $this->getJson('/api/academic/mutations?school_year_id='.$this->activeYear->id)->assertStatus(401);
    $this->getJson('/api/students/'.$student->id.'/mutations')->assertStatus(401);
    $this->postJson('/api/students/'.$student->id.'/mutations/in', [])->assertStatus(401);
    $this->postJson('/api/students/'.$student->id.'/mutations/out', [])->assertStatus(401);
    $this->postJson('/api/students/'.$student->id.'/mutations/1/cancel', [])->assertStatus(401);
    $this->getJson('/api/students/'.$student->id.'/locked-corrections')->assertStatus(401);
    $this->postJson('/api/students/'.$student->id.'/locked-corrections', [])->assertStatus(401);

    Sanctum::actingAs($this->principal);

    $this->getJson('/api/academic/mutations/options')->assertStatus(200);
    $this->getJson('/api/academic/mutations/schools?search=UAT')->assertStatus(200);
    $this->getJson('/api/academic/mutations/recap?school_year_id='.$this->activeYear->id)->assertStatus(200);
    $this->getJson('/api/academic/mutations?school_year_id='.$this->activeYear->id)->assertStatus(200);
    $this->getJson('/api/students/'.$student->id.'/mutations')->assertStatus(200);
    $this->getJson('/api/students/'.$student->id.'/locked-corrections')->assertStatus(200);

    $this->postJson('/api/students/'.$student->id.'/mutations/in', p06cInPayload())->assertStatus(403);
    $this->postJson('/api/students/'.$student->id.'/locked-corrections', [
        'action' => 'membership_exit',
        'school_year_id' => $this->lockedYear->id,
        'reason' => 'View-only accounts must not correct.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(403);

    expect(StudentMutation::count())->toBe(0)
        ->and(StudentLockedCorrection::count())->toBe(0);
});

test('mutation-in creates a new active membership, mutation log, and audit trail', function () {
    Sanctum::actingAs($this->operator);

    $student = p06cStudent('P06C In Student');

    $response = $this->postJson("/api/students/{$student->id}/mutations/in", p06cInPayload([
        'reason' => 'Transferred in from a partner school.',
    ]));

    $response->assertStatus(200)
        ->assertJsonPath('data.mutation.type', 'in')
        ->assertJsonPath('data.mutation.status', 'active')
        ->assertJsonPath('data.mutation.school_npsn', '81000001')
        ->assertJsonPath('data.mutation.student.version', 2)
        ->assertJsonPath('data.mutation.membership.version', 1)
        ->assertJsonPath('data.mutation.membership.student_status_id', 1)
        ->assertJsonPath('data.membership.student_status_id', 1)
        ->assertJsonPath('data.membership.student_entry_id', 2)
        ->assertJsonPath('data.membership.latest_student_entry_date', '2026-08-01')
        ->assertJsonPath('data.membership.mutation_education_school_id', '81000001')
        ->assertJsonPath('data.membership.version', 1)
        ->assertJsonPath('data.student_version', 2);

    $membership = StudentRombel::where('student_id', $student->id)->firstOrFail();
    $mutation = StudentMutation::where('student_id', $student->id)->firstOrFail();

    expect((int) $membership->student_status_id)->toBe(1)
        ->and((int) $membership->student_entry_id)->toBe(2)
        ->and((int) $membership->version)->toBe(1)
        ->and(substr((string) $membership->latest_student_entry_date, 0, 10))->toBe('2026-08-01')
        ->and((string) $membership->mutation_education_school_id)->toBe('81000001')
        ->and((int) $student->fresh()->version)->toBe(2)
        ->and($mutation->type)->toBe('in')
        ->and($mutation->status)->toBe('active')
        ->and($mutation->reason)->toBe('Transferred in from a partner school.')
        ->and((int) $mutation->created_by)->toBe((int) $this->operator->id)
        ->and((int) $mutation->student_rombel_id)->toBe((int) $membership->id);

    expect(Activity::where('table_name', 'student_rombels')->where('record_id', $membership->id)->where('information', 'Created')->exists())->toBeTrue()
        ->and(Activity::where('table_name', 'student_mutations')->where('record_id', $mutation->id)->where('information', 'Created')->exists())->toBeTrue();

    $this->getJson('/api/academic/mutations?school_year_id='.$this->activeYear->id)
        ->assertStatus(200)
        ->assertJsonPath('data.items.0.membership.id', $membership->id)
        ->assertJsonPath('data.items.0.membership.version', 1)
        ->assertJsonPath('data.items.0.student.version', 2);

    $this->getJson('/api/students/'.$student->id.'/mutations')
        ->assertStatus(200)
        ->assertJsonPath('data.mutations.0.membership.id', $membership->id)
        ->assertJsonPath('data.mutations.0.membership.version', 1)
        ->assertJsonPath('data.mutations.0.student.version', 2);
});

test('mutation-in rejects archived students, active memberships, invalid scope, and stale versions', function () {
    Sanctum::actingAs($this->operator);

    $archived = p06cStudent('P06C Archived Student');
    $archived->delete();

    $this->postJson("/api/students/{$archived->id}/mutations/in", p06cInPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('student_id');

    $alreadyActive = p06cStudent('P06C Already Active');
    p06cMembership($alreadyActive, $this->activeClass);

    $this->postJson("/api/students/{$alreadyActive->id}/mutations/in", p06cInPayload())
        ->assertStatus(409)
        ->assertJsonPath('current_versions.membership_version', 1);

    $fresh = p06cStudent('P06C Scope Checks');

    $this->postJson("/api/students/{$fresh->id}/mutations/in", p06cInPayload([
        'school_rombel_id' => $this->nextClass->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('school_rombel_id');

    $deletedClass = SchoolRombel::create([
        'version' => 1,
        'school_year_id' => $this->activeYear->id,
        'school_level_id' => $this->levelTen->id,
        'name' => '10-Deleted',
    ]);
    $deletedClass->delete();

    $this->postJson("/api/students/{$fresh->id}/mutations/in", p06cInPayload([
        'school_rombel_id' => $deletedClass->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('school_rombel_id');

    $this->postJson("/api/students/{$fresh->id}/mutations/in", p06cInPayload([
        'school_year_id' => $this->lockedYear->id,
        'school_rombel_id' => $this->lockedClass->id,
        'effective_date' => '2025-08-01',
    ]))->assertStatus(422)->assertJsonValidationErrors('school_year_id');

    $this->postJson("/api/students/{$fresh->id}/mutations/in", p06cInPayload([
        'school_year_id' => $this->noDateYear->id,
        'school_rombel_id' => $this->noDateClass->id,
        'effective_date' => '2028-08-01',
    ]))->assertStatus(422)->assertJsonValidationErrors('school_year_id');

    $this->postJson("/api/students/{$fresh->id}/mutations/in", p06cInPayload([
        'effective_date' => '2027-01-15',
    ]))->assertStatus(422)->assertJsonValidationErrors('effective_date');

    $this->postJson("/api/students/{$fresh->id}/mutations/in", p06cInPayload([
        'effective_date' => '2026-06-30',
    ]))->assertStatus(422)->assertJsonValidationErrors('effective_date');

    $this->postJson("/api/students/{$fresh->id}/mutations/in", p06cInPayload([
        'school_npsn' => '99999999',
    ]))->assertStatus(422)->assertJsonValidationErrors('school_npsn');

    $this->postJson("/api/students/{$fresh->id}/mutations/in", p06cInPayload([
        'expected_student_version' => 99,
    ]))->assertStatus(409)->assertJsonPath('current_versions.student_version', 1);

    expect(StudentMutation::count())->toBe(0)
        ->and(StudentRombel::where('student_id', $fresh->id)->count())->toBe(0);
});

test('mutation-in allows re-entry only after the previous membership is closed', function () {
    Sanctum::actingAs($this->operator);

    $allowed = p06cStudent('P06C Reentry Allowed');
    p06cMembership($allowed, $this->activeClass, [
        'student_status_id' => 2,
        'latest_student_entry_date' => '2026-07-10',
        'latest_student_exit_date' => '2026-09-30',
        'latest_student_exit_school_id' => '81000002',
    ]);

    $this->postJson("/api/students/{$allowed->id}/mutations/in", p06cInPayload([
        'school_rombel_id' => $this->activeClassB->id,
        'effective_date' => '2026-10-10',
    ]))->assertStatus(200)->assertJsonPath('data.mutation.type', 'in');

    expect(StudentRombel::where('student_id', $allowed->id)->count())->toBe(2);

    $lateExit = p06cStudent('P06C Reentry Late Exit');
    p06cMembership($lateExit, $this->activeClass, [
        'student_status_id' => 2,
        'latest_student_entry_date' => '2026-07-10',
        'latest_student_exit_date' => '2026-12-01',
        'latest_student_exit_school_id' => '81000002',
    ]);

    $this->postJson("/api/students/{$lateExit->id}/mutations/in", p06cInPayload([
        'school_rombel_id' => $this->activeClassB->id,
        'effective_date' => '2026-11-05',
    ]))->assertStatus(409);

    $stillActive = p06cStudent('P06C Reentry Active');
    p06cMembership($stillActive, $this->activeClass);

    $this->postJson("/api/students/{$stillActive->id}/mutations/in", p06cInPayload([
        'school_rombel_id' => $this->activeClassB->id,
        'effective_date' => '2026-10-10',
    ]))->assertStatus(409);

    expect(StudentMutation::count())->toBe(1)
        ->and(StudentRombel::where('student_id', $lateExit->id)->count())->toBe(1)
        ->and(StudentRombel::where('student_id', $stillActive->id)->count())->toBe(1);
});

test('mutation-out closes only the selected membership and keeps entry data intact', function () {
    Sanctum::actingAs($this->operator);

    $student = p06cStudent('P06C Out Student');
    $membership = p06cMembership($student, $this->activeClass, [
        'student_entry_id' => 2,
        'latest_student_entry_date' => '2026-07-10',
        'mutation_education_school_id' => '81000001',
        'latest_information' => 'Keep this information unchanged.',
    ]);

    $decoy = p06cMembership($student, $this->nextClass, [
        'student_status_id' => 2,
        'latest_student_entry_date' => '2027-07-10',
        'latest_student_exit_date' => '2027-10-01',
        'latest_student_exit_school_id' => '81000002',
    ]);

    $response = $this->postJson("/api/students/{$student->id}/mutations/out", p06cOutPayload($membership, $student));

    $response->assertStatus(200)
        ->assertJsonPath('data.mutation.type', 'out')
        ->assertJsonPath('data.mutation.school_npsn', '81000002')
        ->assertJsonPath('data.membership.student_status_id', 2)
        ->assertJsonPath('data.membership.latest_student_exit_date', '2026-10-01')
        ->assertJsonPath('data.membership.latest_student_exit_school_id', '81000002')
        ->assertJsonPath('data.student_version', 2);

    $membership->refresh();
    $decoy->refresh();
    $mutation = StudentMutation::where('student_id', $student->id)->firstOrFail();

    expect((int) $membership->student_status_id)->toBe(2)
        ->and((int) $membership->version)->toBe(2)
        ->and(substr((string) $membership->latest_student_entry_date, 0, 10))->toBe('2026-07-10')
        ->and((string) $membership->mutation_education_school_id)->toBe('81000001')
        ->and((int) $membership->student_entry_id)->toBe(2)
        ->and($membership->latest_information)->toBe('Keep this information unchanged.')
        ->and((int) $student->fresh()->version)->toBe(2)
        ->and($mutation->type)->toBe('out')
        ->and((int) $mutation->student_rombel_id)->toBe((int) $membership->id)
        ->and((int) $decoy->version)->toBe(1)
        ->and((int) $decoy->student_status_id)->toBe(2)
        ->and(substr((string) $decoy->latest_student_exit_date, 0, 10))->toBe('2027-10-01');

    expect(Activity::where('table_name', 'student_rombels')->where('record_id', $membership->id)->where('information', 'Updated')->exists())->toBeTrue()
        ->and(Activity::where('table_name', 'student_mutations')->where('record_id', $mutation->id)->where('information', 'Created')->exists())->toBeTrue();
});

test('mutation-out rejects inactive memberships, wrong ownership, invalid dates, and stale versions', function () {
    Sanctum::actingAs($this->operator);

    $closed = p06cStudent('P06C Out Closed');
    $closedMembership = p06cMembership($closed, $this->activeClass, [
        'student_status_id' => 2,
        'latest_student_exit_date' => '2026-09-01',
        'latest_student_exit_school_id' => '81000002',
    ]);

    $this->postJson("/api/students/{$closed->id}/mutations/out", p06cOutPayload($closedMembership, $closed))
        ->assertStatus(409);

    $archived = p06cStudent('P06C Out Archived Membership');
    $archivedMembership = p06cMembership($archived, $this->activeClass, [
        'archived_at' => now(),
        'archive_reason' => 'Cancelled mutation-in',
    ]);

    $this->postJson("/api/students/{$archived->id}/mutations/out", p06cOutPayload($archivedMembership, $archived))
        ->assertStatus(409);

    $owner = p06cStudent('P06C Out Owner');
    $ownerMembership = p06cMembership($owner, $this->activeClass);
    $intruder = p06cStudent('P06C Out Intruder');

    $this->postJson("/api/students/{$intruder->id}/mutations/out", p06cOutPayload($ownerMembership, $intruder))
        ->assertStatus(422)
        ->assertJsonValidationErrors('student_rombel_id');

    $deletedClass = SchoolRombel::create([
        'version' => 1,
        'school_year_id' => $this->activeYear->id,
        'school_level_id' => $this->levelTen->id,
        'name' => '10-Out-Deleted',
    ]);
    $deletedClassStudent = p06cStudent('P06C Out Deleted Class');
    $deletedClassMembership = p06cMembership($deletedClassStudent, $deletedClass);
    $deletedClass->delete();

    $this->postJson("/api/students/{$deletedClassStudent->id}/mutations/out", p06cOutPayload($deletedClassMembership, $deletedClassStudent))
        ->assertStatus(422)
        ->assertJsonValidationErrors('student_rombel_id');

    $early = p06cStudent('P06C Out Early');
    $earlyMembership = p06cMembership($early, $this->activeClass, [
        'latest_student_entry_date' => '2026-10-01',
    ]);

    $this->postJson("/api/students/{$early->id}/mutations/out", p06cOutPayload($earlyMembership, $early, [
        'effective_date' => '2026-09-15',
    ]))->assertStatus(422)->assertJsonValidationErrors('effective_date');

    $this->postJson("/api/students/{$early->id}/mutations/out", p06cOutPayload($earlyMembership, $early, [
        'effective_date' => '2026-12-01',
    ]))->assertStatus(422)->assertJsonValidationErrors('effective_date');

    $lockedStudent = p06cStudent('P06C Out Locked');
    $lockedMembership = p06cMembership($lockedStudent, $this->lockedClass, [
        'latest_student_entry_date' => '2025-07-15',
    ]);

    $this->postJson("/api/students/{$lockedStudent->id}/mutations/out", p06cOutPayload($lockedMembership, $lockedStudent, [
        'effective_date' => '2025-10-01',
    ]))->assertStatus(422)->assertJsonValidationErrors('school_year_id');

    $this->postJson("/api/students/{$early->id}/mutations/out", p06cOutPayload($earlyMembership, $early, [
        'school_npsn' => '99999999',
    ]))->assertStatus(422)->assertJsonValidationErrors('school_npsn');

    $this->postJson("/api/students/{$early->id}/mutations/out", p06cOutPayload($earlyMembership, $early, [
        'expected_student_version' => 5,
    ]))->assertStatus(409)->assertJsonPath('current_versions.student_version', 1);

    $this->postJson("/api/students/{$early->id}/mutations/out", p06cOutPayload($earlyMembership, $early, [
        'expected_membership_version' => 5,
    ]))->assertStatus(409)->assertJsonPath('current_versions.membership_version', 1);

    $legacyStudent = p06cStudent('P06C Out Legacy');
    p06cStudent('P06C Out Legacy Nik Owner', ['nik' => '8699999999999999']);
    $legacyMembershipId = DB::table('student_rombels')->insertGetId([
        'version' => 1,
        'student_id' => null,
        'student_nik' => '8699999999999999',
        'school_rombel' => $this->activeClass->id,
        'student_status_id' => 1,
        'latest_student_entry_date' => '2026-07-10',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson("/api/students/{$legacyStudent->id}/mutations/out", [
        'student_rombel_id' => $legacyMembershipId,
        'effective_date' => '2026-10-01',
        'school_npsn' => '81000002',
        'reason' => 'Legacy membership ownership probe.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('student_rombel_id');

    expect(StudentMutation::count())->toBe(0)
        ->and((int) $earlyMembership->fresh()->student_status_id)->toBe(1)
        ->and((int) $earlyMembership->fresh()->version)->toBe(1);
});

test('cancelling a mutation-out restores the membership and records a compensating log', function () {
    Sanctum::actingAs($this->operator);

    $student = p06cStudent('P06C Cancel Out');
    $membership = p06cMembership($student, $this->activeClass);

    $this->postJson("/api/students/{$student->id}/mutations/out", p06cOutPayload($membership, $student))
        ->assertStatus(200);

    $membership->refresh();
    $mutation = StudentMutation::where('student_id', $student->id)->firstOrFail();

    $response = $this->postJson("/api/students/{$student->id}/mutations/{$mutation->id}/cancel", [
        'reason' => 'Cancelling the mutation-out record.',
        'expected_student_version' => 2,
        'expected_membership_version' => (int) $membership->version,
        'expected_mutation_version' => 1,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.consequence', 'restored')
        ->assertJsonPath('data.mutation.status', 'cancelled')
        ->assertJsonPath('data.membership.student_status_id', 1)
        ->assertJsonPath('data.membership.latest_student_exit_date', null)
        ->assertJsonPath('data.student_version', 3);

    $mutation->refresh();
    $membership->refresh();

    expect($mutation->status)->toBe('cancelled')
        ->and((int) $mutation->version)->toBe(2)
        ->and((int) $mutation->cancelled_by)->toBe((int) $this->operator->id)
        ->and($mutation->cancel_reason)->toBe('Cancelling the mutation-out record.')
        ->and($mutation->cancelled_at)->not->toBeNull()
        ->and((int) $membership->student_status_id)->toBe(1)
        ->and((int) $membership->version)->toBe(3)
        ->and($membership->latest_student_exit_date)->toBeNull()
        ->and($membership->latest_student_exit_school_id)->toBeNull()
        ->and($membership->latest_student_graduation_date)->toBeNull()
        ->and((int) $student->fresh()->version)->toBe(3);

    expect(Activity::where('table_name', 'student_mutations')->where('record_id', $mutation->id)->where('information', 'Updated')->exists())->toBeTrue();
});

test('cancelling a mutation-out rejects changed state, other active memberships, double cancellation, and stale versions', function () {
    Sanctum::actingAs($this->operator);

    $changed = p06cStudent('P06C Cancel Out Changed');
    $changedMembership = p06cMembership($changed, $this->activeClass);
    $this->postJson("/api/students/{$changed->id}/mutations/out", p06cOutPayload($changedMembership, $changed))
        ->assertStatus(200);
    $changedMutation = StudentMutation::where('student_id', $changed->id)->firstOrFail();

    DB::table('student_rombels')->where('id', $changedMembership->id)
        ->update(['latest_student_exit_school_id' => '81000001']);

    $this->postJson("/api/students/{$changed->id}/mutations/{$changedMutation->id}/cancel", [
        'reason' => 'The membership state no longer matches.',
        'expected_student_version' => 2,
        'expected_membership_version' => 2,
        'expected_mutation_version' => 1,
    ])->assertStatus(409)->assertJsonStructure(['message', 'errors']);

    $otherActive = p06cStudent('P06C Cancel Out Other Active');
    $otherActiveMembership = p06cMembership($otherActive, $this->activeClass);
    $this->postJson("/api/students/{$otherActive->id}/mutations/out", p06cOutPayload($otherActiveMembership, $otherActive, [
        'effective_date' => '2026-09-30',
    ]))->assertStatus(200);
    $otherActiveMutation = StudentMutation::where('student_id', $otherActive->id)->firstOrFail();

    $this->postJson("/api/students/{$otherActive->id}/mutations/in", p06cInPayload([
        'school_rombel_id' => $this->activeClassB->id,
        'effective_date' => '2026-10-10',
        'expected_student_version' => 2,
    ]))->assertStatus(200);

    $this->postJson("/api/students/{$otherActive->id}/mutations/{$otherActiveMutation->id}/cancel", [
        'reason' => 'Reopening would create a duplicate active membership.',
        'expected_student_version' => 3,
        'expected_membership_version' => 2,
        'expected_mutation_version' => 1,
    ])->assertStatus(409);

    $double = p06cStudent('P06C Cancel Out Double');
    $doubleMembership = p06cMembership($double, $this->activeClass);
    $this->postJson("/api/students/{$double->id}/mutations/out", p06cOutPayload($doubleMembership, $double))
        ->assertStatus(200);
    $doubleMembership->refresh();
    $doubleMutation = StudentMutation::where('student_id', $double->id)->firstOrFail();

    $this->postJson("/api/students/{$double->id}/mutations/{$doubleMutation->id}/cancel", [
        'reason' => 'First cancellation succeeds.',
        'expected_student_version' => 2,
        'expected_membership_version' => (int) $doubleMembership->version,
        'expected_mutation_version' => 1,
    ])->assertStatus(200);

    $this->postJson("/api/students/{$double->id}/mutations/{$doubleMutation->id}/cancel", [
        'reason' => 'Second cancellation must fail.',
        'expected_student_version' => 3,
        'expected_membership_version' => 3,
        'expected_mutation_version' => 2,
    ])->assertStatus(409);

    $stale = p06cStudent('P06C Cancel Out Stale');
    $staleMembership = p06cMembership($stale, $this->activeClass);
    $this->postJson("/api/students/{$stale->id}/mutations/out", p06cOutPayload($staleMembership, $stale))
        ->assertStatus(200);
    $staleMutation = StudentMutation::where('student_id', $stale->id)->firstOrFail();

    $this->postJson("/api/students/{$stale->id}/mutations/{$staleMutation->id}/cancel", [
        'reason' => 'Stale membership version must conflict.',
        'expected_student_version' => 2,
        'expected_membership_version' => 1,
        'expected_mutation_version' => 1,
    ])->assertStatus(409)->assertJsonPath('current_versions.membership_version', 2);

    expect($changedMutation->fresh()->status)->toBe('active')
        ->and($otherActiveMutation->fresh()->status)->toBe('active')
        ->and($doubleMutation->fresh()->status)->toBe('cancelled')
        ->and($staleMutation->fresh()->status)->toBe('active');
});

test('cancelling a mutation-in archives the membership without deleting it', function () {
    Sanctum::actingAs($this->operator);

    $student = p06cStudent('P06C Cancel In');
    $this->postJson("/api/students/{$student->id}/mutations/in", p06cInPayload())->assertStatus(200);

    $membership = StudentRombel::where('student_id', $student->id)->firstOrFail();
    $mutation = StudentMutation::where('student_id', $student->id)->firstOrFail();

    $this->postJson("/api/students/{$student->id}/mutations/{$mutation->id}/cancel", [
        'reason' => 'Cancelling the mutation-in record.',
        'expected_student_version' => 2,
        'expected_membership_version' => 1,
        'expected_mutation_version' => 1,
    ])->assertStatus(200)
        ->assertJsonPath('data.consequence', 'archived')
        ->assertJsonPath('data.mutation.status', 'cancelled')
        ->assertJsonPath('data.student_version', 3);

    $membership->refresh();
    $mutation->refresh();

    expect($membership->archived_at)->not->toBeNull()
        ->and($membership->archive_reason)->toBe('Cancelled mutation-in')
        ->and($membership->deleted_at)->toBeNull()
        ->and((int) $membership->version)->toBe(2)
        ->and($mutation->status)->toBe('cancelled')
        ->and((int) $mutation->version)->toBe(2)
        ->and((int) $student->fresh()->version)->toBe(3)
        ->and(StudentRombel::where('student_id', $student->id)->count())->toBe(1);

    expect(Activity::where('table_name', 'student_rombels')->where('record_id', $membership->id)->where('information', 'Updated')->exists())->toBeTrue()
        ->and(Activity::where('table_name', 'student_mutations')->where('record_id', $mutation->id)->where('information', 'Updated')->exists())->toBeTrue();
});

test('cancelling a mutation-in rejects changed classes, closed memberships, and stale versions', function () {
    Sanctum::actingAs($this->operator);

    $changedClass = p06cStudent('P06C Cancel In Changed Class');
    $this->postJson("/api/students/{$changedClass->id}/mutations/in", p06cInPayload())->assertStatus(200);
    $changedMembership = StudentRombel::where('student_id', $changedClass->id)->firstOrFail();
    $changedMutation = StudentMutation::where('student_id', $changedClass->id)->firstOrFail();

    DB::table('student_rombels')->where('id', $changedMembership->id)
        ->update(['school_rombel' => $this->activeClassB->id]);

    $this->postJson("/api/students/{$changedClass->id}/mutations/{$changedMutation->id}/cancel", [
        'reason' => 'The membership class no longer matches the log.',
        'expected_student_version' => 2,
        'expected_membership_version' => 1,
        'expected_mutation_version' => 1,
    ])->assertStatus(409);

    expect(StudentRombel::whereKey($changedMembership->id)->firstOrFail()->archived_at)->toBeNull();

    $closed = p06cStudent('P06C Cancel In Closed Membership');
    $this->postJson("/api/students/{$closed->id}/mutations/in", p06cInPayload())->assertStatus(200);
    $closedMembership = StudentRombel::where('student_id', $closed->id)->firstOrFail();
    $closedMutation = StudentMutation::where('student_id', $closed->id)->where('type', 'in')->firstOrFail();

    $this->postJson("/api/students/{$closed->id}/mutations/out", p06cOutPayload($closedMembership, $closed->fresh(), [
        'effective_date' => '2026-09-30',
    ]))->assertStatus(200);

    $this->postJson("/api/students/{$closed->id}/mutations/{$closedMutation->id}/cancel", [
        'reason' => 'The membership was already closed.',
        'expected_student_version' => 3,
        'expected_membership_version' => 2,
        'expected_mutation_version' => 1,
    ])->assertStatus(409);

    $stale = p06cStudent('P06C Cancel In Stale');
    $this->postJson("/api/students/{$stale->id}/mutations/in", p06cInPayload())->assertStatus(200);
    $staleMutation = StudentMutation::where('student_id', $stale->id)->firstOrFail();

    $this->postJson("/api/students/{$stale->id}/mutations/{$staleMutation->id}/cancel", [
        'reason' => 'Stale student version must conflict.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
        'expected_mutation_version' => 1,
    ])->assertStatus(409)->assertJsonPath('current_versions.student_version', 2);

    expect($changedMutation->fresh()->status)->toBe('active')
        ->and($closedMutation->fresh()->status)->toBe('active')
        ->and($staleMutation->fresh()->status)->toBe('active')
        ->and(StudentRombel::where('student_id', $stale->id)->firstOrFail()->archived_at)->toBeNull();
});

test('locked years reject normal mutations and gate corrections behind the sensitive ability', function () {
    $lockedStudent = p06cStudent('P06C Locked Student');
    $lockedMembership = p06cMembership($lockedStudent, $this->lockedClass, [
        'latest_student_entry_date' => '2025-07-15',
    ]);

    $correctionStudent = p06cStudent('P06C Locked Correction Student');
    $correctionMembership = p06cMembership($correctionStudent, $this->lockedClassB, [
        'latest_student_entry_date' => '2025-07-15',
    ]);

    $legacyMutationId = p06cMutationRow(
        $lockedStudent->id,
        $this->lockedYear->id,
        $this->lockedClass->id,
        'in',
        '2025-07-15',
        '81000001',
        'active',
        $lockedMembership->id
    );

    Sanctum::actingAs($this->operator);

    $this->postJson("/api/students/{$lockedStudent->id}/mutations/out", [
        'student_rombel_id' => $lockedMembership->id,
        'effective_date' => '2025-10-01',
        'school_npsn' => '81000002',
        'reason' => 'Attempting a locked year mutation-out.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('school_year_id');

    $incoming = p06cStudent('P06C Locked Incoming');

    $this->postJson("/api/students/{$incoming->id}/mutations/in", p06cInPayload([
        'school_year_id' => $this->lockedYear->id,
        'school_rombel_id' => $this->lockedClass->id,
        'effective_date' => '2025-08-01',
    ]))->assertStatus(422)->assertJsonValidationErrors('school_year_id');

    $this->postJson("/api/students/{$lockedStudent->id}/mutations/{$legacyMutationId}/cancel", [
        'reason' => 'Cancelling inside a locked year is rejected.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
        'expected_mutation_version' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('mutation_id');

    $payload = [
        'action' => 'membership_exit',
        'school_year_id' => $this->lockedYear->id,
        'student_rombel_id' => $correctionMembership->id,
        'effective_date' => '2025-10-01',
        'target_status_id' => 2,
        'school_npsn' => '81000002',
        'reason' => 'Correcting a locked-year exit record.',
        'approval_reference' => 'DEC-31-UAT',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ];

    $this->postJson("/api/students/{$correctionStudent->id}/locked-corrections", $payload)
        ->assertStatus(403);

    expect(StudentLockedCorrection::count())->toBe(0);

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/students/{$correctionStudent->id}/locked-corrections", $payload)
        ->assertStatus(200)
        ->assertJsonPath('data.correction.action', 'membership_exit')
        ->assertJsonPath('data.correction.approval_reference', 'DEC-31-UAT')
        ->assertJsonPath('data.membership.student_status_id', 2)
        ->assertJsonPath('data.student_version', 2);

    $correctionMembership->refresh();
    $correction = StudentLockedCorrection::firstOrFail();

    expect((int) $correctionMembership->student_status_id)->toBe(2)
        ->and((int) $correctionMembership->version)->toBe(2)
        ->and(substr((string) $correctionMembership->latest_student_exit_date, 0, 10))->toBe('2025-10-01')
        ->and((string) $correctionMembership->latest_student_exit_school_id)->toBe('81000002')
        ->and($correction->target_type)->toBe('membership')
        ->and((int) $correction->target_id)->toBe((int) $correctionMembership->id)
        ->and((int) $correction->before['membership']['student_status_id'])->toBe(1)
        ->and((int) $correction->after['membership']['student_status_id'])->toBe(2)
        ->and((int) $correction->created_by)->toBe((int) $this->admin->id);

    expect(fn () => $correction->update(['reason' => 'Mutating a correction must fail.']))
        ->toThrow(DomainException::class);
    expect(fn () => $correction->delete())
        ->toThrow(DomainException::class);

    $unlockedStudent = p06cStudent('P06C Unlocked Correction Student');
    $unlockedMembership = p06cMembership($unlockedStudent, $this->activeClass);

    $this->postJson("/api/students/{$unlockedStudent->id}/locked-corrections", [
        'action' => 'membership_exit',
        'school_year_id' => $this->activeYear->id,
        'student_rombel_id' => $unlockedMembership->id,
        'effective_date' => '2026-10-01',
        'target_status_id' => 2,
        'reason' => 'Unlocked years must use normal mutations.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('school_year_id');
});

test('locked corrections can change mutation dates and origin schools consistently', function () {
    $inStudent = p06cStudent('P06C Correct In Student');
    $inMembership = p06cMembership($inStudent, $this->lockedClass, [
        'student_entry_id' => 2,
        'latest_student_entry_date' => '2025-08-01',
        'mutation_education_school_id' => '81000001',
    ]);
    $inMutationId = p06cMutationRow(
        $inStudent->id,
        $this->lockedYear->id,
        $this->lockedClass->id,
        'in',
        '2025-08-01',
        '81000001',
        'active',
        $inMembership->id
    );

    $outStudent = p06cStudent('P06C Correct Out Student');
    $outMembership = p06cMembership($outStudent, $this->lockedClassB, [
        'student_status_id' => 2,
        'latest_student_entry_date' => '2025-08-01',
        'latest_student_exit_date' => '2025-11-01',
        'latest_student_exit_school_id' => '81000002',
    ]);
    $outMutationId = p06cMutationRow(
        $outStudent->id,
        $this->lockedYear->id,
        $this->lockedClassB->id,
        'out',
        '2025-11-01',
        '81000002',
        'active',
        $outMembership->id
    );

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/students/{$inStudent->id}/locked-corrections", [
        'action' => 'mutation_in',
        'school_year_id' => $this->lockedYear->id,
        'mutation_id' => $inMutationId,
        'effective_date' => '2025-08-05',
        'school_npsn' => '81000002',
        'reason' => 'Correcting the mutation-in date and origin school.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
        'expected_mutation_version' => 1,
    ])->assertStatus(200)
        ->assertJsonPath('data.correction.action', 'mutation_in')
        ->assertJsonPath('data.correction.before.mutation.effective_date', '2025-08-01')
        ->assertJsonPath('data.correction.after.mutation.effective_date', '2025-08-05')
        ->assertJsonPath('data.correction.before.membership.latest_student_entry_date', '2025-08-01')
        ->assertJsonPath('data.correction.after.membership.latest_student_entry_date', '2025-08-05')
        ->assertJsonPath('data.mutation.version', 2)
        ->assertJsonPath('data.membership.version', 2)
        ->assertJsonPath('data.student_version', 2);

    $inMembership->refresh();
    $inMutation = StudentMutation::whereKey($inMutationId)->firstOrFail();

    expect(substr((string) $inMembership->latest_student_entry_date, 0, 10))->toBe('2025-08-05')
        ->and((string) $inMembership->mutation_education_school_id)->toBe('81000002')
        ->and((int) $inMembership->version)->toBe(2)
        ->and(substr((string) $inMutation->effective_date, 0, 10))->toBe('2025-08-05')
        ->and((string) $inMutation->school_npsn)->toBe('81000002')
        ->and((int) $inMutation->version)->toBe(2);

    $this->postJson("/api/students/{$outStudent->id}/locked-corrections", [
        'action' => 'mutation_out',
        'school_year_id' => $this->lockedYear->id,
        'mutation_id' => $outMutationId,
        'effective_date' => '2025-11-05',
        'reason' => 'Correcting the mutation-out effective date.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
        'expected_mutation_version' => 1,
    ])->assertStatus(200)
        ->assertJsonPath('data.correction.action', 'mutation_out')
        ->assertJsonPath('data.correction.before.mutation.effective_date', '2025-11-01')
        ->assertJsonPath('data.correction.after.mutation.effective_date', '2025-11-05')
        ->assertJsonPath('data.mutation.version', 2)
        ->assertJsonPath('data.membership.version', 2);

    $outMembership->refresh();
    $outMutation = StudentMutation::whereKey($outMutationId)->firstOrFail();

    expect(substr((string) $outMembership->latest_student_exit_date, 0, 10))->toBe('2025-11-05')
        ->and((string) $outMembership->latest_student_exit_school_id)->toBe('81000002')
        ->and(substr((string) $outMutation->effective_date, 0, 10))->toBe('2025-11-05')
        ->and((int) $outMutation->version)->toBe(2);

    $this->postJson("/api/students/{$outStudent->id}/locked-corrections", [
        'action' => 'mutation_out',
        'school_year_id' => $this->lockedYear->id,
        'mutation_id' => $outMutationId,
        'effective_date' => '2025-11-05',
        'school_npsn' => '81000002',
        'reason' => 'An unchanged mutation is not a correction.',
        'expected_student_version' => 2,
        'expected_membership_version' => 2,
        'expected_mutation_version' => 2,
    ])->assertStatus(422)->assertJsonValidationErrors('effective_date');

    $this->postJson("/api/students/{$inStudent->id}/locked-corrections", [
        'action' => 'mutation_out',
        'school_year_id' => $this->lockedYear->id,
        'mutation_id' => $inMutationId,
        'effective_date' => '2025-08-06',
        'reason' => 'The action must match the mutation type.',
        'expected_student_version' => 2,
        'expected_membership_version' => 2,
        'expected_mutation_version' => 2,
    ])->assertStatus(422)->assertJsonValidationErrors('mutation_id');

    expect(StudentLockedCorrection::count())->toBe(2);
});

test('locked corrections can exit and reopen memberships with conflict guards', function () {
    $reopenStudent = p06cStudent('P06C Reopen Student');
    $reopenMembership = p06cMembership($reopenStudent, $this->lockedClass, [
        'student_status_id' => 2,
        'latest_student_entry_date' => '2025-07-15',
        'latest_student_exit_date' => '2025-12-01',
        'latest_student_exit_school_id' => '81000002',
    ]);

    $otherActiveStudent = p06cStudent('P06C Reopen Other Active Student');
    $closedMembership = p06cMembership($otherActiveStudent, $this->lockedClass, [
        'student_status_id' => 2,
        'latest_student_entry_date' => '2025-07-15',
        'latest_student_exit_date' => '2025-10-01',
        'latest_student_exit_school_id' => '81000002',
    ]);
    p06cMembership($otherActiveStudent, $this->lockedClassB, [
        'latest_student_entry_date' => '2025-10-05',
    ]);

    $activeMutationStudent = p06cStudent('P06C Reopen Active Mutation Student');
    $activeMutationMembership = p06cMembership($activeMutationStudent, $this->lockedClassB, [
        'student_status_id' => 2,
        'latest_student_entry_date' => '2025-07-15',
        'latest_student_exit_date' => '2025-10-01',
        'latest_student_exit_school_id' => '81000002',
    ]);
    p06cMutationRow(
        $activeMutationStudent->id,
        $this->lockedYear->id,
        $this->lockedClassB->id,
        'out',
        '2025-10-01',
        '81000002',
        'active',
        $activeMutationMembership->id
    );

    $exitActiveMutationStudent = p06cStudent('P06C Exit Active Mutation Student');
    $exitActiveMutationMembership = p06cMembership($exitActiveMutationStudent, $this->lockedClass, [
        'student_entry_id' => 2,
        'latest_student_entry_date' => '2025-07-15',
        'mutation_education_school_id' => '81000001',
    ]);
    p06cMutationRow(
        $exitActiveMutationStudent->id,
        $this->lockedYear->id,
        $this->lockedClass->id,
        'in',
        '2025-07-15',
        '81000001',
        'active',
        $exitActiveMutationMembership->id
    );

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/students/{$reopenStudent->id}/locked-corrections", [
        'action' => 'membership_reopen',
        'school_year_id' => $this->lockedYear->id,
        'student_rombel_id' => $reopenMembership->id,
        'reason' => 'Reopening the membership after an erroneous exit.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(200)
        ->assertJsonPath('data.correction.action', 'membership_reopen')
        ->assertJsonPath('data.membership.student_status_id', 1)
        ->assertJsonPath('data.student_version', 2);

    $reopenMembership->refresh();

    expect((int) $reopenMembership->student_status_id)->toBe(1)
        ->and((int) $reopenMembership->version)->toBe(2)
        ->and($reopenMembership->latest_student_exit_date)->toBeNull()
        ->and($reopenMembership->latest_student_exit_school_id)->toBeNull()
        ->and($reopenMembership->latest_student_graduation_date)->toBeNull();

    $this->postJson("/api/students/{$otherActiveStudent->id}/locked-corrections", [
        'action' => 'membership_reopen',
        'school_year_id' => $this->lockedYear->id,
        'student_rombel_id' => $closedMembership->id,
        'reason' => 'Reopening would duplicate the active membership.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(409);

    $this->postJson("/api/students/{$activeMutationStudent->id}/locked-corrections", [
        'action' => 'membership_reopen',
        'school_year_id' => $this->lockedYear->id,
        'student_rombel_id' => $activeMutationMembership->id,
        'reason' => 'The active mutation must be cancelled first.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(409);

    $this->postJson("/api/students/{$exitActiveMutationStudent->id}/locked-corrections", [
        'action' => 'membership_exit',
        'school_year_id' => $this->lockedYear->id,
        'student_rombel_id' => $exitActiveMutationMembership->id,
        'effective_date' => '2025-10-01',
        'target_status_id' => 2,
        'school_npsn' => '81000002',
        'reason' => 'The active mutation must be cancelled first.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(409);

    $this->postJson("/api/students/{$reopenStudent->id}/locked-corrections", [
        'action' => 'membership_reopen',
        'school_year_id' => $this->lockedYear->id,
        'student_rombel_id' => $reopenMembership->id,
        'reason' => 'Only exited or graduated memberships can reopen.',
        'expected_student_version' => 2,
        'expected_membership_version' => 2,
    ])->assertStatus(409);

    $this->postJson("/api/students/{$otherActiveStudent->id}/locked-corrections", [
        'action' => 'membership_exit',
        'school_year_id' => $this->lockedYear->id,
        'student_rombel_id' => $closedMembership->id,
        'effective_date' => '2025-10-02',
        'target_status_id' => 1,
        'reason' => 'The target status must be mutation out or graduated.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(422)->assertJsonValidationErrors('target_status_id');

    expect(StudentLockedCorrection::count())->toBe(1);
});

test('recap counts active mutations in range, distinct graduations, and latest retained decisions', function () {
    $mutationInA = p06cStudent('P06C Recap In A');
    $mutationInB = p06cStudent('P06C Recap In B');
    $mutationInCancelled = p06cStudent('P06C Recap In Cancelled');
    $mutationOut = p06cStudent('P06C Recap Out');
    $mutationOutOfRange = p06cStudent('P06C Recap Out Of Range');

    p06cMutationRow($mutationInA->id, $this->activeYear->id, $this->activeClass->id, 'in', '2026-08-01');
    p06cMutationRow($mutationInB->id, $this->activeYear->id, $this->activeClassB->id, 'in', '2026-09-01');
    p06cMutationRow($mutationInCancelled->id, $this->activeYear->id, $this->activeClass->id, 'in', '2026-10-01', '81000001', 'cancelled');
    p06cMutationRow($mutationOut->id, $this->activeYear->id, $this->activeClass->id, 'out', '2026-11-01');
    p06cMutationRow($mutationOutOfRange->id, $this->activeYear->id, $this->activeClass->id, 'out', '2027-07-05');

    $graduatedA = p06cStudent('P06C Recap Graduated A');
    $graduatedB = p06cStudent('P06C Recap Graduated B');
    $graduatedOutOfRange = p06cStudent('P06C Recap Graduated Out Of Range');

    p06cMembership($graduatedA, $this->activeClass, [
        'student_status_id' => 6,
        'latest_student_exit_date' => '2027-06-01',
        'latest_student_graduation_date' => '2027-06-01',
    ]);
    p06cMembership($graduatedB, $this->activeClassB, [
        'student_status_id' => 6,
        'latest_student_exit_date' => '2027-06-20',
        'latest_student_graduation_date' => '2027-06-20',
    ]);
    p06cMembership($graduatedOutOfRange, $this->nextClass, [
        'student_status_id' => 6,
        'latest_student_entry_date' => '2027-07-10',
        'latest_student_exit_date' => '2027-07-15',
        'latest_student_graduation_date' => '2027-07-15',
    ]);

    $retainedA = p06cStudent('P06C Recap Retained A');
    $promotedB = p06cStudent('P06C Recap Promoted B');
    $retainedC = p06cStudent('P06C Recap Retained C');
    $previewD = p06cStudent('P06C Recap Preview D');

    StudentProgressionBatch::create([
        'token_hash' => str_repeat('a', 64),
        'source_school_year_id' => $this->activeYear->id,
        'target_school_year_id' => $this->nextYear->id,
        'status' => StudentProgressionBatch::STATUS_COMMITTED,
        'decisions' => [
            ['student_id' => $retainedA->id, 'decision' => 'promote'],
            ['student_id' => $retainedC->id, 'decision' => 'retain'],
        ],
        'summary' => [],
        'created_by' => $this->operator->id,
        'committed_by' => $this->operator->id,
        'expires_at' => now()->addHour(),
        'committed_at' => now()->subDay(),
    ]);

    StudentProgressionBatch::create([
        'token_hash' => str_repeat('b', 64),
        'source_school_year_id' => $this->activeYear->id,
        'target_school_year_id' => $this->nextYear->id,
        'status' => StudentProgressionBatch::STATUS_COMMITTED,
        'decisions' => [
            ['student_id' => $retainedA->id, 'decision' => 'retain'],
            ['student_id' => $promotedB->id, 'decision' => 'promote'],
        ],
        'summary' => [],
        'created_by' => $this->operator->id,
        'committed_by' => $this->operator->id,
        'expires_at' => now()->addHour(),
        'committed_at' => now(),
    ]);

    StudentProgressionBatch::create([
        'token_hash' => str_repeat('c', 64),
        'source_school_year_id' => $this->activeYear->id,
        'target_school_year_id' => $this->nextYear->id,
        'status' => StudentProgressionBatch::STATUS_PREVIEW,
        'decisions' => [
            ['student_id' => $previewD->id, 'decision' => 'retain'],
        ],
        'summary' => [],
        'created_by' => $this->operator->id,
        'expires_at' => now()->addHour(),
    ]);

    Sanctum::actingAs($this->principal);

    $this->getJson('/api/academic/mutations/recap?school_year_id='.$this->activeYear->id)
        ->assertStatus(200)
        ->assertJsonPath('data.mutation_in', 2)
        ->assertJsonPath('data.mutation_out', 1)
        ->assertJsonPath('data.graduated', 2)
        ->assertJsonPath('data.retained', 2)
        ->assertJsonPath('data.period.start_date', '2026-07-01')
        ->assertJsonPath('data.period.end_date', '2027-06-30');

    $this->getJson('/api/academic/mutations/recap?school_year_id='.$this->noDateYear->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors('school_year_id');

    $this->getJson('/api/academic/mutations?school_year_id='.$this->activeYear->id.'&status=active')
        ->assertStatus(200)
        ->assertJsonCount(4, 'data.items');
});

test('audit failures roll back mutation, cancellation, and correction transactions', function () {
    Sanctum::actingAs($this->operator);

    $failOnMutationAudit = function () {
        Activity::$testCreatingHook = function ($activity) {
            if ($activity->table_name === 'student_mutations') {
                throw new RuntimeException('Simulated audit failure for student_mutations.');
            }
        };
    };

    $inStudent = p06cStudent('P06C Rollback In');
    $failOnMutationAudit();

    try {
        $this->postJson("/api/students/{$inStudent->id}/mutations/in", p06cInPayload())
            ->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    expect(StudentMutation::count())->toBe(0)
        ->and(StudentRombel::where('student_id', $inStudent->id)->count())->toBe(0)
        ->and((int) $inStudent->fresh()->version)->toBe(1);

    $outStudent = p06cStudent('P06C Rollback Out');
    $outMembership = p06cMembership($outStudent, $this->activeClass);
    $failOnMutationAudit();

    try {
        $this->postJson("/api/students/{$outStudent->id}/mutations/out", p06cOutPayload($outMembership, $outStudent))
            ->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    expect(StudentMutation::count())->toBe(0)
        ->and((int) $outMembership->fresh()->student_status_id)->toBe(1)
        ->and((int) $outMembership->fresh()->version)->toBe(1)
        ->and((int) $outStudent->fresh()->version)->toBe(1);

    $cancelStudent = p06cStudent('P06C Rollback Cancel');
    $cancelMembership = p06cMembership($cancelStudent, $this->activeClass);
    $this->postJson("/api/students/{$cancelStudent->id}/mutations/out", p06cOutPayload($cancelMembership, $cancelStudent))
        ->assertStatus(200);

    $cancelMutation = StudentMutation::where('student_id', $cancelStudent->id)->firstOrFail();
    $cancelMembership->refresh();
    $failOnMutationAudit();

    try {
        $this->postJson("/api/students/{$cancelStudent->id}/mutations/{$cancelMutation->id}/cancel", [
            'reason' => 'This cancellation must roll back.',
            'expected_student_version' => 2,
            'expected_membership_version' => (int) $cancelMembership->version,
            'expected_mutation_version' => 1,
        ])->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    expect($cancelMutation->fresh()->status)->toBe('active')
        ->and((int) $cancelMutation->fresh()->version)->toBe(1)
        ->and((int) $cancelMembership->fresh()->student_status_id)->toBe(2)
        ->and((int) $cancelMembership->fresh()->version)->toBe(2)
        ->and((int) $cancelStudent->fresh()->version)->toBe(2);

    $correctionStudent = p06cStudent('P06C Rollback Correction');
    $correctionMembership = p06cMembership($correctionStudent, $this->lockedClass, [
        'student_entry_id' => 2,
        'latest_student_entry_date' => '2025-08-01',
        'mutation_education_school_id' => '81000001',
    ]);
    $correctionMutationId = p06cMutationRow(
        $correctionStudent->id,
        $this->lockedYear->id,
        $this->lockedClass->id,
        'in',
        '2025-08-01',
        '81000001',
        'active',
        $correctionMembership->id
    );

    Sanctum::actingAs($this->admin);
    $failOnMutationAudit();

    try {
        $this->postJson("/api/students/{$correctionStudent->id}/locked-corrections", [
            'action' => 'mutation_in',
            'school_year_id' => $this->lockedYear->id,
            'mutation_id' => $correctionMutationId,
            'effective_date' => '2025-08-05',
            'reason' => 'This correction must roll back.',
            'expected_student_version' => 1,
            'expected_membership_version' => 1,
            'expected_mutation_version' => 1,
        ])->assertStatus(500);
    } finally {
        Activity::$testCreatingHook = null;
    }

    expect(StudentLockedCorrection::count())->toBe(0)
        ->and(substr((string) $correctionMembership->fresh()->latest_student_entry_date, 0, 10))->toBe('2025-08-01')
        ->and((int) $correctionMembership->fresh()->version)->toBe(1)
        ->and(substr((string) StudentMutation::whereKey($correctionMutationId)->firstOrFail()->effective_date, 0, 10))->toBe('2025-08-01')
        ->and((int) StudentMutation::whereKey($correctionMutationId)->firstOrFail()->version)->toBe(1)
        ->and((int) $correctionStudent->fresh()->version)->toBe(1);
});

test('audit allowlists keep personal data and free-text reasons out of mutation activities', function () {
    Sanctum::actingAs($this->operator);

    $student = p06cStudent('P06C Privacy Student');
    $membership = p06cMembership($student, $this->activeClass, [
        'latest_information' => 'Do not audit latest information.',
    ]);

    $this->postJson("/api/students/{$student->id}/mutations/out", p06cOutPayload($membership, $student, [
        'reason' => 'Confidential transfer reason for privacy test.',
    ]))->assertStatus(200);

    $mutation = StudentMutation::where('student_id', $student->id)->firstOrFail();
    $mutationActivity = Activity::where('table_name', 'student_mutations')
        ->where('record_id', $mutation->id)
        ->where('information', 'Created')
        ->firstOrFail();

    $mutationPayload = json_encode($mutationActivity->new);

    expect($mutationPayload)->not->toContain('P06C Privacy Student')
        ->and($mutationPayload)->not->toContain((string) $student->nik)
        ->and($mutationPayload)->not->toContain('Confidential transfer reason for privacy test.')
        ->and($mutationPayload)->toContain('81000002');

    $membershipActivity = Activity::where('table_name', 'student_rombels')
        ->where('record_id', $membership->id)
        ->where('information', 'Updated')
        ->firstOrFail();

    expect(array_key_exists('latest_information', $membershipActivity->after ?? []))->toBeFalse()
        ->and(array_key_exists('student_nik', $membershipActivity->after ?? []))->toBeFalse();

    $correctionStudent = p06cStudent('P06C Privacy Correction');
    $correctionMembership = p06cMembership($correctionStudent, $this->lockedClass, [
        'latest_student_entry_date' => '2025-07-15',
    ]);

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/students/{$correctionStudent->id}/locked-corrections", [
        'action' => 'membership_exit',
        'school_year_id' => $this->lockedYear->id,
        'student_rombel_id' => $correctionMembership->id,
        'effective_date' => '2025-10-01',
        'target_status_id' => 2,
        'reason' => 'Correction reason stays out of activities.',
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(200);

    expect(Activity::where('table_name', 'student_locked_corrections')->count())->toBe(0);
});

test('school years that own mutations or locked corrections cannot be soft deleted', function () {
    Sanctum::actingAs($this->operator);

    $emptyYear = SchoolYear::create([
        'name' => '2030/2031',
        'start_date' => '2030-07-01',
        'end_date' => '2031-06-30',
    ]);

    $this->deleteJson("/api/school-years/{$emptyYear->id}", ['delete' => 'true'])->assertStatus(200);

    expect(SchoolYear::withTrashed()->whereKey($emptyYear->id)->firstOrFail()->trashed())->toBeTrue();

    $guardYear = SchoolYear::create([
        'name' => '2031/2032',
        'start_date' => '2031-07-01',
        'end_date' => '2032-06-30',
    ]);

    $guardStudent = p06cStudent('P06C Year Guard Mutation');
    p06cMutationRow($guardStudent->id, $guardYear->id, $this->activeClass->id, 'in', '2031-08-01');

    $this->deleteJson("/api/school-years/{$guardYear->id}", ['delete' => 'true'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This record is still being referenced by 1 mutation log(s).');

    DB::table('student_mutations')->where('school_year_id', $guardYear->id)->delete();
    p06cCorrectionRow($guardStudent->id, $guardYear->id);

    $this->deleteJson("/api/school-years/{$guardYear->id}", ['delete' => 'true'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This record is still being referenced by 1 locked correction(s).');

    DB::table('student_locked_corrections')->where('school_year_id', $guardYear->id)->delete();

    $this->deleteJson("/api/school-years/{$guardYear->id}", ['delete' => 'true'])->assertStatus(200);

    expect(SchoolYear::withTrashed()->whereKey($guardYear->id)->firstOrFail()->trashed())->toBeTrue();
});

test('mutation endpoints are non-idempotent and permanent logs cannot be deleted', function () {
    Sanctum::actingAs($this->operator);

    $student = p06cStudent('P06C Duplicate Student');

    $first = $this->postJson("/api/students/{$student->id}/mutations/in", p06cInPayload());
    $first->assertStatus(200);
    expect($first->json('data'))->not->toHaveKey('token');

    $this->postJson("/api/students/{$student->id}/mutations/in", p06cInPayload())->assertStatus(409);

    expect(StudentMutation::where('student_id', $student->id)->count())->toBe(1)
        ->and(StudentRombel::where('student_id', $student->id)->count())->toBe(1);

    $mutation = StudentMutation::where('student_id', $student->id)->firstOrFail();

    $this->postJson("/api/students/{$student->id}/mutations/{$mutation->id}/cancel", [
        'reason' => 'First cancellation succeeds.',
        'expected_student_version' => 2,
        'expected_membership_version' => 1,
        'expected_mutation_version' => 1,
    ])->assertStatus(200);

    $this->postJson("/api/students/{$student->id}/mutations/{$mutation->id}/cancel", [
        'reason' => 'Second cancellation must conflict.',
        'expected_student_version' => 3,
        'expected_membership_version' => 2,
        'expected_mutation_version' => 2,
    ])->assertStatus(409);

    expect(StudentMutation::where('student_id', $student->id)->count())->toBe(1)
        ->and(StudentMutation::where('student_id', $student->id)->firstOrFail()->status)->toBe('cancelled');

    expect(fn () => $mutation->fresh()->delete())->toThrow(DomainException::class);
});

test('mutation and correction routes are registered with the expected middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes());

    $find = function (string $method, string $uri) use ($routes) {
        return $routes->first(fn ($route) => in_array($method, $route->methods(), true) && $route->uri() === $uri);
    };

    $expected = [
        ['GET', 'api/academic/mutations/options', 'can:students.view'],
        ['GET', 'api/academic/mutations/schools', 'can:students.view'],
        ['GET', 'api/academic/mutations/recap', 'can:students.view'],
        ['GET', 'api/academic/mutations', 'can:students.view'],
        ['GET', 'api/students/{id}/mutations', 'can:students.view'],
        ['GET', 'api/students/{id}/locked-corrections', 'can:students.view'],
        ['POST', 'api/students/{id}/mutations/in', 'can:students.manage'],
        ['POST', 'api/students/{id}/mutations/out', 'can:students.manage'],
        ['POST', 'api/students/{id}/mutations/{mutation}/cancel', 'can:students.manage'],
    ];

    foreach ($expected as [$method, $uri, $middleware]) {
        $route = $find($method, $uri);
        expect($route)->not->toBeNull();
        expect($route->gatherMiddleware())->toContain($middleware);
    }

    $correction = $find('POST', 'api/students/{id}/locked-corrections');
    expect($correction)->not->toBeNull();
    expect($correction->gatherMiddleware())
        ->toContain('can:students.manage')
        ->toContain('can:students.correct-locked');

    $phaseRoutes = $routes->filter(function ($route) {
        $uri = $route->uri();

        return str_starts_with($uri, 'api/academic/mutations')
            || preg_match('#^api/students/\{id\}/(?:mutations|locked-corrections)(?:/|$)#', $uri) === 1;
    });

    expect($phaseRoutes)->toHaveCount(10)
        ->and($phaseRoutes->contains(fn ($route) => str_contains($route->uri(), 'token')))->toBeFalse();
});
