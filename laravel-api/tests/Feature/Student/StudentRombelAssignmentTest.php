<?php

use App\Models\Activity;
use App\Models\Role;
use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->year = SchoolYear::create(['name' => '2026/2027']);
    $this->otherYear = SchoolYear::create(['name' => '2025/2026']);
    $this->level = SchoolLevel::create(['name' => 'Grade 10']);

    $this->rombelA = SchoolRombel::create([
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'name' => '10-A',
    ]);

    $this->rombelB = SchoolRombel::create([
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'name' => '10-B',
    ]);

    $this->rombelOtherYear = SchoolRombel::create([
        'school_year_id' => $this->otherYear->id,
        'school_level_id' => $this->level->id,
        'name' => '9-A',
    ]);

    $this->statusActive = StudentStatus::firstOrCreate(['id' => 1], ['name' => 'Aktif']);

    // Operator with students.manage + students.view
    $this->operator = User::factory()->create(['status_active' => true]);
    $opRole = Role::firstOrCreate(['name' => 'School Operator', 'slug' => 'operator-sekolah']);
    $opRole->abilities()->sync(
        \App\Models\Ability::whereIn('name', ['students.view', 'students.manage'])->pluck('id')
    );
    $this->operator->roles()->attach($opRole->id, ['assigned_by' => $this->operator->id]);

    // Viewer with students.view only (Principal)
    $this->viewer = User::factory()->create(['status_active' => true]);
    $viewRole = Role::firstOrCreate(['name' => 'Principal', 'slug' => 'kepala-sekolah']);
    $viewRole->abilities()->sync(
        \App\Models\Ability::whereIn('name', ['students.view'])->pluck('id')
    );
    $this->viewer->roles()->attach($viewRole->id, ['assigned_by' => $this->viewer->id]);
});

test('unauthenticated guest is denied assign and transfer with 401', function () {
    $student = Student::create(['name' => 'Guest Target', 'version' => 1]);

    $this->postJson("/api/students/{$student->id}/assign-rombel", [
        'school_year_id' => $this->year->id,
        'school_rombel_id' => $this->rombelA->id,
        'expected_student_version' => 1,
    ])->assertStatus(401);

    $this->postJson("/api/students/{$student->id}/transfer-rombel", [
        'school_year_id' => $this->year->id,
        'target_rombel_id' => $this->rombelB->id,
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(401);
});

test('user without students.manage is denied assign and transfer with 403', function () {
    $student = Student::create(['name' => 'Viewer Target', 'version' => 1]);
    Sanctum::actingAs($this->viewer);

    $this->postJson("/api/students/{$student->id}/assign-rombel", [
        'school_year_id' => $this->year->id,
        'school_rombel_id' => $this->rombelA->id,
        'expected_student_version' => 1,
    ])->assertStatus(403);

    $this->postJson("/api/students/{$student->id}/transfer-rombel", [
        'school_year_id' => $this->year->id,
        'target_rombel_id' => $this->rombelB->id,
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ])->assertStatus(403);
});

test('authorized operator can assign unplaced student to rombel in selected academic year', function () {
    $student = Student::create(['name' => 'Unplaced Student', 'nik' => '9000000000000010', 'version' => 1]);
    Sanctum::actingAs($this->operator);

    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $response = $this->postJson("/api/students/{$student->id}/assign-rombel", [
        'school_year_id' => $this->year->id,
        'school_rombel_id' => $this->rombelA->id,
        'expected_student_version' => 1,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.student_id', $student->id)
        ->assertJsonPath('data.school_rombel_id', $this->rombelA->id)
        ->assertJsonPath('data.school_rombel_name', '10-A')
        ->assertJsonPath('data.student_version', 2);

    $student->refresh();
    expect($student->version)->toBe(2);

    $membership = StudentRombel::where('student_id', $student->id)->first();
    expect($membership)->not->toBeNull()
        ->and($membership->school_rombel)->toBe($this->rombelA->id)
        ->and($membership->student_status_id)->toBe(1)
        ->and($membership->latest_student_entry_date)->toBe($today)
        ->and($membership->version)->toBe(1)
        ->and($membership->student_nik)->toBe('9000000000000010');

    // Audit log verification: trait Loggable records student_rombels mutation
    $audit = Activity::where('table_name', 'student_rombels')
        ->where('record_id', $membership->id)
        ->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($this->operator->id);
});

test('assigning rombel to archived student is rejected with 422', function () {
    $student = Student::create(['name' => 'Archived Student', 'version' => 1]);
    $student->delete(); // soft delete

    Sanctum::actingAs($this->operator);

    $response = $this->postJson("/api/students/{$student->id}/assign-rombel", [
        'school_year_id' => $this->year->id,
        'school_rombel_id' => $this->rombelA->id,
        'expected_student_version' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Cannot assign rombel to an archived student.');

    expect(StudentRombel::where('student_id', $student->id)->count())->toBe(0);
});

test('assigning rombel belonging to different academic year is rejected with 422', function () {
    $student = Student::create(['name' => 'Cross Year Student', 'version' => 1]);
    Sanctum::actingAs($this->operator);

    $response = $this->postJson("/api/students/{$student->id}/assign-rombel", [
        'school_year_id' => $this->year->id,
        'school_rombel_id' => $this->rombelOtherYear->id, // belongs to otherYear!
        'expected_student_version' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['school_rombel_id']);
});

test('assigning with stale expected_student_version is rejected with 409 conflict', function () {
    $student = Student::create(['name' => 'Version Test Student', 'version' => 2]);
    Sanctum::actingAs($this->operator);

    $response = $this->postJson("/api/students/{$student->id}/assign-rombel", [
        'school_year_id' => $this->year->id,
        'school_rombel_id' => $this->rombelA->id,
        'expected_student_version' => 1, // outdated version!
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('current_version', 2);

    expect(StudentRombel::where('student_id', $student->id)->count())->toBe(0);
});

test('assigning student who already has active rombel in same academic year is rejected with 409 conflict', function () {
    $student = Student::create(['name' => 'Already Placed Student', 'version' => 1]);
    StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombelA->id,
        'student_status_id' => 1,
        'version' => 1,
    ]);

    Sanctum::actingAs($this->operator);

    $response = $this->postJson("/api/students/{$student->id}/assign-rombel", [
        'school_year_id' => $this->year->id,
        'school_rombel_id' => $this->rombelB->id,
        'expected_student_version' => 1,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Conflict: Student already has an active rombel membership in this academic year.');
});

test('authorized operator can transfer placed student to another rombel within the same academic year', function () {
    $student = Student::create(['name' => 'Transfer Student', 'version' => 1]);
    $oldMembership = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombelA->id,
        'student_status_id' => 1,
        'latest_student_entry_date' => '2026-07-15',
        'version' => 1,
    ]);

    Sanctum::actingAs($this->operator);

    $today = Carbon::now('Asia/Jakarta')->toDateString();

    $response = $this->postJson("/api/students/{$student->id}/transfer-rombel", [
        'school_year_id' => $this->year->id,
        'target_rombel_id' => $this->rombelB->id,
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
        'reason' => 'Class balancing transfer',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.student_id', $student->id)
        ->assertJsonPath('data.previous_rombel_id', $this->rombelA->id)
        ->assertJsonPath('data.new_rombel_id', $this->rombelB->id)
        ->assertJsonPath('data.new_rombel_name', '10-B')
        ->assertJsonPath('data.student_version', 2);

    $student->refresh();
    expect($student->version)->toBe(2);

    // Old membership is closed with exit_date = today, version incremented, status preserved (DEC-23 #5)
    $oldMembership->refresh();
    expect($oldMembership->latest_student_exit_date)->toBe($today)
        ->and($oldMembership->student_status_id)->toBe(1) // NOT Mutasi Keluar!
        ->and($oldMembership->latest_information)->toBe('Transfer: Class balancing transfer')
        ->and($oldMembership->version)->toBe(2);

    // New membership is created active
    $newMembership = StudentRombel::where('student_id', $student->id)
        ->where('school_rombel', $this->rombelB->id)
        ->first();

    expect($newMembership)->not->toBeNull()
        ->and($newMembership->latest_student_entry_date)->toBe($today)
        ->and($newMembership->latest_student_exit_date)->toBeNull()
        ->and($newMembership->student_status_id)->toBe(1)
        ->and($newMembership->version)->toBe(1);
});

test('transfer to identical target rombel is rejected with 422', function () {
    $student = Student::create(['name' => 'Same Rombel Student', 'version' => 1]);
    StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombelA->id,
        'student_status_id' => 1,
        'version' => 1,
    ]);

    Sanctum::actingAs($this->operator);

    $response = $this->postJson("/api/students/{$student->id}/transfer-rombel", [
        'school_year_id' => $this->year->id,
        'target_rombel_id' => $this->rombelA->id, // identical!
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Target rombel must be different from current rombel.');
});

test('transfer to rombel from different academic year is rejected with 422', function () {
    $student = Student::create(['name' => 'Cross Year Transfer', 'version' => 1]);
    StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombelA->id,
        'student_status_id' => 1,
        'version' => 1,
    ]);

    Sanctum::actingAs($this->operator);

    $response = $this->postJson("/api/students/{$student->id}/transfer-rombel", [
        'school_year_id' => $this->year->id,
        'target_rombel_id' => $this->rombelOtherYear->id, // belongs to otherYear!
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['target_rombel_id']);
});

test('transfer with stale expected_student_version is rejected with 409 conflict', function () {
    $student = Student::create(['name' => 'Stale Student Version Transfer', 'version' => 2]);
    StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombelA->id,
        'student_status_id' => 1,
        'version' => 1,
    ]);

    Sanctum::actingAs($this->operator);

    $response = $this->postJson("/api/students/{$student->id}/transfer-rombel", [
        'school_year_id' => $this->year->id,
        'target_rombel_id' => $this->rombelB->id,
        'expected_student_version' => 1, // outdated version!
        'expected_membership_version' => 1,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('current_version', 2);
});

test('transfer with stale expected_membership_version is rejected with 409 conflict', function () {
    $student = Student::create(['name' => 'Stale Membership Version Transfer', 'version' => 1]);
    StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombelA->id,
        'student_status_id' => 1,
        'version' => 3, // actual version is 3
    ]);

    Sanctum::actingAs($this->operator);

    $response = $this->postJson("/api/students/{$student->id}/transfer-rombel", [
        'school_year_id' => $this->year->id,
        'target_rombel_id' => $this->rombelB->id,
        'expected_student_version' => 1,
        'expected_membership_version' => 1, // outdated membership version!
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('current_membership_version', 3);
});

test('transfer for unplaced student is rejected with 422', function () {
    $student = Student::create(['name' => 'Unplaced Student', 'version' => 1]);
    Sanctum::actingAs($this->operator);

    $response = $this->postJson("/api/students/{$student->id}/transfer-rombel", [
        'school_year_id' => $this->year->id,
        'target_rombel_id' => $this->rombelB->id,
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Student has no active rombel membership in the selected academic year to transfer from.');
});

test('transfer for archived student is rejected with 422', function () {
    $student = Student::create(['name' => 'Archived Student Transfer', 'version' => 1]);
    $student->delete(); // archived

    Sanctum::actingAs($this->operator);

    $response = $this->postJson("/api/students/{$student->id}/transfer-rombel", [
        'school_year_id' => $this->year->id,
        'target_rombel_id' => $this->rombelB->id,
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Cannot transfer rombel for an archived student.');
});
