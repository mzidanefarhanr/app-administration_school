<?php

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
});

afterEach(function () {
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
    $this->get('/api/backups/backup-test-123.zip/download')->assertStatus(403);
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

// ─────────────────────────────────────────────────────────────────────────────
// 2. Command app:backup-run
// ─────────────────────────────────────────────────────────────────────────────

test('backup run command creates encrypted zip, manifest, and companion sha256', function () {
    $this->seedAccessReferenceData();

    // Buat file lampiran sintetis untuk membuktikan dukungan file
    $testDocPath = $this->attachmentsTestDir.DIRECTORY_SEPARATOR.'sample-doc.txt';
    File::put($testDocPath, 'Synthetic attachment content for backup test');

    $passphrase = 'SecurePassphrase2026!';

    $this->artisan('app:backup-run', [
        '--passphrase' => $passphrase,
    ])
        ->expectsOutput('Starting automated backup process...')
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

test('backup run command rejects passphrase under 12 characters', function () {
    $this->artisan('app:backup-run', [
        '--passphrase' => 'short-pass',
    ])
        ->expectsOutput('Encryption passphrase must be at least 12 characters long.')
        ->assertExitCode(1);

    expect(File::files($this->backupTestDir))->toBeEmpty();
});
