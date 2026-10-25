<?php

// tests/Support/concurrency_worker.php

require_once __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// 1. Parse command line options
$longopts = [
    'action:',
    'params:',
];
$options = getopt('', $longopts);

if (empty($options['action']) || empty($options['params'])) {
    fwrite(STDERR, "Missing required options --action or --params\n");
    exit(1);
}

$action = $options['action'];
$params = json_decode(base64_decode($options['params']), true);

if (! is_array($params)) {
    fwrite(STDERR, "Invalid params JSON\n");
    exit(1);
}

// 2. Configure dedicated disposable MariaDB database
$envDir = 'C:\Users\LENOVO\AppData\Local\Temp\opencode\school-sec-env';
$passFile = "$envDir\secret_db_pass.txt";
$pass = file_exists($passFile) ? trim(file_get_contents($passFile)) : '';

config([
    'database.default' => 'mariadb_03d',
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

DB::purge('mariadb_03d');
DB::reconnect('mariadb_03d');
DB::setDefaultConnection('mariadb_03d');

// Safety Guard: Abort if target is not disposable database
$currentDb = DB::connection()->getDatabaseName();
if ($currentDb !== 'school_03d_integration') {
    fwrite(STDERR, "Safety Guard: Prohibited database target: {$currentDb}\n");
    exit(1);
}

// 3. Authenticate operator with students.manage ability
$operator = User::where('username', 'concurrency_operator')->first();
if (! $operator) {
    fwrite(STDERR, "Concurrency operator not found in database\n");
    exit(1);
}
Auth::login($operator);

$sessionDir = $params['session_dir'] ?? null;
$role = $params['role'] ?? 'standalone';

// 4. Setup synchronization barriers
if ($role === 'holder') {
    if (! $sessionDir || ! is_dir($sessionDir)) {
        fwrite(STDERR, "Invalid session directory for holder\n");
        exit(1);
    }

    Activity::$testCreatingHook = function ($activity) use ($sessionDir) {
        static $hookTriggered = false;
        if ($hookTriggered) {
            return;
        }
        $hookTriggered = true;

        // Signal holder is ready and holds row lock
        file_put_contents("$sessionDir/holder_hook_start.flag", (string) microtime(true));
        file_put_contents("$sessionDir/holder_ready.flag", '1');

        // Poll information_schema.innodb_trx to verify contender is actively in LOCK WAIT
        // Use root (default XAMPP local) for PROCESS privilege to inspect innodb_trx on school_03d_integration
        // Poll SHOW FULL PROCESSLIST / innodb_trx to verify contender is actively waiting on row lock
        $start = microtime(true);
        $sawWait = false;
        while (microtime(true) - $start < 10.0) {
            $monitorPdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=school_03d_integration', 'root', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $proc = $monitorPdo->query('SHOW FULL PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($proc as $p) {
                if ($p['User'] === 'school_compat_app'
                    && ($p['db'] ?? '') === 'school_03d_integration'
                    && str_contains(strtolower($p['Info'] ?? ''), 'for update')) {
                    $sawWait = true;
                    break 2;
                }
            }

            $waits = (int) $monitorPdo->query('SELECT COUNT(*) FROM information_schema.innodb_lock_waits')->fetchColumn();
            if ($waits > 0) {
                $sawWait = true;
                break;
            }

            $trxWait = (int) $monitorPdo->query("SELECT COUNT(*) FROM information_schema.innodb_trx WHERE trx_state = 'LOCK WAIT'")->fetchColumn();
            if ($trxWait > 0) {
                $sawWait = true;
                break;
            }

            $monitorPdo = null;
            usleep(25000); // 25ms
        }

        if (! $sawWait) {
            throw new \RuntimeException('FAIL/BLOCKED: Contender did not enter LOCK WAIT within 10s');
        }

        file_put_contents("$sessionDir/contender_waiting.flag", '1');

        file_put_contents("$sessionDir/contender_waiting.flag", '1');
    };
} elseif ($role === 'contender') {
    if (! $sessionDir || ! is_dir($sessionDir)) {
        fwrite(STDERR, "Invalid session directory for contender\n");
        exit(1);
    }

    // Wait until holder signals it is holding the lock
    $start = microtime(true);
    $holderReady = false;
    while (microtime(true) - $start < 15.0) {
        if (file_exists("$sessionDir/holder_ready.flag")) {
            $holderReady = true;
            break;
        }
        usleep(25000); // 25ms
    }

    if (! $holderReady) {
        fwrite(STDERR, "FAIL/BLOCKED: Holder never signaled holder_ready within 15s\n");
        exit(1);
    }
    file_put_contents("$sessionDir/contender_started.flag", microtime(true));
}

// 5. Execute actual application controller mutation
$status = 500;
$content = null;

if ($role === 'contender') {
    file_put_contents("$sessionDir/contender_before_controller.flag", microtime(true));
}

try {
    if ($action === 'assign') {
        $request = \App\Http\Requests\AssignStudentRombelRequest::create(
            "/api/students/{$params['student_id']}/assign-rombel",
            'POST',
            [
                'school_year_id' => $params['school_year_id'],
                'school_rombel_id' => $params['school_rombel_id'],
                'expected_student_version' => $params['expected_student_version'],
            ]
        );
        $request->setUserResolver(fn () => $operator);
        $request->setContainer($app);
        $request->validateResolved();

        $controller = $app->make(\App\Http\Controllers\StudentRombelController::class);
        $response = $controller->assignRombel($request, $params['student_id']);
        $status = $response->getStatusCode();
        $content = json_decode($response->getContent(), true);
    } elseif ($action === 'transfer') {
        $request = \App\Http\Requests\TransferStudentRombelRequest::create(
            "/api/students/{$params['student_id']}/transfer-rombel",
            'POST',
            [
                'school_year_id' => $params['school_year_id'],
                'target_rombel_id' => $params['target_rombel_id'],
                'expected_student_version' => $params['expected_student_version'],
                'expected_membership_version' => $params['expected_membership_version'],
                'reason' => $params['reason'] ?? null,
            ]
        );
        $request->setUserResolver(fn () => $operator);
        $request->setContainer($app);
        $request->validateResolved();

        $controller = $app->make(\App\Http\Controllers\StudentRombelController::class);
        $response = $controller->transferRombel($request, $params['student_id']);
        $status = $response->getStatusCode();
        $content = json_decode($response->getContent(), true);
        if ($role === 'contender') {
            file_put_contents("$sessionDir/contender_finished.flag", (string) microtime(true));
        }
    } elseif ($action === 'archive') {
        $request = \Illuminate\Http\Request::create(
            "/api/students/{$params['student_id']}",
            'DELETE',
            [
                'version' => $params['version'],
                'archive_reason' => $params['archive_reason'],
            ]
        );
        $request->setUserResolver(fn () => $operator);

        $controller = $app->make(\App\Http\Controllers\StudentController::class);
        $response = $controller->destroy($request, $params['student_id']);
        $status = $response->getStatusCode();
        $content = json_decode($response->getContent(), true);
    } else {
        throw new \InvalidArgumentException("Unknown action: {$action}");
    }
} catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
    $resp = $e->getResponse();
    $status = $resp->getStatusCode();
    $content = json_decode($resp->getContent(), true);
} catch (\Illuminate\Validation\ValidationException $e) {
    $status = 422;
    $content = [
        'message' => $e->getMessage(),
        'errors' => $e->errors(),
    ];
} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    $status = 404;
    $content = ['message' => 'Record not found.'];
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    $status = $e->getStatusCode();
    $content = ['message' => $e->getMessage()];
} catch (\Throwable $e) {
    $status = 500;
    $content = ['message' => $e->getMessage()];
}

echo 'RESULT_JSON:'.json_encode([
    'status' => $status,
    'content' => $content,
]);
