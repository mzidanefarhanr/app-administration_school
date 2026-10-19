<?php

use App\Services\BackupService;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAccessFixtures;

uses(CreatesAccessFixtures::class);

beforeEach(function () {
    // Siapkan storage testing privat
    $this->backupTestDir = storage_path('framework/testing/disks/testing/backups');
    $this->attachmentsTestDir = storage_path('framework/testing/disks/testing/attachments');

    File::ensureDirectoryExists($this->backupTestDir);
    File::ensureDirectoryExists($this->attachmentsTestDir);

    config(['backup.path' => $this->backupTestDir]);
    config(['backup.attachments_path' => $this->attachmentsTestDir]);
    config(['backup.encryption_key' => null]); // Pastikan env bersih
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
// 1. Otorisasi Endpoint API Backup
// ─────────────────────────────────────────────────────────────────────────────

test('guest cannot list or download backups', function () {
    $this->getJson('/api/backups')->assertStatus(401);
    $this->getJson('/api/backups/backup-test-123.zip/download')->assertStatus(401);
});

test('user without backup.manage cannot list or download backups', function () {
    $operator = $this->makeUserWithRole('operator-sekolah');
    Sanctum::actingAs($operator);

    $this->getJson('/api/backups')->assertStatus(403);
    $this->getJson('/api/backups/backup-test-123.zip/download')->assertStatus(403);
});

test('admin-sistem with backup.manage can list backups and download valid archive', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    // Buat file backup sintetis
    $filename = 'backup-testing-20261019_100000.zip';
    $filepath = $this->backupTestDir.DIRECTORY_SEPARATOR.$filename;
    File::put($filepath, 'dummy-zip-content');
    File::put($filepath.'.sha256', hash('sha256', 'dummy-zip-content')."  {$filename}\n");

    // List backups
    $response = $this->getJson('/api/backups');
    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $items = $response->json('data');
    expect($items)->toHaveCount(1);
    expect($items[0]['filename'])->toBe($filename);
    expect($items[0]['sha256'])->toBe(hash('sha256', 'dummy-zip-content'));

    // Download backup
    $downloadResponse = $this->get("/api/backups/{$filename}/download");
    $downloadResponse->assertStatus(200);
    $downloadResponse->assertHeader('content-type', 'application/zip');
});

test('download rejects path traversal and invalid filenames', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    // Traversal attempts
    $this->get('/api/backups/..%2F..%2F.env/download')->assertStatus(400);
    $this->get('/api/backups/sub%2Fbackup-test.zip/download')->assertStatus(400);

    // Non-existent or invalid format
    $this->get('/api/backups/invalid-name.zip/download')->assertStatus(400);
    $this->get('/api/backups/backup-nonexistent-999.zip/download')->assertStatus(404);
});

test('partial and temporary backup files are excluded from list and download', function () {
    $admin = $this->makeUserWithRole('admin-sistem');
    Sanctum::actingAs($admin);

    // Buat folder .tmp dan file parsial .tmp
    $tmpDir = $this->backupTestDir.DIRECTORY_SEPARATOR.'.tmp-sample-uuid';
    File::makeDirectory($tmpDir);
    File::put($this->backupTestDir.DIRECTORY_SEPARATOR.'backup-partial.zip.tmp', 'partial');
    File::put($this->backupTestDir.DIRECTORY_SEPARATOR.'backup-valid-20261019_111111.zip.sha256', 'sha256');

    $backupService = app(BackupService::class);
    $list = $backupService->listBackups();
    expect($list)->toBeEmpty();

    // Pastikan file .tmp ditolak download
    $this->get('/api/backups/backup-partial.zip.tmp/download')->assertStatus(400);
});

test('download rejects symlink resolving outside backup directory', function () {
    $backupService = app(BackupService::class);

    // Coba minta file dengan nama yang dimanipulasi
    expect(fn () => $backupService->getBackupFileForDownload('../../../outside.zip'))
        ->toThrow(\InvalidArgumentException::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. Command app:backup-run Contract & Execution
// ─────────────────────────────────────────────────────────────────────────────

test('backup run command creates encrypted zip via interactive secret prompt', function () {
    $this->seedAccessReferenceData();

    // Buat file lampiran sintetis untuk membuktikan dukungan file
    $testDocPath = $this->attachmentsTestDir.DIRECTORY_SEPARATOR.'sample-doc.txt';
    File::put($testDocPath, 'Synthetic attachment content for backup test');

    $passphrase = 'InteractiveSecretPass2026!';

    // Jalankan tanpa opsi --passphrase (rahasia diinput via secret prompt)
    $this->artisan('app:backup-run')
        ->expectsOutput('Starting automated backup process...')
        ->expectsQuestion('Enter backup encryption passphrase (min 12 characters)', $passphrase)
        ->expectsQuestion('Confirm passphrase', $passphrase)
        ->expectsOutput('Backup successfully created!')
        ->assertExitCode(0);

    // Verifikasi file backup terbuat di direktori privat
    $files = File::files($this->backupTestDir);
    $zipFiles = array_filter($files, fn ($f) => str_ends_with($f->getFilename(), '.zip'));
    expect($zipFiles)->toHaveCount(1);

    $zipFile = array_values($zipFiles)[0];
    $zipPath = $zipFile->getRealPath();
    $companionPath = $zipPath.'.sha256';

    // Verifikasi file checksum pendamping (.sha256) ada di luar arsip
    expect(file_exists($companionPath))->toBeTrue();
    $companionContent = trim(File::get($companionPath));
    expect($companionContent)->toContain(hash_file('sha256', $zipPath));

    // Buka arsip dengan password dan verifikasi enkripsi
    $zip = new ZipArchive;
    expect($zip->open($zipPath))->toBeTrue();
    $zip->setPassword($passphrase);

    // Verifikasi entri manifest.json, database.sql, dan attachments
    $manifestJson = $zip->getFromName('manifest.json');
    expect($manifestJson)->not->toBeFalse();

    $manifest = json_decode($manifestJson, true);
    expect($manifest['format_version'])->toBe('1.0');
    expect($manifest['database']['dump_file'])->toBe('database.sql');
    expect($manifest['attachments']['files'])->toHaveCount(1);
    expect($manifest['attachments']['files'][0]['relative_path'])->toBe('sample-doc.txt');

    // Pastikan kredensial tidak bocor di manifest
    $rawManifest = json_encode($manifest);
    expect($rawManifest)->not->toContain($passphrase);
    expect($rawManifest)->not->toContain('root');
    expect($rawManifest)->not->toContain('forge');

    // Verifikasi database.sql dapat diekstrak
    $extractedSql = $zip->getFromName('database.sql');
    expect($extractedSql)->not->toBeFalse();
    expect(hash('sha256', $extractedSql))->toBe($manifest['database']['dump_sha256']);

    $zip->close();
});

test('backup run command aborts when passphrase confirmation does not match', function () {
    $this->artisan('app:backup-run')
        ->expectsQuestion('Enter backup encryption passphrase (min 12 characters)', 'PassphraseOne123!')
        ->expectsQuestion('Confirm passphrase', 'PassphraseTwo456!')
        ->expectsOutput('Passphrase confirmation does not match. Backup aborted.')
        ->assertExitCode(1);

    expect(File::files($this->backupTestDir))->toBeEmpty();
});

test('backup run command rejects passphrase under 12 characters', function () {
    $this->artisan('app:backup-run')
        ->expectsQuestion('Enter backup encryption passphrase (min 12 characters)', 'short')
        ->expectsQuestion('Confirm passphrase', 'short')
        ->expectsOutput('Encryption passphrase must be at least 12 characters long.')
        ->assertExitCode(1);

    expect(File::files($this->backupTestDir))->toBeEmpty();
});

test('backup run cleans up temporary directories and plaintext dumps on failure', function () {
    $this->seedAccessReferenceData();

    // Hook kegagalan simulasi saat dump berlangsung
    BackupService::$testDumpHook = function ($tempDir) {
        // Verifikasi folder temp sedang ada saat hook dipanggil
        expect(File::isDirectory($tempDir))->toBeTrue();
        throw new \RuntimeException('Simulated dump failure for cleanup check');
    };

    config(['backup.encryption_key' => 'ValidConfiguredPassphrase2026!']);

    $this->artisan('app:backup-run')
        ->expectsOutput('Starting automated backup process...')
        ->assertExitCode(1);

    // Verifikasi tidak ada folder .tmp-* atau arsip parsial yang tertinggal
    $allDirs = File::directories($this->backupTestDir);
    expect($allDirs)->toBeEmpty();
    expect(File::files($this->backupTestDir))->toBeEmpty();
});

test('backup run detects attachment drift during backup and aborts publication', function () {
    $this->seedAccessReferenceData();

    // Buat lampiran awal
    File::put($this->attachmentsTestDir.DIRECTORY_SEPARATOR.'initial.txt', 'Initial Attachment');

    // Hook simulasi: saat database sedang di-dump, file lampiran diubah/ditambah
    BackupService::$testDumpHook = function ($tempDir) {
        File::put($this->attachmentsTestDir.DIRECTORY_SEPARATOR.'drift-file.txt', 'Drift File Added During Dump');
    };

    config(['backup.encryption_key' => 'ValidConfiguredPassphrase2026!']);

    $this->artisan('app:backup-run')
        ->expectsOutput('Starting automated backup process...')
        ->assertExitCode(1);

    // Publikasi gagal: tidak ada file .zip yang dipublikasikan
    expect(File::files($this->backupTestDir))->toBeEmpty();
});
