<?php

use App\Services\BackupService;
use App\Services\RestoreDrillService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

beforeEach(function () {
    $this->backupTestDir = storage_path('framework/testing/disks/testing/backups');
    $this->attachmentsTestDir = storage_path('framework/testing/disks/testing/attachments');
    $this->drillAttachmentsDir = storage_path('framework/testing/disks/testing/drill-attachments');

    File::ensureDirectoryExists($this->backupTestDir);
    File::ensureDirectoryExists($this->attachmentsTestDir);
    File::ensureDirectoryExists($this->drillAttachmentsDir);

    config(['backup.path' => $this->backupTestDir]);
    config(['backup.attachments_path' => $this->attachmentsTestDir]);
    config(['backup.drill_attachments_path' => $this->drillAttachmentsDir]);

    // Setup koneksi drill SQLite terisolasi
    config([
        'database.connections.drill_sqlite' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
    ]);

    $this->passphrase = 'CorrectPassphrase123!';
});

afterEach(function () {
    if (File::isDirectory($this->backupTestDir)) {
        File::deleteDirectory($this->backupTestDir);
    }
    if (File::isDirectory($this->attachmentsTestDir)) {
        File::deleteDirectory($this->attachmentsTestDir);
    }
    if (File::isDirectory($this->drillAttachmentsDir)) {
        File::deleteDirectory($this->drillAttachmentsDir);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// 1. Sukses Round-Trip Restore Drill
// ─────────────────────────────────────────────────────────────────────────────

test('restore drill successfully restores and verifies database and attachments on disposable target', function () {
    $this->seedAccessReferenceData();
    $this->makeUserWithRole('admin-sistem');

    // Buat file lampiran sintetis
    $testDocPath = $this->attachmentsTestDir.DIRECTORY_SEPARATOR.'sample-cert.txt';
    File::put($testDocPath, 'Synthetic Certificate Content 2026');

    // Buat backup
    $backupService = app(BackupService::class);
    $backupResult = $backupService->createBackup($this->passphrase);

    // Jalankan restore drill ke target koneksi drill_sqlite
    $drillService = app(RestoreDrillService::class);
    $report = $drillService->runDrill(
        $backupResult['path'],
        $this->passphrase,
        'drill_sqlite',
        true
    );

    expect($report['status'])->toBe('PASS');
    expect($report['checks']['tables_verified'])->toBeGreaterThan(0);
    expect($report['checks']['foreign_keys_valid'])->toBeTrue();
    expect($report['checks']['admin_account_verified'])->toBeTrue();
    expect($report['checks']['attachments_verified'])->toBe(1);

    // Verifikasi data di target connection drill_sqlite benar-benar ada
    $restoredRoles = DB::connection('drill_sqlite')->table('roles')->count();
    expect($restoredRoles)->toBeGreaterThan(0);
    $restoredUsers = DB::connection('drill_sqlite')->table('users')->count();
    expect($restoredUsers)->toBeGreaterThan(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. Proteksi Fail-Safe Keamanan Target
// ─────────────────────────────────────────────────────────────────────────────

test('restore drill strictly refuses to target operational database backdata_administrations', function () {
    $backupService = app(BackupService::class);
    $backupResult = $backupService->createBackup($this->passphrase);

    // Konfigurasi koneksi dummy yang mengarah ke backdata_administrations
    config([
        'database.connections.fake_operational' => [
            'driver' => 'mysql',
            'database' => 'backdata_administrations',
            'host' => '127.0.0.1',
        ],
    ]);

    $drillService = app(RestoreDrillService::class);

    expect(fn () => $drillService->runDrill($backupResult['path'], $this->passphrase, 'fake_operational'))
        ->toThrow(\RuntimeException::class, 'SECURITY ERROR: Target database \'backdata_administrations\' is the protected operational database!');
});

test('restore drill strictly refuses target database identical to active source database', function () {
    $backupService = app(BackupService::class);
    $backupResult = $backupService->createBackup($this->passphrase);

    $currentConn = config('database.default');
    $drillService = app(RestoreDrillService::class);

    expect(fn () => $drillService->runDrill($backupResult['path'], $this->passphrase, $currentConn))
        ->toThrow(\RuntimeException::class, 'SECURITY ERROR: Target connection');
});

test('restore drill refuses target database not on disposable allowlist', function () {
    $backupService = app(BackupService::class);
    $backupResult = $backupService->createBackup($this->passphrase);

    config([
        'database.connections.unauthorized_db' => [
            'driver' => 'mysql',
            'database' => 'some_random_database',
            'host' => '127.0.0.1',
        ],
    ]);

    $drillService = app(RestoreDrillService::class);

    expect(fn () => $drillService->runDrill($backupResult['path'], $this->passphrase, 'unauthorized_db'))
        ->toThrow(\RuntimeException::class, 'is not in the allowed disposable databases list');
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Validasi Integritas & Anti-Malicious Archive
// ─────────────────────────────────────────────────────────────────────────────

test('restore drill rejects archive with invalid passphrase', function () {
    $backupService = app(BackupService::class);
    $backupResult = $backupService->createBackup($this->passphrase);

    $drillService = app(RestoreDrillService::class);

    expect(fn () => $drillService->runDrill($backupResult['path'], 'WrongPassphrase123!', 'drill_sqlite', true))
        ->toThrow(\RuntimeException::class);
});

test('restore drill rejects modified archive failing companion checksum', function () {
    $backupService = app(BackupService::class);
    $backupResult = $backupService->createBackup($this->passphrase);

    // Rusak isi companion checksum
    File::put($backupResult['companion_sha256_path'], "0000000000000000000000000000000000000000000000000000000000000000  {$backupResult['filename']}\n");

    $drillService = app(RestoreDrillService::class);

    expect(fn () => $drillService->runDrill($backupResult['path'], $this->passphrase, 'drill_sqlite', true))
        ->toThrow(\RuntimeException::class, 'Companion checksum mismatch');
});

test('restore drill rejects zip containing path traversal (Zip Slip guard)', function () {
    // Buat ZIP jahat dengan path traversal
    $maliciousZipPath = $this->backupTestDir.DIRECTORY_SEPARATOR.'backup-malicious.zip';
    $zip = new ZipArchive;
    $zip->open($maliciousZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->setPassword($this->passphrase);
    $zip->addFromString('manifest.json', json_encode(['database' => ['dump_file' => 'database.sql', 'dump_sha256' => '123']]));
    $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256, $this->passphrase);
    $zip->addFromString('../../malicious.txt', 'danger');
    $zip->setEncryptionName('../../malicious.txt', ZipArchive::EM_AES_256, $this->passphrase);
    $zip->close();

    $drillService = app(RestoreDrillService::class);

    expect(fn () => $drillService->runDrill($maliciousZipPath, $this->passphrase, 'drill_sqlite', true))
        ->toThrow(\RuntimeException::class, 'Zip Slip detected');
});

test('restore drill rejects SQL dump containing database switching commands (USE/CREATE DATABASE)', function () {
    $maliciousSql = "CREATE DATABASE hacked_db;\nUSE hacked_db;\nCREATE TABLE test (id INT);";

    $archivePath = $this->backupTestDir.DIRECTORY_SEPARATOR.'backup-sql-attack.zip';
    $zip = new ZipArchive;
    $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->setPassword($this->passphrase);

    $manifest = [
        'database' => [
            'dump_file' => 'database.sql',
            'dump_sha256' => hash('sha256', $maliciousSql),
            'tables' => [],
        ],
    ];

    $zip->addFromString('manifest.json', json_encode($manifest));
    $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256, $this->passphrase);
    $zip->addFromString('database.sql', $maliciousSql);
    $zip->setEncryptionName('database.sql', ZipArchive::EM_AES_256, $this->passphrase);
    $zip->close();

    $drillService = app(RestoreDrillService::class);

    expect(fn () => $drillService->runDrill($archivePath, $this->passphrase, 'drill_sqlite', true))
        ->toThrow(\RuntimeException::class, 'Security audit failed: SQL dump contains forbidden command');
});
