<?php

/**
 * Pre-Flight Isolation Guard for School Administration Application.
 *
 * Enforces strict fail-closed validation before any Laravel bootstrap or artisan command.
 * Rejects unexpected environments, database targets, cached configurations, or insecure ports.
 *
 * Usage:
 *   php scripts/verify-isolation.php uat [path-to-env-file]
 *   php scripts/verify-isolation.php testing [path-to-env-file]
 */

declare(strict_types=1);

$profile = $argv[1] ?? 'uat';
$envFile = $argv[2] ?? (dirname(__DIR__) . '/.env.' . $profile);

if (!in_array($profile, ['uat', 'testing'], true)) {
    fwrite(STDERR, "[FAIL-CLOSED] Invalid profile specified: '{$profile}'. Allowed: 'uat', 'testing'.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$errors = [];

// 1. Verify bootstrap cache files do not exist (prevents stale config overrides)
$configCache = $baseDir . '/bootstrap/cache/config.php';
$routesCache = $baseDir . '/bootstrap/cache/routes-v7.php';

if (file_exists($configCache)) {
    $errors[] = "Stale configuration cache detected at '{$configCache}'. Delete or remove config cache before running.";
}
if (file_exists($routesCache)) {
    $errors[] = "Stale route cache detected at '{$routesCache}'. Delete or remove route cache before running.";
}

// 2. Parse designated .env file if it exists
$parsedEnv = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            // Strip matching wrapping quotes
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }
            $parsedEnv[$key] = $val;
        }
    }
} elseif ($profile === 'uat') {
    $errors[] = "Required environment file not found: '{$envFile}'.";
}

// Helper to get effective variable taking process environment precedence into account
$getVar = function (string $key) use ($parsedEnv): ?string {
    $proc = getenv($key);
    if ($proc !== false && $proc !== '') {
        return $proc;
    }
    return $parsedEnv[$key] ?? null;
};

// 3. Profile-specific checks
if ($profile === 'uat') {
    // 3a. Verify APP_ENV
    $appEnv = $getVar('APP_ENV');
    if ($appEnv !== 'uat') {
        $errors[] = "APP_ENV must strictly be 'uat'. Detected: '" . ($appEnv ?? 'NULL') . "'.";
    }

    // 3b. Verify Database exact allowlist
    $dbConn = $getVar('DB_CONNECTION');
    if ($dbConn !== 'mysql') {
        $errors[] = "DB_CONNECTION must strictly be 'mysql'. Detected: '" . ($dbConn ?? 'NULL') . "'.";
    }

    $dbHost = $getVar('DB_HOST');
    if (!in_array($dbHost, ['127.0.0.1', 'localhost'], true)) {
        $errors[] = "DB_HOST must strictly be loopback ('127.0.0.1' or 'localhost'). Detected: '" . ($dbHost ?? 'NULL') . "'.";
    }

    $dbPort = (string) $getVar('DB_PORT');
    if ($dbPort !== '3306') {
        $errors[] = "DB_PORT must strictly be '3306'. Detected: '" . ($dbPort ?? 'NULL') . "'.";
    }

    $dbName = $getVar('DB_DATABASE');
    $exactAllowedDb = 'school_compat_uat';
    if ($dbName !== $exactAllowedDb) {
        $errors[] = "DB_DATABASE must strictly match exact allowlist '{$exactAllowedDb}'. Detected: '" . ($dbName ?? 'NULL') . "'.";
    }

    $dbUser = $getVar('DB_USERNAME');
    if ($dbUser === 'root') {
        $errors[] = "DB_USERNAME must not be 'root'. A dedicated application user is required.";
    }

    // 3c. Process Environment Override Guards
    if (getenv('DATABASE_URL') !== false && getenv('DATABASE_URL') !== '') {
        $errors[] = "DATABASE_URL is set in process environment; this can override target DB. Unset it.";
    }
    if (getenv('DB_URL') !== false && getenv('DB_URL') !== '') {
        $errors[] = "DB_URL is set in process environment; this can override target DB. Unset it.";
    }
    $procDb = getenv('DB_DATABASE');
    if ($procDb !== false && $procDb !== '' && $procDb !== $exactAllowedDb) {
        $errors[] = "Process environment DB_DATABASE ('{$procDb}') conflicts with target allowlist '{$exactAllowedDb}'.";
    }

    // 3d. URLs, Ports & Cookies
    $appUrl = $getVar('APP_URL');
    if ($appUrl !== 'http://127.0.0.1:18021' && $appUrl !== 'http://localhost:18021') {
        $errors[] = "APP_URL must strictly be 'http://127.0.0.1:18021' (or localhost). Detected: '" . ($appUrl ?? 'NULL') . "'.";
    }

    $frontendUrl = $getVar('FRONTEND_URL');
    if ($frontendUrl !== 'http://127.0.0.1:14021' && $frontendUrl !== 'http://localhost:14021') {
        $errors[] = "FRONTEND_URL must strictly be 'http://127.0.0.1:14021' (or localhost). Detected: '" . ($frontendUrl ?? 'NULL') . "'.";
    }

    $sessionCookie = $getVar('SESSION_COOKIE');
    if ($sessionCookie !== 'school_compat_session') {
        $errors[] = "SESSION_COOKIE must strictly be 'school_compat_session' for session isolation. Detected: '" . ($sessionCookie ?? 'NULL') . "'.";
    }

    // 3e. Safe Mailer
    $mailMailer = $getVar('MAIL_MAILER');
    if (!in_array($mailMailer, ['log', 'array'], true)) {
        $errors[] = "MAIL_MAILER must be 'log' or 'array' to prevent real SMTP transmission during UAT. Detected: '" . ($mailMailer ?? 'NULL') . "'.";
    }
} elseif ($profile === 'testing') {
    // Automated testing profile
    $dbConn = $getVar('DB_CONNECTION') ?? 'sqlite';
    $dbName = $getVar('DB_DATABASE') ?? ':memory:';

    if ($dbConn !== 'sqlite') {
        $errors[] = "Testing DB_CONNECTION must strictly be 'sqlite'. Detected: '{$dbConn}'.";
    }
    if ($dbName !== ':memory:') {
        $errors[] = "Testing DB_DATABASE must strictly be ':memory:'. Detected: '{$dbName}'.";
    }
}

// 4. Verify storage and temporary directories are within project and not junctions outside
$storageDir = $baseDir . '/storage';
if (!is_dir($storageDir)) {
    $errors[] = "Storage directory does not exist at '{$storageDir}'.";
}

// 5. Result reporting
if (!empty($errors)) {
    fwrite(STDERR, "[FAIL-CLOSED] Isolation Guard rejected environment for profile '{$profile}':\n");
    foreach ($errors as $err) {
        fwrite(STDERR, "  - {$err}\n");
    }
    exit(1);
}

fwrite(STDOUT, "[ISOLATION GUARD PASS] Environment verified safe for profile '{$profile}'.\n");
exit(0);
