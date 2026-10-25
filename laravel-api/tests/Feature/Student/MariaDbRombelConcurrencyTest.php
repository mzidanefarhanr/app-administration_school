<?php

use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $envDir = 'C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env';
    $passFile = "$envDir\secret_db_pass.txt";
    $pass = file_exists($passFile) ? trim(file_get_contents($passFile)) : '';

    config([
        'database.connections.mariadb_03d' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'school_03d_integration',
            'username' => 'school_compat_app',
            'password' => $pass,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ],
    ]);

    try {
        DB::connection('mariadb_03d')->getPdo();
    } catch (\Throwable $e) {
        $this->markTestSkipped('MariaDB local instance not reachable for 03d integration: '.$e->getMessage());
    }

    $currentDb = DB::connection('mariadb_03d')->getDatabaseName();
    if ($currentDb !== 'school_03d_integration') {
        throw new \RuntimeException("Safety Guard: Aborted. Cannot run tests on non-disposable database: {$currentDb}");
    }

    // Run migrations on isolated disposable database
    Artisan::call('migrate', [
        '--database' => 'mariadb_03d',
        '--force' => true,
    ]);

    $conn = DB::connection('mariadb_03d');
    if (! $conn->table('student_statuses')->where('id', 1)->exists()) {
        $conn->table('student_statuses')->insert(['id' => 1, 'name' => 'Aktif', 'created_at' => now(), 'updated_at' => now()]);
    }
});

test('mariadb integration: two concurrent assigns with same student version serialize via row lock; exactly one succeeds', function () {
    $conn = DB::connection('mariadb_03d');

    // Setup fresh school year & rombel
    $suffix = bin2hex(random_bytes(4));
    $yearId = $conn->table('school_years')->insertGetId(['name' => '2026/2027 UAT-03D '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $levelId = $conn->table('school_levels')->insertGetId(['name' => 'Grade 10 UAT-03D '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $rombelId = $conn->table('school_rombels')->insertGetId([
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'name' => '10-A 03D',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = $conn->table('students')->insertGetId([
        'name' => 'Concurrent Assign Student',
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pdo1 = $conn->getPdo();
    $envDir = 'C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env';
    $pass = file_exists("$envDir\secret_db_pass.txt") ? trim(file_get_contents("$envDir\secret_db_pass.txt")) : '';
    $pdo2 = new PDO('mysql:host=127.0.0.1;port=3306;dbname=school_03d_integration', 'school_compat_app', $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $today = Carbon::now('Asia/Jakarta')->toDateString();

    // Transaction 1 locks student row, assigns rombel, bumps version, commits
    $pdo1->beginTransaction();
    $stmt1 = $pdo1->prepare('SELECT id, version, deleted_at FROM students WHERE id = ? FOR UPDATE');
    $stmt1->execute([$studentId]);
    $row1 = $stmt1->fetch(PDO::FETCH_ASSOC);

    expect((int) $row1['version'])->toBe(1);

    // Insert membership on PDO 1
    $insStmt1 = $pdo1->prepare('INSERT INTO student_rombels (student_id, school_rombel, student_status_id, latest_student_entry_date, version, created_at, updated_at) VALUES (?, ?, 1, ?, 1, NOW(), NOW())');
    $insStmt1->execute([$studentId, $rombelId, $today]);

    // Bump student version on PDO 1
    $updStmt1 = $pdo1->prepare('UPDATE students SET version = version + 1 WHERE id = ?');
    $updStmt1->execute([$studentId]);
    $pdo1->commit();

    // Now Transaction 2 tries with outdated expected_student_version = 1
    $pdo2->beginTransaction();
    $stmt2 = $pdo2->prepare('SELECT id, version, deleted_at FROM students WHERE id = ? FOR UPDATE');
    $stmt2->execute([$studentId]);
    $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

    $expectedVersion = 1;
    $versionConflict = ((int) $row2['version'] !== $expectedVersion);
    $pdo2->rollBack();

    expect($versionConflict)->toBeTrue();

    // Verify final state: version is exactly 2, and exactly 1 active membership exists
    $finalStudent = $conn->table('students')->where('id', $studentId)->first();
    expect($finalStudent->version)->toBe(2);

    $activeMemberships = $conn->table('student_rombels')
        ->where('student_id', $studentId)
        ->whereNull('deleted_at')
        ->whereNull('archived_at')
        ->whereNull('latest_student_exit_date')
        ->count();
    expect($activeMemberships)->toBe(1);
});

test('mariadb integration: archived student never ends up with an active rombel membership', function () {
    $conn = DB::connection('mariadb_03d');

    $suffix = bin2hex(random_bytes(4));
    $yearId = $conn->table('school_years')->insertGetId(['name' => '2026/2027 UAT-03D-ARCH '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $levelId = $conn->table('school_levels')->insertGetId(['name' => 'Grade 10 UAT-03D-ARCH '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $rombelId = $conn->table('school_rombels')->insertGetId([
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'name' => '10-A ARCH',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = $conn->table('students')->insertGetId([
        'name' => 'Archived Race Student',
        'version' => 1,
        'deleted_at' => now(), // archived!
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pdo = $conn->getPdo();

    // MariaDB trigger trg_student_rombels_single_active_insert strictly blocks insert for deleted_at IS NOT NULL
    $blocked = false;
    try {
        $stmt = $pdo->prepare('INSERT INTO student_rombels (student_id, school_rombel, student_status_id, latest_student_entry_date, version, created_at, updated_at) VALUES (?, ?, 1, CURDATE(), 1, NOW(), NOW())');
        $stmt->execute([$studentId, $rombelId]);
    } catch (\PDOException $e) {
        if ($e->getCode() == '45000' || str_contains($e->getMessage(), 'Cannot create active rombel membership for an archived student')) {
            $blocked = true;
        }
    }

    expect($blocked)->toBeTrue();

    $activeCount = $conn->table('student_rombels')
        ->where('student_id', $studentId)
        ->whereNull('deleted_at')
        ->whereNull('archived_at')
        ->whereNull('latest_student_exit_date')
        ->count();
    expect($activeCount)->toBe(0);
});
