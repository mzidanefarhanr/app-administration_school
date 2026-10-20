<?php

use App\Services\BackupMonitoringService;
use App\Services\BackupMutex;
use App\Services\BackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

beforeEach(function () {
    $this->backupTestDir = storage_path('framework/testing/disks/testing/backups');
    $this->attachmentsTestDir = storage_path('framework/testing/disks/testing/attachments');
    $this->lockFilePath = storage_path('framework/testing/disks/testing/backups/.backup.lock');
    $this->monitoringFile = storage_path('framework/testing/disks/testing/backups/monitoring.json');

    File::ensureDirectoryExists($this->backupTestDir);
    File::ensureDirectoryExists($this->attachmentsTestDir);

    config(['backup.path' => $this->backupTestDir]);
    config(['backup.attachments_path' => $this->attachmentsTestDir]);
    config(['backup.lock_file' => $this->lockFilePath]);
    config(['backup.monitoring_file' => $this->monitoringFile]);
    config(['backup.encryption_key' => null]);
});

afterEach(function () {
    BackupService::$testDumpHook = null;

    if (File::isDirectory($this->backupTestDir)) {
        File::deleteDirectory($this->backupTestDir);
    }
    if (File::isDirectory($this->attachmentsTestDir)) {
        File::deleteDirectory($this->attachmentsTestDir);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// 1. Non-Interactive Execution
// ─────────────────────────────────────────────────────────────────────────────

test('non-interactive backup succeeds when valid encryption key is configured without prompts', function () {
    $this->seedAccessReferenceData();

    config(['backup.encryption_key' => 'ValidConfiguredSecretPass2026!']);

    $this->artisan('app:backup-run', ['--non-interactive' => true])
        ->expectsOutput('Starting automated backup process...')
        ->expectsOutput('Backup successfully created!')
        ->assertExitCode(0);

    // Verifikasi arsip terbuat
    $files = File::files($this->backupTestDir);
    $zips = array_filter($files, fn ($f) => str_ends_with($f->getFilename(), '.zip'));
    expect($zips)->toHaveCount(1);

    // Verifikasi monitoring tercatat
    $monitoring = app(BackupMonitoringService::class)->readRawData();
    expect($monitoring['last_attempt']['result'])->toBe('SUCCESS');
    expect($monitoring['last_success']['archive_file'])->toBe(array_values($zips)[0]->getFilename());
});

test('non-interactive backup fails immediately without prompt when encryption key is missing or short', function () {
    config(['backup.encryption_key' => null]);

    // Missing key
    $this->artisan('app:backup-run', ['--non-interactive' => true])
        ->expectsOutput('Starting automated backup process...')
        ->expectsOutput('Non-interactive backup aborted: BACKUP_ENCRYPTION_KEY is not configured or shorter than 12 characters.')
        ->assertExitCode(1);

    $zips = array_filter(File::files($this->backupTestDir), fn ($f) => str_ends_with($f->getFilename(), '.zip'));
    expect($zips)->toBeEmpty();

    // Short key (< 12 chars)
    config(['backup.encryption_key' => 'short-key']);
    $this->artisan('app:backup-run', ['--non-interactive' => true])
        ->expectsOutput('Starting automated backup process...')
        ->expectsOutput('Non-interactive backup aborted: BACKUP_ENCRYPTION_KEY is not configured or shorter than 12 characters.')
        ->assertExitCode(1);

    $zips = array_filter(File::files($this->backupTestDir), fn ($f) => str_ends_with($f->getFilename(), '.zip'));
    expect($zips)->toBeEmpty();
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. Scheduler Timezone & Opt-In Configuration
// ─────────────────────────────────────────────────────────────────────────────

test('scheduler is disabled by default (opt-in configuration)', function () {
    expect(config('backup.schedule_enabled'))->toBeFalse();

    $schedule = app(Schedule::class);
    $events = collect($schedule->events());

    // Pastikan tidak ada event backup jika disabled
    $backupEvents = $events->filter(fn ($e) => str_contains($e->command ?? '', 'app:backup-run') || str_contains($e->command ?? '', 'app:backup-prune'));
    expect($backupEvents)->toBeEmpty();
});

test('scheduler registers commands at 02:00 and 02:30 Asia/Jakarta when opt-in enabled', function () {
    config(['backup.schedule_enabled' => true]);
    config(['backup.schedule_timezone' => 'Asia/Jakarta']);
    config(['backup.schedule_backup_time' => '02:00']);
    config(['backup.schedule_prune_time' => '02:30']);

    // Refresh schedule kernel
    $kernel = app(\App\Console\Kernel::class);
    $schedule = app(Schedule::class);

    // Panggil schedule(Schedule $schedule) via reflection
    $method = new \ReflectionMethod($kernel, 'schedule');
    $method->setAccessible(true);
    $method->invoke($kernel, $schedule);

    $events = collect($schedule->events());

    // 1. Verifikasi event backup
    $backupEvent = $events->first(fn ($e) => str_contains($e->command ?? '', 'app:backup-run --non-interactive'));
    expect($backupEvent)->not->toBeNull();
    expect($backupEvent->expression)->toBe('0 2 * * *'); // 02:00 daily
    expect($backupEvent->timezone)->toBe('Asia/Jakarta');
    expect($backupEvent->withoutOverlapping)->toBeTrue();

    // 2. Verifikasi event prune
    $pruneEvent = $events->first(fn ($e) => str_contains($e->command ?? '', 'app:backup-prune'));
    expect($pruneEvent)->not->toBeNull();
    expect($pruneEvent->expression)->toBe('30 2 * * *'); // 02:30 daily
    expect($pruneEvent->timezone)->toBe('Asia/Jakarta');
    expect($pruneEvent->withoutOverlapping)->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Mutex Lock Overlap Prevention
// ─────────────────────────────────────────────────────────────────────────────

test('mutex lock prevents overlapping backup runs and logs skipped attempt without clearing last_success', function () {
    $this->seedAccessReferenceData();
    config(['backup.encryption_key' => 'ValidConfiguredSecretPass2026!']);

    $monitoringService = app(BackupMonitoringService::class);

    // Simulasikan keberhasilan backup pertama
    $exitCode = $this->artisan('app:backup-run', ['--non-interactive' => true])->run();
    expect($exitCode)->toBe(0);

    $dataBefore = $monitoringService->readRawData();
    expect($dataBefore['last_success'])->not->toBeNull();
    $firstSuccessFile = $dataBefore['last_success']['archive_file'];

    // Ambil lock file secara eksternal untuk menyimulasikan proses yang sedang berjalan
    $lockFp = fopen($this->lockFilePath, 'c+');
    $locked = flock($lockFp, LOCK_EX | LOCK_NB);
    expect($locked)->toBeTrue();

    try {
        // Coba jalankan backup kedua saat lock sedang aktif
        $this->artisan('app:backup-run', ['--non-interactive' => true])
            ->expectsOutput('Another backup or restore process is currently running. Skipping backup run.')
            ->assertExitCode(0);

        // Verifikasi bahwa status skip tercatat tapi last_success TETAP UTUH
        $dataAfter = $monitoringService->readRawData();
        expect($dataAfter['last_attempt']['result'])->toBe('SKIPPED');
        expect($dataAfter['last_attempt']['error_message'])->toContain('Another backup or restore process is currently running');
        expect($dataAfter['last_success']['archive_file'])->toBe($firstSuccessFile);
    } finally {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
});

test('mutex lock is released on failure allowing subsequent run to acquire lock', function () {
    $this->seedAccessReferenceData();
    config(['backup.encryption_key' => 'ValidConfiguredSecretPass2026!']);

    // Hook kegagalan
    BackupService::$testDumpHook = function ($tempDir) {
        throw new \RuntimeException('Simulated failure during mutex test');
    };

    $this->artisan('app:backup-run', ['--non-interactive' => true])
        ->assertExitCode(1);

    expect(BackupMutex::isLocked())->toBeFalse();

    // Hapus hook dan jalankan run kedua, harus berhasil memperoleh lock
    BackupService::$testDumpHook = null;
    $this->artisan('app:backup-run', ['--non-interactive' => true])
        ->assertExitCode(0);

    expect(BackupMutex::isLocked())->toBeFalse();
});
