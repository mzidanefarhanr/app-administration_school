<?php

use App\Models\SchoolLevel;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
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
    $this->plainUser = $this->makeUserWithRole();

    $this->year = SchoolYear::create(['name' => '2026/2027']);
    $this->level = SchoolLevel::create(['name' => 'Grade 10']);
});

test('guest or unauthorized user cannot perform rombel batch delete', function () {
    $this->postJson('/api/school-rombels/batch-delete', [
        'items' => [['id' => 1, 'expected_version' => 1]],
    ])->assertStatus(401);

    Sanctum::actingAs($this->plainUser);
    $this->postJson('/api/school-rombels/batch-delete', [
        'items' => [['id' => 1, 'expected_version' => 1]],
    ])->assertStatus(403);
});

test('batch delete deletes multiple empty rombels atomically', function () {
    Sanctum::actingAs($this->operator);

    $r1 = SchoolRombel::create([
        'name' => '10-A',
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'version' => 1,
    ]);

    $r2 = SchoolRombel::create([
        'name' => '10-B',
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'version' => 1,
    ]);

    $response = $this->postJson('/api/school-rombels/batch-delete', [
        'items' => [
            ['id' => $r1->id, 'expected_version' => 1],
            ['id' => $r2->id, 'expected_version' => 1],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Successfully deleted 2 class(es).',
            'data' => [
                'deleted_count' => 2,
            ],
        ]);

    $this->assertSoftDeleted('school_rombels', ['id' => $r1->id]);
    $this->assertSoftDeleted('school_rombels', ['id' => $r2->id]);
});

test('batch delete fails and rolls back when a rombel has student memberships', function () {
    Sanctum::actingAs($this->operator);

    $r1 = SchoolRombel::create([
        'name' => '10-A',
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'version' => 1,
    ]);

    $r2 = SchoolRombel::create([
        'name' => '10-B',
        'school_year_id' => $this->year->id,
        'school_level_id' => $this->level->id,
        'version' => 1,
    ]);

    $student = Student::create(['name' => 'Siswa Aktif', 'version' => 1]);
    $status = StudentStatus::firstOrCreate(['id' => 1], ['name' => 'Aktif']);

    StudentRombel::create([
        'student_id' => $student->id,
        'school_rombel' => $r2->id,
        'student_status_id' => $status->id,
    ]);

    $response = $this->postJson('/api/school-rombels/batch-delete', [
        'items' => [
            ['id' => $r1->id, 'expected_version' => 1],
            ['id' => $r2->id, 'expected_version' => 1], // has student membership
        ],
    ]);

    $response->assertStatus(422);

    $this->assertDatabaseHas('school_rombels', ['id' => $r1->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('school_rombels', ['id' => $r2->id, 'deleted_at' => null]);
});
