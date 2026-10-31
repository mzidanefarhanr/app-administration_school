<?php

use App\Models\AcademicSetting;
use App\Models\Activity;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(RefreshDatabase::class, CreatesAccessFixtures::class);

beforeEach(function () {
    $this->seedAccessReferenceData();

    $this->operator = $this->makeUserWithRole('operator-sekolah');
    $this->principal = $this->makeUserWithRole('kepala-sekolah');
    $this->plainUser = $this->makeUserWithRole();

    $this->year = SchoolYear::create([
        'name' => '2026/2027',
        'start_date' => '2026-07-01',
        'end_date' => '2027-06-30',
    ]);
    $this->otherYear = SchoolYear::create(['name' => '2025/2026']);
    $this->semester = Semester::create(['name' => 'Ganjil', 'name_other' => 'Odd']);
    $this->level = SchoolLevel::create(['name' => 'Grade 10']);

    $this->rombel = SchoolRombel::create([
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'name' => '10-A',
    ]);
});

test('guest cannot read or set the academic period and cannot lock a year', function () {
    $this->getJson('/api/academic/period')->assertStatus(401);
    $this->putJson('/api/academic/period', [
        'active_school_year_id' => $this->year->id,
        'active_semester_id' => $this->semester->id,
    ])->assertStatus(401);
    $this->postJson("/api/school-years/{$this->year->id}/lock", ['reason' => 'Term closed'])->assertStatus(401);
});

test('view-only accounts can read the period but cannot set it or lock years', function () {
    Sanctum::actingAs($this->principal);

    $this->getJson('/api/academic/period')
        ->assertStatus(200)
        ->assertJsonPath('data.active', null)
        ->assertJsonCount(2, 'data.school_years')
        ->assertJsonCount(1, 'data.semesters');

    $this->putJson('/api/academic/period', [
        'active_school_year_id' => $this->year->id,
        'active_semester_id' => $this->semester->id,
    ])->assertStatus(403);

    $this->postJson("/api/school-years/{$this->year->id}/lock", ['reason' => 'Term closed'])->assertStatus(403);
    $this->postJson("/api/school-years/{$this->year->id}/unlock", ['reason' => 'Reopen'])->assertStatus(403);
});

test('operator can set a single active period and the change is audited', function () {
    Sanctum::actingAs($this->operator);

    $this->putJson('/api/academic/period', [
        'active_school_year_id' => $this->year->id,
        'active_semester_id' => $this->semester->id,
    ])
        ->assertStatus(200)
        ->assertJsonPath('data.active.school_year.id', $this->year->id)
        ->assertJsonPath('data.active.semester.id', $this->semester->id);

    $settings = AcademicSetting::find(1);
    expect($settings->active_school_year_id)->toBe($this->year->id)
        ->and($settings->active_semester_id)->toBe($this->semester->id)
        ->and($settings->updated_by)->toBe($this->operator->id);

    $audit = Activity::where('table_name', 'academic_settings')->where('record_id', 1)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($this->operator->id);

    $this->getJson('/api/academic/period')
        ->assertStatus(200)
        ->assertJsonPath('data.active.school_year.is_active', true);
});

test('setting the active period rejects locked or soft-deleted years and missing semesters', function () {
    Sanctum::actingAs($this->operator);

    $lockedYear = SchoolYear::create(['name' => '2024/2025']);
    $lockedYear->update(['locked_at' => now(), 'locked_by' => $this->operator->id, 'lock_reason' => 'Closed']);

    $this->putJson('/api/academic/period', [
        'active_school_year_id' => $lockedYear->id,
        'active_semester_id' => $this->semester->id,
    ])->assertStatus(422)->assertJsonPath('errors.active_school_year_id.0', 'A locked school year cannot be set as the active period.');

    $deletedYear = SchoolYear::create(['name' => '2023/2024']);
    $deletedYear->delete();

    $this->putJson('/api/academic/period', [
        'active_school_year_id' => $deletedYear->id,
        'active_semester_id' => $this->semester->id,
    ])->assertStatus(422);

    $this->putJson('/api/academic/period', [
        'active_school_year_id' => $this->year->id,
        'active_semester_id' => 999999,
    ])->assertStatus(422)->assertJsonPath('errors.active_semester_id.0', 'The selected active semester is not available.');
});

test('active year cannot be locked, lock requires a reason, and repeated lock is idempotent', function () {
    Sanctum::actingAs($this->operator);

    AcademicSetting::find(1)->update(['active_school_year_id' => $this->year->id]);

    $this->postJson("/api/school-years/{$this->year->id}/lock", ['reason' => ''])
        ->assertStatus(422);

    $this->postJson("/api/school-years/{$this->year->id}/lock", ['reason' => 'Term closed'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The active school year cannot be locked. Set another active period first.');

    $this->postJson("/api/school-years/{$this->otherYear->id}/lock", ['reason' => 'Term closed'])
        ->assertStatus(200)
        ->assertJsonPath('data.is_locked', true)
        ->assertJsonPath('data.lock_reason', 'Term closed');

    $this->postJson("/api/school-years/{$this->otherYear->id}/lock", ['reason' => 'Again'])
        ->assertStatus(200)
        ->assertJsonPath('data.lock_reason', 'Term closed');

    $this->otherYear->refresh();
    expect($this->otherYear->locked_at)->not->toBeNull()
        ->and($this->otherYear->locked_by)->toBe($this->operator->id)
        ->and($this->otherYear->lock_reason)->toBe('Term closed');

    $audit = Activity::where('table_name', 'school_years')->where('record_id', $this->otherYear->id)->first();
    expect($audit)->not->toBeNull();
});

test('unlock requires a reason, clears the lock, and repeated unlock is idempotent', function () {
    Sanctum::actingAs($this->operator);

    $this->otherYear->update(['locked_at' => now(), 'locked_by' => $this->operator->id, 'lock_reason' => 'Closed']);

    $this->postJson("/api/school-years/{$this->otherYear->id}/unlock", ['reason' => ''])
        ->assertStatus(422);

    $this->postJson("/api/school-years/{$this->otherYear->id}/unlock", ['reason' => 'Reopen corrections'])
        ->assertStatus(200)
        ->assertJsonPath('data.is_locked', false);

    $this->otherYear->refresh();
    expect($this->otherYear->locked_at)->toBeNull()
        ->and($this->otherYear->locked_by)->toBeNull()
        ->and($this->otherYear->lock_reason)->toBeNull();

    $this->postJson("/api/school-years/{$this->otherYear->id}/unlock", ['reason' => 'Again'])
        ->assertStatus(200)
        ->assertJsonPath('data.is_locked', false);
});

test('locked year blocks class create, update, delete, assign, and transfer', function () {
    Sanctum::actingAs($this->operator);

    $student = Student::create(['name' => 'Phase 06 Student', 'version' => 1]);
    $placedStudent = Student::create(['name' => 'Phase 06 Placed Student', 'nik' => '9000000000000061', 'version' => 1]);
    $targetClass = SchoolRombel::create([
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'name' => '10-B',
    ]);

    $status = StudentStatus::firstOrCreate(['id' => 1], ['name' => 'Aktif']);
    StudentRombel::create([
        'student_id' => $placedStudent->id,
        'student_nik' => $placedStudent->nik,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $status->id,
        'latest_student_entry_date' => '2026-07-15',
        'version' => 1,
    ]);

    $this->year->update(['locked_at' => now(), 'locked_by' => $this->operator->id, 'lock_reason' => 'Closed']);

    $this->postJson('/api/school-rombels', [
        'name' => '10-B',
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
    ])->assertStatus(422)->assertJsonPath('errors.school_year_id.0', 'The selected academic year is locked. Unlock it before changing classes.');

    $this->putJson("/api/school-rombels/{$this->rombel->id}", [
        'name' => '10-A Updated',
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'expected_version' => 1,
    ])->assertStatus(422);

    $this->deleteJson("/api/school-rombels/{$this->rombel->id}", ['expected_version' => 1])
        ->assertStatus(422);

    $this->postJson("/api/students/{$student->id}/assign-rombel", [
        'school_year_id' => $this->year->id,
        'school_rombel_id' => $this->rombel->id,
        'expected_student_version' => 1,
    ])->assertStatus(422)->assertJsonPath('message', 'The selected academic year is locked. Unlock it before placing students.');

    $this->postJson("/api/students/{$placedStudent->id}/transfer-rombel", [
        'school_year_id' => $this->year->id,
        'target_rombel_id' => $targetClass->id,
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(422)->assertJsonPath('message', 'The selected academic year is locked. Unlock it before moving students.');
});

test('soft-deleted year is rejected by assign and transfer', function () {
    Sanctum::actingAs($this->operator);

    $student = Student::create(['name' => 'Archived Year Student', 'version' => 1]);
    $placedStudent = Student::create(['name' => 'Archived Year Placed', 'nik' => '9000000000000062', 'version' => 1]);
    $targetClass = SchoolRombel::create([
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'name' => '10-B',
    ]);
    $status = StudentStatus::firstOrCreate(['id' => 1], ['name' => 'Aktif']);
    $membership = StudentRombel::create([
        'student_id' => $placedStudent->id,
        'student_nik' => $placedStudent->nik,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $status->id,
        'latest_student_entry_date' => '2026-07-15',
        'version' => 1,
    ]);

    $this->rombel->delete();
    $targetClass->delete();
    $this->year->delete();

    $this->postJson("/api/students/{$student->id}/assign-rombel", [
        'school_year_id' => $this->year->id,
        'school_rombel_id' => $this->rombel->id,
        'expected_student_version' => 1,
    ])->assertStatus(422);

    $this->postJson("/api/students/{$placedStudent->id}/transfer-rombel", [
        'school_year_id' => $this->year->id,
        'target_rombel_id' => $targetClass->id,
        'expected_student_version' => 1,
        'expected_membership_version' => (int) $membership->version,
    ])->assertStatus(422);
});

test('school year dates must be ordered and can be updated without touching the lock', function () {
    Sanctum::actingAs($this->operator);

    $this->putJson("/api/school-years/{$this->year->id}", [
        'name' => '2026/2027',
        'start_date' => '2027-07-01',
        'end_date' => '2026-06-30',
    ])->assertStatus(422);

    $this->putJson("/api/school-years/{$this->year->id}", [
        'name' => '2026/2027 Revised',
        'start_date' => '2026-07-15',
        'end_date' => '2027-06-30',
    ])->assertStatus(200)
        ->assertJsonPath('data.name', '2026/2027 Revised')
        ->assertJsonPath('data.start_date', '2026-07-15');

    $this->year->refresh();
    expect($this->year->start_date->toDateString())->toBe('2026-07-15')
        ->and($this->year->locked_at)->toBeNull();
});

test('active, locked, and referenced school years cannot be deleted', function () {
    Sanctum::actingAs($this->operator);

    AcademicSetting::find(1)->update(['active_school_year_id' => $this->year->id]);

    $this->deleteJson("/api/school-years/{$this->year->id}", ['delete' => 'true'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The active school year cannot be deleted. Set another active period first.');

    AcademicSetting::find(1)->update(['active_school_year_id' => null]);
    $this->year->update(['locked_at' => now(), 'locked_by' => $this->operator->id, 'lock_reason' => 'Closed']);

    $this->deleteJson("/api/school-years/{$this->year->id}", ['delete' => 'true'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'A locked school year cannot be deleted. Unlock it first.');

    $this->year->update(['locked_at' => null, 'locked_by' => null, 'lock_reason' => null]);

    $this->deleteJson("/api/school-years/{$this->year->id}", ['delete' => 'true'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This record is still being referenced by 1 Class(es).');

    // Soft-deleted classes still block deletion of the year.
    $this->rombel->delete();
    $this->deleteJson("/api/school-years/{$this->year->id}", ['delete' => 'true'])
        ->assertStatus(422);

    $emptyYear = SchoolYear::create(['name' => '2022/2023']);
    $this->deleteJson("/api/school-years/{$emptyYear->id}", ['delete' => 'true'])
        ->assertStatus(200);
    expect(SchoolYear::find($emptyYear->id))->toBeNull();
});
