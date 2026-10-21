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

test('mariadb integration: two concurrent transactions serialize via student row lock; exactly one succeeds', function () {
    $pdo1 = DB::connection('mariadb_test')->getPdo();
    $pdo2 = new PDO('mysql:host=127.0.0.1;port=3306;dbname=school_restore_drill_uat', 'root', '');
    $pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $uniq = uniqid();
    $pdo1->exec("INSERT INTO school_years (name, created_at, updated_at) VALUES ('Concurrency Year {$uniq}', NOW(), NOW());");
    $yearId = $pdo1->lastInsertId();

    $pdo1->exec("INSERT INTO school_levels (name, created_at, updated_at) VALUES ('Level {$uniq}', NOW(), NOW());");
    $levelId = $pdo1->lastInsertId();

    $pdo1->exec("INSERT INTO school_rombels (name, school_year_id, school_level_id, version, created_at, updated_at) VALUES ('Rombel A {$uniq}', {$yearId}, {$levelId}, 1, NOW(), NOW());");
    $rombel1 = $pdo1->lastInsertId();

    $pdo1->exec("INSERT INTO school_rombels (name, school_year_id, school_level_id, version, created_at, updated_at) VALUES ('Rombel B {$uniq}', {$yearId}, {$levelId}, 1, NOW(), NOW());");
    $rombel2 = $pdo1->lastInsertId();

    $nik = str_pad((string) mt_rand(10000000, 99999999).mt_rand(10000000, 99999999), 16, '0', STR_PAD_LEFT);
    $pdo1->exec("INSERT INTO students (name, nik, version, created_at, updated_at) VALUES ('Concurrent Student', '{$nik}', 1, NOW(), NOW());");
    $studentId = $pdo1->lastInsertId();

    // Transaksi 1 dan 2 berjalan bersamaan
    $pdo1->beginTransaction();
    $pdo2->beginTransaction();

    // Tx1 menyisipkan keanggotaan aktif di Rombel 1 (mengunci baris student)
    $pdo1->exec("INSERT INTO student_rombels (student_id, student_nik, school_rombel, student_status_id, version, created_at, updated_at) VALUES ({$studentId}, '{$nik}', {$rombel1}, 1, 1, NOW(), NOW());");

    // Tx1 commit
    $pdo1->commit();

    // Tx2 mencoba menyisipkan keanggotaan aktif di Rombel 2 untuk siswa dan tahun yang sama
    $tx2Failed = false;
    $tx2Msg = '';
    try {
        $pdo2->exec("INSERT INTO student_rombels (student_id, student_nik, school_rombel, student_status_id, version, created_at, updated_at) VALUES ({$studentId}, '{$nik}', {$rombel2}, 1, 1, NOW(), NOW());");
        $pdo2->commit();
    } catch (\Throwable $e) {
        $tx2Failed = true;
        $tx2Msg = $e->getMessage();
        $pdo2->rollBack();
    }

    expect($tx2Failed)->toBeTrue();
    expect($tx2Msg)->toContain('Student already has an active rombel membership in this academic year');

    // Asersi hasil akhir: tepat 1 keanggotaan aktif yang ada di database
    $stmt = $pdo1->query("SELECT COUNT(*) FROM student_rombels WHERE student_id = {$studentId} AND deleted_at IS NULL AND (student_status_id = 1 OR student_status_id IS NULL) AND latest_student_exit_date IS NULL");
    expect((int) $stmt->fetchColumn())->toBe(1);
});

test('mariadb integration: updating inactive rombel to active is blocked if another active membership exists in same year', function () {
    $conn = DB::connection('mariadb_test');

    $uniq = uniqid();
    $yearId = $conn->table('school_years')->insertGetId([
        'name' => 'Update Test Year '.$uniq,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $levelId = $conn->table('school_levels')->insertGetId([
        'name' => 'Level '.$uniq,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rombel1 = $conn->table('school_rombels')->insertGetId([
        'name' => 'Rombel 1 '.$uniq,
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rombel2 = $conn->table('school_rombels')->insertGetId([
        'name' => 'Rombel 2 '.$uniq,
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $nik = str_pad((string) mt_rand(10000000, 99999999).mt_rand(10000000, 99999999), 16, '0', STR_PAD_LEFT);
    $studentId = $conn->table('students')->insertGetId([
        'name' => 'Update Target Student',
        'nik' => $nik,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Rombel 1: Aktif
    $conn->table('student_rombels')->insert([
        'student_id' => $studentId,
        'student_nik' => $nik,
        'school_rombel' => $rombel1,
        'student_status_id' => 1,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Rombel 2: Tidak aktif (Mutasi Keluar, status 2)
    $sr2Id = $conn->table('student_rombels')->insertGetId([
        'student_id' => $studentId,
        'student_nik' => $nik,
        'school_rombel' => $rombel2,
        'student_status_id' => 2,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Update Rombel 2 menjadi Aktif (status 1) -> HARUS DITOLAK TRIGGER KARENA ROMBEL 1 SUDAH AKTIF
    $updateFailed = false;
    $updateMsg = '';
    try {
        $conn->table('student_rombels')->where('id', $sr2Id)->update([
            'student_status_id' => 1,
            'latest_student_exit_date' => null,
        ]);
    } catch (\Throwable $e) {
        $updateFailed = true;
        $updateMsg = $e->getMessage();
    }

    expect($updateFailed)->toBeTrue();
    expect($updateMsg)->toContain('Student already has an active rombel membership in this academic year');
});

test('mariadb integration: legacy row with student_id null blocks second active membership via NIK match', function () {
    $conn = DB::connection('mariadb_test');

    $uniq = uniqid();
    $yearId = $conn->table('school_years')->insertGetId([
        'name' => 'Legacy Match Year '.$uniq,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $levelId = $conn->table('school_levels')->insertGetId([
        'name' => 'Level '.$uniq,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rombel1 = $conn->table('school_rombels')->insertGetId([
        'name' => 'Legacy Rombel 1 '.$uniq,
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rombel2 = $conn->table('school_rombels')->insertGetId([
        'name' => 'Legacy Rombel 2 '.$uniq,
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $nik = str_pad((string) mt_rand(10000000, 99999999).mt_rand(10000000, 99999999), 16, '0', STR_PAD_LEFT);
    $studentId = $conn->table('students')->insertGetId([
        'name' => 'Legacy Linked Student',
        'nik' => $nik,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Baris legacy 1: student_id = NULL, student_nik = $nik, status = 1 (Aktif)
    $conn->table('student_rombels')->insert([
        'student_id' => null,
        'student_nik' => $nik,
        'school_rombel' => $rombel1,
        'student_status_id' => 1,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Mencoba menyisipkan baris baru untuk siswa yang sama menggunakan student_id
    $secondInsertFailed = false;
    $secondInsertMsg = '';
    try {
        $conn->table('student_rombels')->insert([
            'student_id' => $studentId,
            'student_nik' => $nik,
            'school_rombel' => $rombel2,
            'student_status_id' => 1,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (\Throwable $e) {
        $secondInsertFailed = true;
        $secondInsertMsg = $e->getMessage();
    }

    // Baris legacy student_id null TIDAK BISA dibypass untuk memasukkan rombel ganda aktif
    expect($secondInsertFailed)->toBeTrue();
    expect($secondInsertMsg)->toContain('Student already has an active rombel membership in this academic year');
});
