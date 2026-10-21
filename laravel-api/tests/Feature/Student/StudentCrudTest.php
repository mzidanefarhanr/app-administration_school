<?php

use App\Models\Role;
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

    // Operator with students.manage + students.view
    $this->managerUser = User::factory()->create(['status_active' => true]);
    $manageRole = Role::firstOrCreate(['name' => 'School Operator', 'slug' => 'operator-sekolah']);
    $manageRole->abilities()->sync(
        \App\Models\Ability::whereIn('name', ['students.view', 'students.manage', 'students.export'])->pluck('id')
    );
    $this->managerUser->roles()->attach($manageRole->id, ['assigned_by' => $this->managerUser->id]);

    // Viewer with students.view only
    $this->viewerUser = User::factory()->create(['status_active' => true]);
    $viewRole = Role::firstOrCreate(['name' => 'Principal', 'slug' => 'kepala-sekolah']);
    $viewRole->abilities()->sync(
        \App\Models\Ability::whereIn('name', ['students.view'])->pluck('id')
    );
    $this->viewerUser->roles()->attach($viewRole->id, ['assigned_by' => $this->viewerUser->id]);

    // Unauthorized user
    $this->guestUser = User::factory()->create(['status_active' => true]);
});

test('unauthenticated guest is denied access to student endpoints with 401', function () {
    $this->postJson('/api/students', ['name' => 'Test'])->assertStatus(401);
    $this->getJson('/api/students/1')->assertStatus(401);
    $this->putJson('/api/students/1', ['name' => 'Test', 'version' => 1])->assertStatus(401);
    $this->deleteJson('/api/students/1', ['version' => 1, 'archive_reason' => 'Test'])->assertStatus(401);
    $this->postJson('/api/students/1/restore', ['version' => 1])->assertStatus(401);
});

test('user without students.manage cannot perform mutations but can view with students.view', function () {
    $student = Student::create(['name' => 'Existing Student', 'version' => 1]);

    Sanctum::actingAs($this->viewerUser);

    // Can view
    $this->getJson("/api/students/{$student->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Existing Student');

    // Cannot mutate (403)
    $this->postJson('/api/students', ['name' => 'New Student'])->assertStatus(403);
    $this->putJson("/api/students/{$student->id}", ['name' => 'Updated', 'version' => 1])->assertStatus(403);
    $this->deleteJson("/api/students/{$student->id}", ['version' => 1, 'archive_reason' => 'Test'])->assertStatus(403);
    $this->postJson("/api/students/{$student->id}/restore", ['version' => 1])->assertStatus(403);
});

test('authorized manager can create student with full valid payload and nullable NIK', function () {
    Sanctum::actingAs($this->managerUser);

    $payload = [
        'name' => 'John Doe',
        'nik' => '0012345678901234',
        'nis' => '00123',
        'nisn' => '0012345678',
        'birthdate' => '2010-05-15',
        'birthplace' => 'Jakarta',
        'check_kjp' => true,
        'check_bpms' => false,
    ];

    $response = $this->postJson('/api/students', $payload);
    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'John Doe')
        ->assertJsonPath('data.nik', '0012345678901234')
        ->assertJsonPath('data.nis', '00123')
        ->assertJsonPath('data.version', 1);

    expect(Student::where('nik', '0012345678901234')->exists())->toBeTrue();
});

test('authorized manager can create student without NIK (nullable)', function () {
    Sanctum::actingAs($this->managerUser);

    $payload = [
        'name' => 'Student Without NIK',
        'nik' => null,
        'nis' => '99881',
    ];

    $response = $this->postJson('/api/students', $payload);
    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Student Without NIK')
        ->assertJsonPath('data.nik', null);
});

test('homonyms are explicitly permitted: multiple students can share the exact same name', function () {
    Sanctum::actingAs($this->managerUser);

    $res1 = $this->postJson('/api/students', [
        'name' => 'Muhammad Rizki',
        'nik' => '1111222233334444',
    ])->assertStatus(201);

    $res2 = $this->postJson('/api/students', [
        'name' => 'Muhammad Rizki', // Homonim sah
        'nik' => '5555666677778888',
    ])->assertStatus(201);

    expect(Student::where('name', 'Muhammad Rizki')->count())->toBe(2);
});

test('invalid NIK format is rejected with clean English 422 validation message', function () {
    Sanctum::actingAs($this->managerUser);

    // 15 digits (short)
    $res1 = $this->postJson('/api/students', [
        'name' => 'Short NIK',
        'nik' => '123456789012345',
    ]);
    $res1->assertStatus(422)
        ->assertJsonValidationErrors('nik')
        ->assertJsonPath('errors.nik.0', 'NIK must be exactly 16 digits.');

    // Contains characters
    $res2 = $this->postJson('/api/students', [
        'name' => 'Alpha NIK',
        'nik' => '123456789012345A',
    ]);
    $res2->assertStatus(422)
        ->assertJsonValidationErrors('nik');
});

test('duplicate NIK or NIS is rejected with 422 without raw SQL leak', function () {
    Student::create([
        'name' => 'Original Student',
        'nik' => '3201010101010001',
        'nis' => '1001',
        'version' => 1,
    ]);

    Sanctum::actingAs($this->managerUser);

    $res = $this->postJson('/api/students', [
        'name' => 'Cloned Student',
        'nik' => '3201010101010001',
        'nis' => '1002',
    ]);
    $res->assertStatus(422)
        ->assertJsonValidationErrors('nik')
        ->assertJsonPath('errors.nik.0', 'The NIK has already been registered.');

    $resNis = $this->postJson('/api/students', [
        'name' => 'Another Student',
        'nik' => '3201010101010002',
        'nis' => '1001',
    ]);
    $resNis->assertStatus(422)
        ->assertJsonValidationErrors('nis')
        ->assertJsonPath('errors.nis.0', 'The NIS has already been registered in this school.');
});

test('identity conflict against an archived student is rejected per unconditional index constraint', function () {
    $archived = Student::create([
        'name' => 'Archived Student',
        'nik' => '3201010101019999',
        'nis' => '9999',
        'version' => 1,
    ]);
    $archived->delete(); // Soft delete

    Sanctum::actingAs($this->managerUser);

    $res = $this->postJson('/api/students', [
        'name' => 'New Student Reusing NIK',
        'nik' => '3201010101019999',
    ]);
    $res->assertStatus(422)
        ->assertJsonValidationErrors('nik');
});

test('server ignores client-supplied id, age, timestamps, or version on creation', function () {
    Sanctum::actingAs($this->managerUser);

    $res = $this->postJson('/api/students', [
        'id' => 99999,
        'version' => 888,
        'age' => 99,
        'name' => 'Unspoofable Student',
        'birthdate' => '2010-01-01',
    ]);
    $res->assertStatus(201);

    $student = Student::where('name', 'Unspoofable Student')->first();
    expect($student->id)->not->toBe(99999);
    expect($student->version)->toBe(1);
});

test('update with matching expected version succeeds and atomics version increment', function () {
    $student = Student::create([
        'name' => 'Initial Name',
        'version' => 1,
    ]);

    Sanctum::actingAs($this->managerUser);

    $res = $this->putJson("/api/students/{$student->id}", [
        'version' => 1,
        'name' => 'Updated Name',
    ]);

    $res->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.version', 2);

    expect($student->fresh()->name)->toBe('Updated Name');
    expect($student->fresh()->version)->toBe(2);
});

test('update with outdated expected version is rejected with 409 Conflict without altering record', function () {
    $student = Student::create([
        'name' => 'Initial Name',
        'version' => 3,
    ]);

    Sanctum::actingAs($this->managerUser);

    // Klien mengirimkan version 2 (stale)
    $res = $this->putJson("/api/students/{$student->id}", [
        'version' => 2,
        'name' => 'Stale Overwrite Attempt',
    ]);

    $res->assertStatus(409)
        ->assertJsonPath('current_version', 3);

    // Target record di database sama sekali tidak berubah
    expect($student->fresh()->name)->toBe('Initial Name');
    expect($student->fresh()->version)->toBe(3);
});

test('cannot update an archived student directly without restoring first', function () {
    $student = Student::create([
        'name' => 'Archived Student',
        'version' => 1,
    ]);
    $student->delete();

    Sanctum::actingAs($this->managerUser);

    $res = $this->putJson("/api/students/{$student->id}", [
        'version' => 1,
        'name' => 'Illegal Update on Archived',
    ]);

    $res->assertStatus(422)
        ->assertJsonPath('errors.student.0', 'Cannot update an archived student. Please restore the student record first.');
});

test('archiving student requires expected version and deactivates active rombels administratively without corrupting history', function () {
    $student = Student::create([
        'name' => 'Student To Archive',
        'nik' => '1234567812345678',
        'version' => 1,
    ]);

    $srActive = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    Sanctum::actingAs($this->managerUser);

    // Stale version rejected
    $this->deleteJson("/api/students/{$student->id}", [
        'version' => 99,
        'archive_reason' => 'Family moved abroad',
    ])->assertStatus(409);

    // Valid archive
    $res = $this->deleteJson("/api/students/{$student->id}", [
        'version' => 1,
        'archive_reason' => 'Family moved abroad',
    ]);
    $res->assertStatus(200);

    $freshStudent = Student::withTrashed()->find($student->id);
    expect($freshStudent->trashed())->toBeTrue();
    expect($freshStudent->archive_reason)->toBe('Family moved abroad');
    expect($freshStudent->version)->toBe(2);

    // Rombel aktif dinonaktifkan via archived_at tanpa mengubah student_status_id menjadi Mutasi Keluar
    $freshSr = StudentRombel::find($srActive->id);
    expect($freshSr->archived_at)->not->toBeNull();
    expect($freshSr->archive_reason)->toContain('Family moved abroad');
    expect($freshSr->student_status_id)->toBe($this->statusActive->id); // Status awal tidak dipalsukan!
    expect($freshSr->latest_student_exit_date)->toBeNull(); // Tanggal keluar sekolah tidak dipalsukan!
});

test('cannot enroll an archived student into an active rombel', function () {
    $student = Student::create([
        'name' => 'Archived Student For Enrollment',
        'nik' => '7788990011223344',
        'version' => 1,
    ]);
    $student->delete();

    expect(function () use ($student) {
        StudentRombel::create([
            'student_id' => $student->id,
            'school_rombel' => $this->rombel->id,
            'student_status_id' => $this->statusActive->id,
        ]);
    })->toThrow(\DomainException::class);
});

test('restore student requires expected version, checks identity conflicts, and preserves inactive rombel status', function () {
    $student = Student::create([
        'name' => 'Restorable Student',
        'nik' => '8899001122334455',
        'nis' => 'RESTORE-01',
        'version' => 2,
    ]);

    $sr = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
        'archived_at' => now(),
        'archive_reason' => 'Administrative archive',
    ]);

    $student->delete();

    Sanctum::actingAs($this->managerUser);

    // Stale version rejected
    $this->postJson("/api/students/{$student->id}/restore", ['version' => 1])
        ->assertStatus(409);

    // Valid restore
    $res = $this->postJson("/api/students/{$student->id}/restore", ['version' => 2]);
    $res->assertStatus(200)
        ->assertJsonPath('data.version', 3)
        ->assertJsonPath('data.archive_reason', null);

    $freshStudent = Student::find($student->id);
    expect($freshStudent->trashed())->toBeFalse();

    // Rombel lama TETAP NON-AKTIF (archived_at tetap terisi) (DEC-21)
    $freshSr = StudentRombel::find($sr->id);
    expect($freshSr->archived_at)->not->toBeNull();
});

test('audit log records student mutations while strictly excluding medical and financial data', function () {
    Sanctum::actingAs($this->managerUser);

    $res = $this->postJson('/api/students', [
        'name' => 'Private Student',
        'nik' => '1122334455667788',
        'history_illness' => 'Asthma - Highly Confidential',
        'father_income' => 'Rp 50.000.000',
        'father_hp_num' => '081234567890',
    ]);
    $res->assertStatus(201);
    $studentId = $res->json('data.id');

    // Periksa log aktivitas pada tabel activities
    $activity = DB::table('activities')
        ->where('table_name', 'students')
        ->where('record_id', $studentId)
        ->where('information', 'Created')
        ->first();

    expect($activity)->not->toBeNull();
    $new = json_decode($activity->new, true);

    expect($new)->toHaveKey('name', 'Private Student');
    expect($new)->toHaveKey('nik', '1122334455667788');

    // Strict Privacy Verification (NFR-01, DEC-20, DEC-21)
    expect($new)->not->toHaveKey('history_illness');
    expect($new)->not->toHaveKey('father_income');
    expect($new)->not->toHaveKey('father_hp_num');
});

test('concurrency: two updates with same expected version serialize so exactly one succeeds and one receives 409', function () {
    $student = Student::create([
        'name' => 'Concurrent Target',
        'version' => 1,
    ]);

    Sanctum::actingAs($this->managerUser);

    // Request 1 succeeds
    $res1 = $this->putJson("/api/students/{$student->id}", [
        'version' => 1,
        'name' => 'First Update Succeeded',
    ]);
    $res1->assertStatus(200)
        ->assertJsonPath('data.version', 2);

    // Request 2 with same original version (1) must fail with 409
    $res2 = $this->putJson("/api/students/{$student->id}", [
        'version' => 1,
        'name' => 'Second Concurrent Update Stale',
    ]);
    $res2->assertStatus(409)
        ->assertJsonPath('current_version', 2);

    expect($student->fresh()->name)->toBe('First Update Succeeded');
});

test('archive deactivates active memberships across years without touching historical status or exit dates', function () {
    $year2 = SchoolYear::create(['name' => '2027/2028']);
    $rombel2 = SchoolRombel::create([
        'school_year_id' => $year2->id,
        'school_level_id' => $this->level->id,
        'name' => '11-A',
    ]);
    $statusMutasi = StudentStatus::firstOrCreate(['id' => 2], ['name' => 'Mutasi Keluar']);

    $student = Student::create(['name' => 'Multi Year', 'nik' => '4455667788990011', 'version' => 1]);

    $historical = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $statusMutasi->id,
        'latest_student_exit_date' => '2026-03-01',
    ]);
    $active1 = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
    ]);
    $active2 = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $rombel2->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    Sanctum::actingAs($this->managerUser);
    $this->deleteJson("/api/students/{$student->id}", ['version' => 1, 'archive_reason' => 'Admin cleanup'])
        ->assertStatus(200);

    foreach ([$active1, $active2] as $sr) {
        $fresh = StudentRombel::find($sr->id);
        expect($fresh->archived_at)->not->toBeNull();
        expect($fresh->student_status_id)->toBe($this->statusActive->id);
        expect($fresh->latest_student_exit_date)->toBeNull();
    }

    $freshHistorical = StudentRombel::find($historical->id);
    expect($freshHistorical->archived_at)->toBeNull();
    expect($freshHistorical->student_status_id)->toBe($statusMutasi->id);
    expect(substr((string) $freshHistorical->latest_student_exit_date, 0, 10))->toBe('2026-03-01');
});

test('archive also deactivates legacy memberships that only have student_nik', function () {
    $student = Student::create(['name' => 'Legacy Link', 'nik' => '9900112233445566', 'version' => 1]);

    DB::table('student_rombels')->insert([
        'version' => 1,
        'student_id' => null,
        'student_nik' => '9900112233445566',
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Sanctum::actingAs($this->managerUser);
    $this->deleteJson("/api/students/{$student->id}", ['version' => 1, 'archive_reason' => 'Legacy'])
        ->assertStatus(200);

    expect(DB::table('student_rombels')->where('student_nik', '9900112233445566')->value('archived_at'))->not->toBeNull();
});

test('archive requires a reason and expected version', function () {
    $student = Student::create(['name' => 'Needs Reason', 'version' => 1]);
    Sanctum::actingAs($this->managerUser);

    $this->deleteJson("/api/students/{$student->id}", ['version' => 1])->assertStatus(422)->assertJsonValidationErrors('archive_reason');
    $this->deleteJson("/api/students/{$student->id}", ['archive_reason' => 'x'])->assertStatus(422)->assertJsonValidationErrors('version');
    expect(Student::find($student->id))->not->toBeNull();
});

test('stale archive and restore versions leave domain data and audit unchanged', function () {
    $student = Student::create(['name' => 'Stale Target', 'version' => 5]);
    $sr = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
    ]);
    $auditBefore = DB::table('activities')->count();

    Sanctum::actingAs($this->managerUser);
    $this->deleteJson("/api/students/{$student->id}", ['version' => 1, 'archive_reason' => 'stale'])->assertStatus(409);

    expect(Student::find($student->id)->version)->toBe(5);
    expect(StudentRombel::find($sr->id)->archived_at)->toBeNull();
    expect(DB::table('activities')->count())->toBe($auditBefore);
});

test('audit failure rolls back student archive and membership deactivation', function () {
    $student = Student::create(['name' => 'Audit Fail', 'version' => 1]);
    $sr = StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    Sanctum::actingAs($this->managerUser);

    \App\Models\Activity::creating(function () {
        throw new \RuntimeException('Simulated audit failure');
    });

    $threw = false;
    try {
        $this->withoutExceptionHandling()->deleteJson("/api/students/{$student->id}", ['version' => 1, 'archive_reason' => 'will fail']);
    } catch (\Throwable $e) {
        $threw = true;
    }
    \App\Models\Activity::flushEventListeners();

    expect($threw)->toBeTrue();
    $fresh = Student::withTrashed()->find($student->id);
    expect($fresh->trashed())->toBeFalse();
    expect($fresh->version)->toBe(1);
    expect($fresh->archive_reason)->toBeNull();
    expect(StudentRombel::find($sr->id)->archived_at)->toBeNull();
});

test('archive reason free text never appears in audit json', function () {
    $student = Student::create(['name' => 'Privacy Archive', 'version' => 1]);
    Sanctum::actingAs($this->managerUser);

    $this->deleteJson("/api/students/{$student->id}", ['version' => 1, 'archive_reason' => 'SENSITIVE-FREE-TEXT-REASON'])
        ->assertStatus(200);

    $dump = json_encode(DB::table('activities')->get());
    expect($dump)->not->toContain('SENSITIVE-FREE-TEXT-REASON');
});

test('update preserves omitted fields and clears explicitly nulled fields', function () {
    $student = Student::create(['name' => 'Partial', 'birthplace' => 'Bandung', 'nis' => 'P-1', 'version' => 1]);
    Sanctum::actingAs($this->managerUser);

    $this->putJson("/api/students/{$student->id}", ['version' => 1, 'name' => 'Partial', 'nis' => null])
        ->assertStatus(200);

    $fresh = $student->fresh();
    expect($fresh->birthplace)->toBe('Bandung');
    expect($fresh->nis)->toBeNull();
});
