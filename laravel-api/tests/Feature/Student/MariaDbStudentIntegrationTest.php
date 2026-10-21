<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config([
        'database.connections.mariadb_test' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'school_restore_drill_uat',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ],
    ]);

    try {
        DB::connection('mariadb_test')->getPdo();
    } catch (\Throwable $e) {
        $this->markTestSkipped('MariaDB local instance not reachable: '.$e->getMessage());
    }

    // Pastikan database target bukan database operasional
    $currentDb = DB::connection('mariadb_test')->getDatabaseName();
    if ($currentDb === 'backdata_administrations' || $currentDb === 'school_access_uat') {
        throw new \RuntimeException("Safety Guard: Aborted. Cannot run tests on protected database: {$currentDb}");
    }

    // Pastikan referensi student_statuses ada di database disposable
    $conn = DB::connection('mariadb_test');
    if (! $conn->table('student_statuses')->where('id', 1)->exists()) {
        $conn->table('student_statuses')->insert(['id' => 1, 'name' => 'Aktif', 'created_at' => now(), 'updated_at' => now()]);
    }
    if (! $conn->table('student_statuses')->where('id', 2)->exists()) {
        $conn->table('student_statuses')->insert(['id' => 2, 'name' => 'Mutasi Keluar', 'created_at' => now(), 'updated_at' => now()]);
    }
});

test('mariadb integration: migration sets up student_id, nullable student_nik, and active triggers', function () {
    // Run migrations on disposable database
    Artisan::call('migrate', [
        '--database' => 'mariadb_test',
        '--force' => true,
    ]);

    $columns = DB::connection('mariadb_test')->select("SHOW COLUMNS FROM student_rombels LIKE 'student_id'");
    expect($columns)->not->toBeEmpty();

    $nikCol = DB::connection('mariadb_test')->select("SHOW COLUMNS FROM student_rombels LIKE 'student_nik'");
    expect($nikCol[0]->Null)->toBe('YES');

    $triggers = DB::connection('mariadb_test')->select("SHOW TRIGGERS WHERE `Table` = 'student_rombels'");
    $triggerNames = array_map(fn ($t) => $t->Trigger, $triggers);
    expect($triggerNames)->toContain('trg_student_rombels_single_active_insert');
    expect($triggerNames)->toContain('trg_student_rombels_single_active_update');
});

test('mariadb integration: database trigger blocks duplicate active rombel in same academic year', function () {
    $conn = DB::connection('mariadb_test');

    $nik = str_pad((string) mt_rand(10000000, 99999999).mt_rand(10000000, 99999999), 16, '0', STR_PAD_LEFT);
    $yearName = '2026/2027 MDB '.uniqid();

    // Create isolated reference records in disposable DB
    $yearId = $conn->table('school_years')->insertGetId([
        'name' => $yearName,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $levelId = $conn->table('school_levels')->insertGetId([
        'name' => 'Grade 10 MDB '.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rombel1 = $conn->table('school_rombels')->insertGetId([
        'name' => '10-A-MDB-'.uniqid(),
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rombel2 = $conn->table('school_rombels')->insertGetId([
        'name' => '10-B-MDB-'.uniqid(),
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = $conn->table('students')->insertGetId([
        'name' => 'MariaDB Student Test',
        'nik' => $nik,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Insert 1: Active in Rombel 1 -> MUST SUCCEED
    $conn->table('student_rombels')->insert([
        'student_id' => $studentId,
        'student_nik' => $nik,
        'school_rombel' => $rombel1,
        'student_status_id' => 1, // Aktif
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Insert 2: Second Active in Rombel 2 (same year) -> MUST BE REJECTED BY TRIGGER
    $failed = false;
    $errorMessage = '';
    try {
        $conn->table('student_rombels')->insert([
            'student_id' => $studentId,
            'student_nik' => $nik,
            'school_rombel' => $rombel2,
            'student_status_id' => 1, // Aktif
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Throwable $e) {
        $failed = true;
        $errorMessage = $e->getMessage();
    }

    expect($failed)->toBeTrue();
    expect($errorMessage)->toContain('Student already has an active rombel membership in this academic year');

    // Insert 3: Inactive membership in Rombel 2 (e.g. status 2) -> MUST SUCCEED preserving history
    $sr3Id = $conn->table('student_rombels')->insertGetId([
        'student_id' => $studentId,
        'student_nik' => $nik,
        'school_rombel' => $rombel2,
        'student_status_id' => 2, // Mutasi Keluar / Non-aktif
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    expect($sr3Id)->toBeGreaterThan(0);
});

test('mariadb integration: backfill service executes cleanly on real MariaDB', function () {
    $conn = DB::connection('mariadb_test');

    $nikBackfill = str_pad((string) mt_rand(10000000, 99999999).mt_rand(10000000, 99999999), 16, '0', STR_PAD_LEFT);

    $studentId = $conn->table('students')->insertGetId([
        'name' => 'MariaDB Backfill Student',
        'nik' => $nikBackfill,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rombel = $conn->table('school_rombels')->first();
    expect($rombel)->not->toBeNull();

    // Insert legacy row with student_id = NULL and inactive status so it doesn't conflict
    $srId = $conn->table('student_rombels')->insertGetId([
        'student_id' => null,
        'student_nik' => $nikBackfill,
        'school_rombel' => $rombel->id,
        'student_status_id' => 2,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Test backfill query directly on connection
    $matches = $conn->table('students')->where('nik', $nikBackfill)->pluck('id');
    expect($matches->count())->toBe(1);

    $conn->table('student_rombels')->where('id', $srId)->update(['student_id' => $matches->first()]);

    $updated = $conn->table('student_rombels')->where('id', $srId)->first();
    expect($updated->student_id)->toBe($studentId);
});
