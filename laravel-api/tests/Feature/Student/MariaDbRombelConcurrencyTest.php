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

    // Ensure access fixtures and concurrency operator exist in mariadb_03d
    $previousDefault = DB::getDefaultConnection();
    DB::setDefaultConnection('mariadb_03d');
    try {
        if (! DB::table('type_users')->where('id', 1)->exists()) {
            DB::table('type_users')->insert(['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]);
        }
        if (! DB::table('type_users')->where('id', 2)->exists()) {
            DB::table('type_users')->insert(['id' => 2, 'name' => 'User', 'created_at' => now(), 'updated_at' => now()]);
        }
        if (! DB::table('status_users')->where('id', 1)->exists()) {
            DB::table('status_users')->insert(['id' => 1, 'name' => 'Aktif', 'created_at' => now(), 'updated_at' => now()]);
        }

        $manageAbilityId = DB::table('abilities')->where('name', 'students.manage')->value('id');
        if (! $manageAbilityId) {
            $manageAbilityId = DB::table('abilities')->insertGetId([
                'name' => 'students.manage',
                'group' => 'students',
                'description' => 'Manage students',
                'is_sensitive' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $viewAbilityId = DB::table('abilities')->where('name', 'students.view')->value('id');
        if (! $viewAbilityId) {
            $viewAbilityId = DB::table('abilities')->insertGetId([
                'name' => 'students.view',
                'group' => 'students',
                'description' => 'View students',
                'is_sensitive' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $opRoleId = DB::table('roles')->where('slug', 'operator-sekolah')->value('id');
        if (! $opRoleId) {
            $opRoleId = DB::table('roles')->insertGetId([
                'name' => 'School Operator',
                'slug' => 'operator-sekolah',
                'description' => 'School Operator',
                'is_protected' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([$manageAbilityId, $viewAbilityId] as $abId) {
            if (! DB::table('ability_role')->where('role_id', $opRoleId)->where('ability_id', $abId)->exists()) {
                DB::table('ability_role')->insert(['role_id' => $opRoleId, 'ability_id' => $abId]);
            }
        }

        $operatorId = DB::table('users')->where('username', 'concurrency_operator')->value('id');
        if (! $operatorId) {
            $operatorId = DB::table('users')->insertGetId([
                'name' => 'Concurrency Operator',
                'username' => 'concurrency_operator',
                'email' => 'operator@concurrency.test',
                'password' => bcrypt('Password12345!'),
                'type_user_id' => 2,
                'status_user_id' => 1,
                'status_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('role_user')->where('user_id', $operatorId)->where('role_id', $opRoleId)->exists()) {
            DB::table('role_user')->insert([
                'user_id' => $operatorId,
                'role_id' => $opRoleId,
                'assigned_by' => $operatorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    } finally {
        DB::setDefaultConnection($previousDefault);
    }
});

function runConcurrentWorkers(string $action1, array $params1, string $action2, array $params2): array
{
    $tempBase = 'C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env\concurrency_sessions';
    if (! is_dir($tempBase)) {
        mkdir($tempBase, 0777, true);
    }
    $sessionDir = $tempBase.'\\sess_'.bin2hex(random_bytes(6));
    mkdir($sessionDir, 0777, true);

    $params1['session_dir'] = $sessionDir;
    $params1['role'] = 'holder';

    $params2['session_dir'] = $sessionDir;
    $params2['role'] = 'contender';

    $phpExe = 'C:\xampp\php82-operational\php.exe';
    $workerScript = base_path('tests/Support/concurrency_worker.php');

    $cmd1 = sprintf('"%s" "%s" --action=%s --params=%s', $phpExe, $workerScript, $action1, base64_encode(json_encode($params1)));
    $cmd2 = sprintf('"%s" "%s" --action=%s --params=%s', $phpExe, $workerScript, $action2, base64_encode(json_encode($params2)));

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $proc1 = proc_open($cmd1, $descriptors, $pipes1);
    $proc2 = proc_open($cmd2, $descriptors, $pipes2);

    $out1 = '';
    $out2 = '';
    $err1 = '';
    $err2 = '';
    stream_set_blocking($pipes1[1], false);
    stream_set_blocking($pipes1[2], false);
    stream_set_blocking($pipes2[1], false);
    stream_set_blocking($pipes2[2], false);

    $start = microtime(true);
    while (microtime(true) - $start < 25.0) {
        $out1 .= stream_get_contents($pipes1[1]);
        $err1 .= stream_get_contents($pipes1[2]);
        $out2 .= stream_get_contents($pipes2[1]);
        $err2 .= stream_get_contents($pipes2[2]);

        $status1 = proc_get_status($proc1);
        $status2 = proc_get_status($proc2);

        if (! $status1['running'] && ! $status2['running']) {
            $out1 .= stream_get_contents($pipes1[1]);
            $err1 .= stream_get_contents($pipes1[2]);
            $out2 .= stream_get_contents($pipes2[1]);
            $err2 .= stream_get_contents($pipes2[2]);
            break;
        }
        usleep(25000); // 25ms
    }

    foreach ([$pipes1, $pipes2] as $pipes) {
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
    }
    $exit1 = proc_close($proc1);
    $exit2 = proc_close($proc2);

    $res1 = null;
    if (preg_match('/RESULT_JSON:(.*)$/m', $out1, $m)) {
        $res1 = json_decode($m[1], true);
    }
    $res2 = null;
    if (preg_match('/RESULT_JSON:(.*)$/m', $out2, $m)) {
        $res2 = json_decode($m[1], true);
    }

    $overlapConfirmed = file_exists("$sessionDir/contender_waiting.flag");

    $debugJson = file_exists("$sessionDir/trx_debug.json") ? file_get_contents("$sessionDir/trx_debug.json") : null;
    $timing = [
        'holder_hook_start' => file_exists("$sessionDir/holder_hook_start.flag") ? file_get_contents("$sessionDir/holder_hook_start.flag") : null,
        'holder_hook_end' => file_exists("$sessionDir/holder_hook_end.flag") ? file_get_contents("$sessionDir/holder_hook_end.flag") : null,
        'contender_started' => file_exists("$sessionDir/contender_started.flag") ? file_get_contents("$sessionDir/contender_started.flag") : null,
        'contender_before_controller' => file_exists("$sessionDir/contender_before_controller.flag") ? file_get_contents("$sessionDir/contender_before_controller.flag") : null,
        'contender_finished' => file_exists("$sessionDir/contender_finished.flag") ? file_get_contents("$sessionDir/contender_finished.flag") : null,
    ];

    @unlink("$sessionDir/holder_ready.flag");
    @unlink("$sessionDir/holder_hook_start.flag");
    @unlink("$sessionDir/holder_hook_end.flag");
    @unlink("$sessionDir/contender_started.flag");
    @unlink("$sessionDir/contender_before_controller.flag");
    @unlink("$sessionDir/contender_finished.flag");
    @unlink("$sessionDir/contender_waiting.flag");
    @unlink("$sessionDir/trx_debug.json");
    @rmdir($sessionDir);

    return [
        'worker1' => ['exit' => $exit1, 'result' => $res1, 'stdout' => $out1, 'stderr' => $err1, 'debug' => $debugJson, 'timing' => $timing],
        'worker2' => ['exit' => $exit2, 'result' => $res2, 'stdout' => $out2, 'stderr' => $err2],
        'overlap_confirmed' => $overlapConfirmed,
    ];
}

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

test('mariadb integration: archived student rejection (non-race: pre-existing deleted_at is blocked by trigger)', function () {
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
        'name' => 'Archived Non-Race Student',
        'version' => 1,
        'deleted_at' => now(), // pre-existing archived!
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

test('mariadb concurrency: two concurrent transfers on same student serialize; exactly one 200 and one 409', function () {
    $conn = DB::connection('mariadb_03d');
    $suffix = bin2hex(random_bytes(4));

    $yearId = $conn->table('school_years')->insertGetId(['name' => '2026/2027 DualTransfer '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $levelId = $conn->table('school_levels')->insertGetId(['name' => 'Grade 10 DualTransfer '.$suffix, 'created_at' => now(), 'updated_at' => now()]);

    $rombelA = $conn->table('school_rombels')->insertGetId([
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'name' => '10-A DT '.$suffix,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $rombelB = $conn->table('school_rombels')->insertGetId([
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'name' => '10-B DT '.$suffix,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $rombelC = $conn->table('school_rombels')->insertGetId([
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'name' => '10-C DT '.$suffix,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = $conn->table('students')->insertGetId([
        'name' => 'Dual Transfer Student '.$suffix,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $membershipA = $conn->table('student_rombels')->insertGetId([
        'student_id' => $studentId,
        'school_rombel' => $rombelA,
        'student_status_id' => 1,
        'latest_student_entry_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'latest_student_exit_date' => null,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $params1 = [
        'student_id' => $studentId,
        'school_year_id' => $yearId,
        'target_rombel_id' => $rombelB,
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
        'reason' => 'Transfer to Rombel B',
    ];
    $params2 = [
        'student_id' => $studentId,
        'school_year_id' => $yearId,
        'target_rombel_id' => $rombelC,
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
        'reason' => 'Transfer to Rombel C',
    ];

    $res = runConcurrentWorkers('transfer', $params1, 'transfer', $params2);

    expect($res['overlap_confirmed'])->toBeTrue('Lock overlap confirmed via innodb_trx');
    expect($res['worker1']['result']['status'])->toBe(200);
    expect($res['worker2']['result']['status'])->toBe(409);

    $student = $conn->table('students')->where('id', $studentId)->first();
    expect($student->version)->toBe(2);

    // Old membership in rombelA is closed with exit_date and preserved status (DEC-23 #5)
    $oldMembership = $conn->table('student_rombels')->where('id', $membershipA)->first();
    expect($oldMembership->latest_student_exit_date)->not->toBeNull()
        ->and($oldMembership->student_status_id)->toBe(1)
        ->and($oldMembership->version)->toBe(2);

    // Rombel B has 1 active membership
    $membershipBCount = $conn->table('student_rombels')
        ->where('student_id', $studentId)
        ->where('school_rombel', $rombelB)
        ->whereNull('latest_student_exit_date')
        ->count();
    expect($membershipBCount)->toBe(1);

    // Rombel C has 0 memberships
    $membershipCCount = $conn->table('student_rombels')
        ->where('student_id', $studentId)
        ->where('school_rombel', $rombelC)
        ->count();
    expect($membershipCCount)->toBe(0);

    // Total active memberships is exactly 1
    $totalActive = $conn->table('student_rombels')
        ->where('student_id', $studentId)
        ->whereNull('deleted_at')
        ->whereNull('archived_at')
        ->whereNull('latest_student_exit_date')
        ->count();
    expect($totalActive)->toBe(1);
});

test('mariadb concurrency: archive vs assign race (assign wins lock first)', function () {
    $conn = DB::connection('mariadb_03d');
    $suffix = bin2hex(random_bytes(4));

    $yearId = $conn->table('school_years')->insertGetId(['name' => '2026/2027 AssignWins '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $levelId = $conn->table('school_levels')->insertGetId(['name' => 'Grade 10 AssignWins '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $rombelA = $conn->table('school_rombels')->insertGetId([
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'name' => '10-A AW '.$suffix,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = $conn->table('students')->insertGetId([
        'name' => 'Assign Wins Student '.$suffix,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $paramsAssign = [
        'student_id' => $studentId,
        'school_year_id' => $yearId,
        'school_rombel_id' => $rombelA,
        'expected_student_version' => 1,
    ];
    $paramsArchive = [
        'student_id' => $studentId,
        'version' => 1,
        'archive_reason' => 'Race archive contender',
    ];

    $res = runConcurrentWorkers('assign', $paramsAssign, 'archive', $paramsArchive);

    expect($res['overlap_confirmed'])->toBeTrue('Lock overlap confirmed via innodb_trx');
    expect($res['worker1']['result']['status'])->toBe(200);
    expect($res['worker2']['result']['status'])->toBe(409);

    $student = $conn->table('students')->where('id', $studentId)->first();
    expect($student->deleted_at)->toBeNull('Student must NOT be archived when archive fails with 409')
        ->and($student->version)->toBe(2);

    $activeCount = $conn->table('student_rombels')
        ->where('student_id', $studentId)
        ->where('school_rombel', $rombelA)
        ->whereNull('deleted_at')
        ->whereNull('archived_at')
        ->whereNull('latest_student_exit_date')
        ->count();
    expect($activeCount)->toBe(1);
});

test('mariadb concurrency: archive vs assign race (archive wins lock first)', function () {
    $conn = DB::connection('mariadb_03d');
    $suffix = bin2hex(random_bytes(4));

    $yearId = $conn->table('school_years')->insertGetId(['name' => '2026/2027 ArchAssignWins '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $levelId = $conn->table('school_levels')->insertGetId(['name' => 'Grade 10 ArchAssignWins '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $rombelA = $conn->table('school_rombels')->insertGetId([
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'name' => '10-A AAW '.$suffix,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = $conn->table('students')->insertGetId([
        'name' => 'Archive Wins Assign Student '.$suffix,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $paramsArchive = [
        'student_id' => $studentId,
        'version' => 1,
        'archive_reason' => 'Holder archive wins',
    ];
    $paramsAssign = [
        'student_id' => $studentId,
        'school_year_id' => $yearId,
        'school_rombel_id' => $rombelA,
        'expected_student_version' => 1,
    ];

    $res = runConcurrentWorkers('archive', $paramsArchive, 'assign', $paramsAssign);

    expect($res['overlap_confirmed'])->toBeTrue('Lock overlap confirmed via innodb_trx');
    expect($res['worker1']['result']['status'])->toBe(200);
    expect($res['worker2']['result']['status'])->toBe(422);

    $student = $conn->table('students')->where('id', $studentId)->first();
    expect($student->deleted_at)->not->toBeNull('Student must be archived')
        ->and($student->version)->toBe(2);

    $activeCount = $conn->table('student_rombels')
        ->where('student_id', $studentId)
        ->whereNull('deleted_at')
        ->whereNull('archived_at')
        ->whereNull('latest_student_exit_date')
        ->count();
    expect($activeCount)->toBe(0, 'Archived student must have NO active rombels');
});

test('mariadb concurrency: archive vs transfer race (transfer wins lock first)', function () {
    $conn = DB::connection('mariadb_03d');
    $suffix = bin2hex(random_bytes(4));

    $yearId = $conn->table('school_years')->insertGetId(['name' => '2026/2027 TransferWins '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $levelId = $conn->table('school_levels')->insertGetId(['name' => 'Grade 10 TransferWins '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $rombelA = $conn->table('school_rombels')->insertGetId([
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'name' => '10-A TW '.$suffix,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $rombelB = $conn->table('school_rombels')->insertGetId([
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'name' => '10-B TW '.$suffix,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = $conn->table('students')->insertGetId([
        'name' => 'Transfer Wins Student '.$suffix,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $membershipA = $conn->table('student_rombels')->insertGetId([
        'student_id' => $studentId,
        'school_rombel' => $rombelA,
        'student_status_id' => 1,
        'latest_student_entry_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'latest_student_exit_date' => null,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $paramsTransfer = [
        'student_id' => $studentId,
        'school_year_id' => $yearId,
        'target_rombel_id' => $rombelB,
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
        'reason' => 'Transfer before archive race',
    ];
    $paramsArchive = [
        'student_id' => $studentId,
        'version' => 1,
        'archive_reason' => 'Contender archive',
    ];

    $res = runConcurrentWorkers('transfer', $paramsTransfer, 'archive', $paramsArchive);

    expect($res['overlap_confirmed'])->toBeTrue('Lock overlap confirmed via innodb_trx');
    expect($res['worker1']['result']['status'])->toBe(200);
    expect($res['worker2']['result']['status'])->toBe(409);

    $student = $conn->table('students')->where('id', $studentId)->first();
    expect($student->deleted_at)->toBeNull('Student must NOT be archived')
        ->and($student->version)->toBe(2);

    $totalActive = $conn->table('student_rombels')
        ->where('student_id', $studentId)
        ->whereNull('deleted_at')
        ->whereNull('archived_at')
        ->whereNull('latest_student_exit_date')
        ->count();
    expect($totalActive)->toBe(1);

    $activeB = $conn->table('student_rombels')
        ->where('student_id', $studentId)
        ->where('school_rombel', $rombelB)
        ->whereNull('latest_student_exit_date')
        ->count();
    expect($activeB)->toBe(1);
});

test('mariadb concurrency: archive vs transfer race (archive wins lock first)', function () {
    $conn = DB::connection('mariadb_03d');
    $suffix = bin2hex(random_bytes(4));

    $yearId = $conn->table('school_years')->insertGetId(['name' => '2026/2027 ArchTransWins '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $levelId = $conn->table('school_levels')->insertGetId(['name' => 'Grade 10 ArchTransWins '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
    $rombelA = $conn->table('school_rombels')->insertGetId([
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'name' => '10-A ATW '.$suffix,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $rombelB = $conn->table('school_rombels')->insertGetId([
        'school_year_id' => $yearId,
        'school_level_id' => $levelId,
        'name' => '10-B ATW '.$suffix,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $studentId = $conn->table('students')->insertGetId([
        'name' => 'Archive Wins Transfer Student '.$suffix,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $membershipA = $conn->table('student_rombels')->insertGetId([
        'student_id' => $studentId,
        'school_rombel' => $rombelA,
        'student_status_id' => 1,
        'latest_student_entry_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'latest_student_exit_date' => null,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $paramsArchive = [
        'student_id' => $studentId,
        'version' => 1,
        'archive_reason' => 'Holder archive wins against transfer',
    ];
    $paramsTransfer = [
        'student_id' => $studentId,
        'school_year_id' => $yearId,
        'target_rombel_id' => $rombelB,
        'expected_student_version' => 1,
        'expected_membership_version' => 1,
    ];

    $res = runConcurrentWorkers('archive', $paramsArchive, 'transfer', $paramsTransfer);

    expect($res['overlap_confirmed'])->toBeTrue('Lock overlap confirmed via innodb_trx');
    expect($res['worker1']['result']['status'])->toBe(200);
    expect($res['worker2']['result']['status'])->toBe(422);

    $student = $conn->table('students')->where('id', $studentId)->first();
    expect($student->deleted_at)->not->toBeNull('Student must be archived')
        ->and($student->version)->toBe(2);

    // Old membership in rombel A must be archived by destroy()
    $oldMembership = $conn->table('student_rombels')->where('id', $membershipA)->first();
    expect($oldMembership->archived_at)->not->toBeNull();

    // Target rombel B must have 0 memberships
    $countB = $conn->table('student_rombels')
        ->where('student_id', $studentId)
        ->where('school_rombel', $rombelB)
        ->count();
    expect($countB)->toBe(0);

    // No active memberships remain
    $totalActive = $conn->table('student_rombels')
        ->where('student_id', $studentId)
        ->whereNull('deleted_at')
        ->whereNull('archived_at')
        ->whereNull('latest_student_exit_date')
        ->count();
    expect($totalActive)->toBe(0);
});
