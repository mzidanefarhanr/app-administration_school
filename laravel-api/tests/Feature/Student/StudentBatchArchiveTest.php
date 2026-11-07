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

    $this->managerUser = User::factory()->create(['status_active' => true]);
    $manageRole = Role::firstOrCreate(['name' => 'School Operator', 'slug' => 'operator-sekolah']);
    $manageRole->abilities()->sync(
        \App\Models\Ability::whereIn('name', ['students.view', 'students.manage'])->pluck('id')
    );
    $this->managerUser->roles()->attach($manageRole->id, ['assigned_by' => $this->managerUser->id]);

    $this->guestUser = User::factory()->create(['status_active' => true]);
});

test('batch archive requires students.manage and authentication', function () {
    $this->postJson('/api/students/batch-archive', [
        'items' => [['id' => 1, 'version' => 1]],
        'archive_reason' => 'Alasan arsip valid',
    ])->assertStatus(401);

    Sanctum::actingAs($this->guestUser);
    $this->postJson('/api/students/batch-archive', [
        'items' => [['id' => 1, 'version' => 1]],
        'archive_reason' => 'Alasan arsip valid',
    ])->assertStatus(403);
});

test('batch archive successfully archives multiple students and deactivates active rombels', function () {
    Sanctum::actingAs($this->managerUser);

    $s1 = Student::create(['name' => 'Siswa 1', 'version' => 1]);
    $s2 = Student::create(['name' => 'Siswa 2', 'version' => 1]);

    $sr = StudentRombel::create([
        'student_id' => $s1->id,
        'school_rombel' => $this->rombel->id,
        'student_status_id' => $this->statusActive->id,
    ]);

    $response = $this->postJson('/api/students/batch-archive', [
        'items' => [
            ['id' => $s1->id, 'version' => 1],
            ['id' => $s2->id, 'version' => 1],
        ],
        'archive_reason' => 'Pengarsipan massal kelulusan siswa',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Successfully archived 2 student(s).',
            'count' => 2,
        ]);

    $this->assertSoftDeleted('students', ['id' => $s1->id]);
    $this->assertSoftDeleted('students', ['id' => $s2->id]);

    $s1Fresh = Student::withTrashed()->find($s1->id);
    expect($s1Fresh->version)->toBe(2);
    expect($s1Fresh->archive_reason)->toBe('Pengarsipan massal kelulusan siswa');

    $srFresh = $sr->fresh();
    expect($srFresh->archived_at)->not->toBeNull();
    expect($srFresh->archive_reason)->toContain('Pengarsipan massal kelulusan siswa');
});

test('batch archive rolls back entirely when a version conflict occurs', function () {
    Sanctum::actingAs($this->managerUser);

    $s1 = Student::create(['name' => 'Siswa 1', 'version' => 1]);
    $s2 = Student::create(['name' => 'Siswa 2', 'version' => 5]); // stored version 5

    $response = $this->postJson('/api/students/batch-archive', [
        'items' => [
            ['id' => $s1->id, 'version' => 1],
            ['id' => $s2->id, 'version' => 1], // outdated version 1
        ],
        'archive_reason' => 'Pengarsipan massal siswa',
    ]);

    $response->assertStatus(409);

    // Rollback atomic: neither student should be deleted
    $this->assertDatabaseHas('students', ['id' => $s1->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('students', ['id' => $s2->id, 'deleted_at' => null]);
});

test('batch restore successfully restores multiple archived students', function () {
    Sanctum::actingAs($this->managerUser);

    $s1 = Student::create(['name' => 'Siswa 1', 'version' => 2, 'archive_reason' => 'Arsip']);
    $s1->delete();

    $s2 = Student::create(['name' => 'Siswa 2', 'version' => 3, 'archive_reason' => 'Arsip']);
    $s2->delete();

    $response = $this->postJson('/api/students/batch-restore', [
        'items' => [
            ['id' => $s1->id, 'version' => 2],
            ['id' => $s2->id, 'version' => 3],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Successfully restored 2 student(s).',
            'count' => 2,
        ]);

    $this->assertDatabaseHas('students', ['id' => $s1->id, 'deleted_at' => null, 'version' => 3]);
    $this->assertDatabaseHas('students', ['id' => $s2->id, 'deleted_at' => null, 'version' => 4]);
});
