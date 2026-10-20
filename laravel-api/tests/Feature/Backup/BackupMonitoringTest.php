<?php

use App\Services\BackupMonitoringService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

beforeEach(function () {
    $this->backupTestDir = storage_path('framework/testing/disks/testing/backups');
    $this->attachmentsTestDir = storage_path('framework/testing/disks/testing/attachments');
    $this->monitoringFile = storage_path('framework/testing/disks/testing/backups/monitoring.json');

    File::ensureDirectoryExists($this->backupTestDir);
    File::ensureDirectoryExists($this->attachmentsTestDir);

    config(['backup.path' => $this->backupTestDir]);
    config(['backup.attachments_path' => $this->attachmentsTestDir]);
    config(['backup.monitoring_file' => $this->monitoringFile]);
    config(['backup.schedule_timezone' => 'Asia/Jakarta']);
    config(['backup.max_age_hours' => 28]);

    Carbon::setTestNow(Carbon::parse('2026-10-20 02:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();

    if (File::isDirectory($this->backupTestDir)) {
        File::deleteDirectory($this->backupTestDir);
    }
    if (File::isDirectory($this->attachmentsTestDir)) {
        File::deleteDirectory($this->attachmentsTestDir);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// 1. Status Kesehatan & Freshness Detection Dinamis
// ─────────────────────────────────────────────────────────────────────────────

test('health status returns NEVER_BACKED_UP on fresh installation', function () {
    $monitoringService = app(BackupMonitoringService::class);
    $health = $monitoringService->getHealthStatus();

    expect($health['status'])->toBe('NEVER_BACKED_UP');
    expect($health['backup_created'])->toBeFalse();
    expect($health['restore_verified'])->toBeFalse();
    expect($health['is_overdue'])->toBeFalse();
});

test('health status dynamically detects OVERDUE when more than 28 hours pass without running scheduler', function () {
    $monitoringService = app(BackupMonitoringService::class);

    // Set waktu saat backup terjadi: 2026-10-18 02:00:00 WIB
    Carbon::setTestNow(Carbon::parse('2026-10-18 02:00:00', 'Asia/Jakarta'));

    // Buat file arsip nyata dan catat sukses pada 2026-10-18 02:00:00 WIB
    $archiveName = 'backup-uat-20261018_020000.zip';
    File::put($this->backupTestDir.DIRECTORY_SEPARATOR.$archiveName, 'archive-content');

    $monitoringService->recordBackupSuccess([
        'filename' => $archiveName,
        'size_bytes' => 1024,
        'sha256' => hash('sha256', 'archive-content'),
    ], durationSeconds: 25.4);

    // 1. Verifikasi sehat saat masih dalam jendela (misal 10 jam kemudian: 2026-10-18 12:00:00)
    Carbon::setTestNow(Carbon::parse('2026-10-18 12:00:00', 'Asia/Jakarta'));
    $healthRecent = $monitoringService->getHealthStatus();
    expect($healthRecent['status'])->toBe('HEALTHY');
    expect($healthRecent['is_overdue'])->toBeFalse();
    expect($healthRecent['hours_since_last_success'])->toBe(10.0);

    // 2. Majukan waktu 30 jam kemudian (2026-10-19 08:00:00) tanpa mengeksekusi scheduler apapun
    Carbon::setTestNow(Carbon::parse('2026-10-19 08:00:00', 'Asia/Jakarta'));

    // Status harus langsung OVERDUE saat dibaca (dinamis)
    $healthOverdue = $monitoringService->getHealthStatus();
    expect($healthOverdue['status'])->toBe('OVERDUE');
    expect($healthOverdue['is_overdue'])->toBeTrue();
    expect($healthOverdue['hours_since_last_success'])->toBe(30.0);
});

test('failed backup attempt preserves last_success and updates health status to DEGRADED', function () {
    $monitoringService = app(BackupMonitoringService::class);

    $archiveName = 'backup-uat-20261019_020000.zip';
    File::put($this->backupTestDir.DIRECTORY_SEPARATOR.$archiveName, 'archive-content');

    // Sukses pertama
    $monitoringService->recordBackupSuccess([
        'filename' => $archiveName,
        'size_bytes' => 2048,
        'sha256' => hash('sha256', 'archive-content'),
    ], durationSeconds: 15.0);

    // Upaya kedua gagal (misal koneksi DB putus)
    Carbon::setTestNow(Carbon::parse('2026-10-19 14:00:00', 'Asia/Jakarta'));
    $monitoringService->recordBackupFailure('PDO connection refused during mysqldump', 5.0);

    $health = $monitoringService->getHealthStatus();

    // last_success tetap utuh dari run pertama
    expect($health['last_success']['archive_file'])->toBe($archiveName);
    expect($health['last_success']['size_bytes'])->toBe(2048);

    // last_attempt mencatat FAILED
    expect($health['last_attempt']['result'])->toBe('FAILED');
    expect($health['last_attempt']['error_message'])->toContain('PDO connection refused');

    // Status kesehatan adalah DEGRADED (ada cadangan valid tapi attempt terakhir gagal)
    expect($health['status'])->toBe('DEGRADED');
});

test('backup_created and restore_verified remain strictly distinct metrics', function () {
    $monitoringService = app(BackupMonitoringService::class);

    $archiveName = 'backup-uat-20261020_020000.zip';
    File::put($this->backupTestDir.DIRECTORY_SEPARATOR.$archiveName, 'archive-content');

    // Catat backup dibuat sukses
    $monitoringService->recordBackupSuccess([
        'filename' => $archiveName,
        'size_bytes' => 5000,
        'sha256' => 'sample-sha256-string',
    ], durationSeconds: 30.0);

    $healthBeforeDrill = $monitoringService->getHealthStatus();
    expect($healthBeforeDrill['backup_created'])->toBeTrue();
    expect($healthBeforeDrill['restore_verified'])->toBeFalse(); // BELUM diverifikasi drill!

    // Catat hasil restore drill yang diverifikasi nyata
    $monitoringService->recordDrillResult([
        'status' => 'PASS',
        'duration_seconds' => 45.0,
        'target_database' => 'school_restore_drill_uat',
        'archive_file' => $archiveName,
        'archive_sha256' => 'sample-sha256-string',
    ]);

    $healthAfterDrill = $monitoringService->getHealthStatus();
    expect($healthAfterDrill['backup_created'])->toBeTrue();
    expect($healthAfterDrill['restore_verified'])->toBeTrue();
    expect($healthAfterDrill['verified_archive'])->toBe($archiveName);
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. Endpoint API GET /api/backups/status
// ─────────────────────────────────────────────────────────────────────────────

test('guest cannot access backup status endpoint', function () {
    $this->getJson('/api/backups/status')->assertStatus(401);
});

test('user without backup.manage cannot access backup status endpoint', function () {
    $operator = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($operator);

    $this->getJson('/api/backups/status')->assertStatus(403);
});

test('admin with backup.manage can access status endpoint without leaking secrets or credentials', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    $monitoringService = app(BackupMonitoringService::class);
    $archiveName = 'backup-uat-20261020_020000.zip';
    File::put($this->backupTestDir.DIRECTORY_SEPARATOR.$archiveName, 'archive-content');
    $monitoringService->recordBackupSuccess([
        'filename' => $archiveName,
        'size_bytes' => 1024,
        'sha256' => hash('sha256', 'archive-content'),
    ], durationSeconds: 12.0);

    $response = $this->getJson('/api/backups/status');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'HEALTHY')
        ->assertJsonPath('data.timezone', 'Asia/Jakarta')
        ->assertJsonPath('data.backup_created', true);

    $rawContent = $response->getContent();
    expect($rawContent)->not->toContain('root');
    expect($rawContent)->not->toContain('password');
    expect($rawContent)->not->toContain('secret');
});
