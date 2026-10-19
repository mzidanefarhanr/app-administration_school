<?php

use App\Services\BackupService;
use App\Services\RestoreDrillService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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

    // Setup koneksi sumber dan target MariaDB lokal
    config([
        'database.connections.mariadb_source' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'school_access_uat',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ],
        'database.connections.mariadb_target' => [
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

test('real local MariaDB backup and restore drill round-trip between school_access_uat and school_restore_drill_uat', function () {
    // 1. Verifikasi koneksi efektif ke MariaDB lokal
    try {
        DB::connection('mariadb_source')->getPdo();
        DB::connection('mariadb_target')->getPdo();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Local MariaDB not reachable on 127.0.0.1:3306: '.$e->getMessage());
    }

    // 2. Pastikan database sumber bukan backdata_administrations
    $sourceDb = DB::connection('mariadb_source')->getDatabaseName();
    $targetDb = DB::connection('mariadb_target')->getDatabaseName();

    expect(strtolower($sourceDb))->toBe('school_access_uat');
    expect(strtolower($targetDb))->toBe('school_restore_drill_uat');
    expect(strtolower($targetDb))->not->toBe(strtolower($sourceDb));

    // 3. Buat file lampiran privat sintetis
    $testDocPath = $this->attachmentsTestDir.DIRECTORY_SEPARATOR.'student-contract-uat.pdf';
    File::put($testDocPath, '%PDF-1.4 Synthetic PDF Attachment Content for MariaDB Drill');

    $passphrase = 'MariaDbSecureDrillPass2026!';

    // 4. Jalankan backup nyata dari sumber school_access_uat memakai mysqldump
    $backupService = app(BackupService::class);
    $backupResult = $backupService->createBackup($passphrase, 'mariadb_source', $this->attachmentsTestDir);

    expect(file_exists($backupResult['path']))->toBeTrue();
    expect($backupResult['size_bytes'])->toBeGreaterThan(0);
    expect($backupResult['manifest']['database']['database_name'])->toBe('school_access_uat');

    // 5. Jalankan restore drill nyata ke target school_restore_drill_uat
    $drillService = app(RestoreDrillService::class);
    $report = $drillService->runDrill(
        $backupResult['path'],
        $passphrase,
        'mariadb_target',
        true
    );

    expect($report['status'])->toBe('PASS');
    expect($report['target_database'])->toBe('school_restore_drill_uat');
    expect($report['checks']['tables_verified'])->toBeGreaterThan(0);
    expect($report['checks']['admin_account_verified'])->toBeTrue();
    expect($report['checks']['attachments_verified'])->toBe(1);

    // 6. Verifikasi database target benar-benar terisi data yang cocok dengan sumber
    $targetUserCount = DB::connection('mariadb_target')->table('users')->count();
    $sourceUserCount = DB::connection('mariadb_source')->table('users')->count();
    expect($targetUserCount)->toBe($sourceUserCount);
});
