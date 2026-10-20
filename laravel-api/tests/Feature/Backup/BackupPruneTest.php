<?php

use App\Services\BackupMonitoringService;
use App\Services\BackupRetentionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->backupTestDir = storage_path('framework/testing/disks/testing/backups');
    $this->monitoringFile = storage_path('framework/testing/disks/testing/backups/monitoring.json');
    $this->lockFilePath = storage_path('framework/testing/disks/testing/backups/.backup.lock');

    File::ensureDirectoryExists($this->backupTestDir);

    config(['backup.path' => $this->backupTestDir]);
    config(['backup.monitoring_file' => $this->monitoringFile]);
    config(['backup.lock_file' => $this->lockFilePath]);
    config(['backup.schedule_timezone' => 'Asia/Jakarta']);
    config(['backup.retention_days' => 7]);
    config(['backup.retention_weeks' => 4]);

    // Set waktu acuan terkontrol: Selasa, 20 Oktober 2026 02:30:00 WIB
    Carbon::setTestNow(Carbon::parse('2026-10-20 02:30:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();

    if (File::isDirectory($this->backupTestDir)) {
        File::deleteDirectory($this->backupTestDir);
    }
});

// Helper untuk membuat file backup sintetis dengan timestamp spesifik
function createSyntheticArchive(string $dir, string $dateTimeStr, string $suffix = '020000'): string
{
    $carbon = Carbon::parse($dateTimeStr, 'Asia/Jakarta');
    $filename = "backup-uat-{$carbon->format('Ymd')}_{$suffix}.zip";
    $path = $dir.DIRECTORY_SEPARATOR.$filename;

    File::put($path, "ZIP Content for {$filename}");
    File::put($path.'.sha256', hash('sha256', "ZIP Content for {$filename}")."  {$filename}\n");
    touch($path, $carbon->getTimestamp());

    return $filename;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Evaluasi & Perlindungan Retensi
// ─────────────────────────────────────────────────────────────────────────────

test('retention preserves single archive when only one backup exists', function () {
    $fn = createSyntheticArchive($this->backupTestDir, '2026-10-01 02:00:00');

    $retentionService = app(BackupRetentionService::class);
    $result = $retentionService->evaluateRetention();

    expect($result['keep'])->toHaveKey($fn);
    expect($result['delete'])->toBeEmpty();
});

test('retention preserves latest backup overall regardless of age or count', function () {
    // Buat 1 backup lama (30 hari lalu)
    $fn = createSyntheticArchive($this->backupTestDir, '2026-09-20 02:00:00');

    $retentionService = app(BackupRetentionService::class);
    $result = $retentionService->evaluateRetention();

    expect($result['keep'])->toHaveKey($fn);
    expect($result['delete'])->toBeEmpty();
});

test('retention correctly retains 7 daily, 4 weekly across month boundaries and deletes older archives', function () {
    // Waktu sekarang: 20 Oktober 2026 (Selasa)
    // 1. Hari berjalan (today): 20 Okt (buat 2 backup pada hari yang sama untuk menguji dedup harian)
    $fnTodayOld = createSyntheticArchive($this->backupTestDir, '2026-10-20', '010000');
    $fnTodayLatest = createSyntheticArchive($this->backupTestDir, '2026-10-20', '020000');

    // 2. 6 hari sebelumnya (19, 18, 17, 16, 15, 14 Okt) -> total 7 hari
    $fn19 = createSyntheticArchive($this->backupTestDir, '2026-10-19', '020000');
    $fn18 = createSyntheticArchive($this->backupTestDir, '2026-10-18', '020000'); // Minggu (juga weekly slot W42)
    $fn17 = createSyntheticArchive($this->backupTestDir, '2026-10-17', '020000');
    $fn16 = createSyntheticArchive($this->backupTestDir, '2026-10-16', '020000');
    $fn15 = createSyntheticArchive($this->backupTestDir, '2026-10-15', '020000');
    $fn14 = createSyntheticArchive($this->backupTestDir, '2026-10-14', '020000');

    // 3. Hari di luar 7 harian tetapi dalam 4 minggu kalender terbaru:
    // Minggu ke-41 (akhir pekan: 11 Okt)
    $fnW41 = createSyntheticArchive($this->backupTestDir, '2026-10-11', '020000');
    $fnW41Mid = createSyntheticArchive($this->backupTestDir, '2026-10-09', '020000'); // Harus dihapus (kalah dari 11 Okt)

    // Minggu ke-40 (lintas bulan ke September! 30 Sep 2026 adalah Rabu di Week 40: 28 Sep - 04 Okt)
    $fnW40 = createSyntheticArchive($this->backupTestDir, '2026-09-30', '020000');

    // 4. Arsip kadaluarsa di luar 7 harian dan di luar 4 mingguan (Minggu ke-39: 27 Sep, Minggu ke-38: 20 Sep)
    $fnExpired39 = createSyntheticArchive($this->backupTestDir, '2026-09-27', '020000'); // Week 5
    $fnExpiredOld = createSyntheticArchive($this->backupTestDir, '2026-09-20', '020000'); // Week 6

    $retentionService = app(BackupRetentionService::class);
    $result = $retentionService->evaluateRetention();

    // Verifikasi yang DI-KEEP:
    $kept = array_keys($result['keep']);
    expect($kept)->toContain($fnTodayLatest); // Most recent + daily today (Week 43)
    expect($kept)->toContain($fn19); // Daily
    expect($kept)->toContain($fn18); // Daily + weekly (Week 42)
    expect($kept)->toContain($fn17);
    expect($kept)->toContain($fn16);
    expect($kept)->toContain($fn15);
    expect($kept)->toContain($fn14);
    expect($kept)->toContain($fnW41); // Weekly (Week 41)
    expect($kept)->toContain($fnW40); // Weekly (Week 40, across month boundary in Sep)

    // Verifikasi yang DI-DELETE:
    $deleted = array_keys($result['delete']);
    expect($deleted)->toContain($fnTodayOld); // Kalah dari fnTodayLatest pada tanggal yang sama
    expect($deleted)->toContain($fnW41Mid); // Kalah dari fnW41 pada minggu yang sama
    expect($deleted)->toContain($fnExpired39); // Minggu ke-5 (lebih dari 4 minggu)
    expect($deleted)->toContain($fnExpiredOld); // Minggu ke-6
});

test('dry run displays keep and delete candidates without modifying or deleting files', function () {
    $fn1 = createSyntheticArchive($this->backupTestDir, '2026-10-20', '020000');
    $fn2 = createSyntheticArchive($this->backupTestDir, '2026-10-20', '010000');
    $fnExpired = createSyntheticArchive($this->backupTestDir, '2026-08-01', '020000');

    $this->artisan('app:backup-prune', ['--dry-run' => true])
        ->expectsOutput('Evaluating backup retention policy (DRY-RUN mode)...')
        ->expectsOutput('Summary: 2 archives kept, 1 archives flagged for deletion.')
        ->assertExitCode(0);

    // Verifikasi bahwa seluruh berkas di disk TIDAK dihapus
    expect(file_exists($this->backupTestDir.DIRECTORY_SEPARATOR.$fnExpired))->toBeTrue();
    expect(file_exists($this->backupTestDir.DIRECTORY_SEPARATOR.$fnExpired.'.sha256'))->toBeTrue();
    expect(file_exists($this->backupTestDir.DIRECTORY_SEPARATOR.$fn2))->toBeTrue();
});

test('pruning deletes both zip archive and companion sha256 file as a group', function () {
    $fnKeep = createSyntheticArchive($this->backupTestDir, '2026-10-20', '020000');
    $fnDelete = createSyntheticArchive($this->backupTestDir, '2026-10-20', '010000');

    $retentionService = app(BackupRetentionService::class);
    $result = $retentionService->prune(dryRun: false);

    expect($result['status'])->toBe('SUCCESS');
    expect($result['deleted_count'])->toBe(1);

    // Arsip yang dihapus beserta companion-nya harus hilang dari disk
    expect(file_exists($this->backupTestDir.DIRECTORY_SEPARATOR.$fnDelete))->toBeFalse();
    expect(file_exists($this->backupTestDir.DIRECTORY_SEPARATOR.$fnDelete.'.sha256'))->toBeFalse();

    // Arsip yang dipertahankan tetap ada
    expect(file_exists($this->backupTestDir.DIRECTORY_SEPARATOR.$fnKeep))->toBeTrue();
    expect(file_exists($this->backupTestDir.DIRECTORY_SEPARATOR.$fnKeep.'.sha256'))->toBeTrue();
});

test('rotation is skipped if latest backup attempt failed today to prevent data loss', function () {
    // Buat arsip cadangan kemarin dan arsip lama
    $fnYesterday = createSyntheticArchive($this->backupTestDir, '2026-10-19', '020000');
    $fnOld = createSyntheticArchive($this->backupTestDir, '2026-08-01', '020000');

    // Catat bahwa attempt backup hari ini (20 Okt) gagal
    $monitoring = app(BackupMonitoringService::class);
    $monitoring->recordBackupFailure('Simulated database connection failure during scheduled backup', 12.5);

    // Jalankan prune
    $retentionService = app(BackupRetentionService::class);
    $result = $retentionService->prune(dryRun: false);

    expect($result['status'])->toBe('SKIPPED');
    expect($result['reason'])->toContain("Today's backup attempt failed");

    // Tidak ada file yang dihapus karena rotasi dilewati
    expect(file_exists($this->backupTestDir.DIRECTORY_SEPARATOR.$fnOld))->toBeTrue();
});

test('prune rejects symlinks and ignores corrupted or temporary files without deleting valid archives', function () {
    $fnValid = createSyntheticArchive($this->backupTestDir, '2026-10-20', '020000');

    // File parsial .tmp
    File::put($this->backupTestDir.DIRECTORY_SEPARATOR.'backup-partial.zip.tmp', 'tmp');
    // File dengan checksum rusak
    $fnCorrupted = $this->backupTestDir.DIRECTORY_SEPARATOR.'backup-corrupted-20260801_010101.zip';
    File::put($fnCorrupted, 'Corrupted Content');
    File::put($fnCorrupted.'.sha256', "invalid-hash  backup-corrupted-20260801_010101.zip\n");

    $retentionService = app(BackupRetentionService::class);
    $evaluation = $retentionService->evaluateRetention();

    // File rusak harus masuk skipped, tidak di-keep dan tidak di-delete diam-diam
    $skippedNames = array_column($evaluation['skipped'], 'filename');
    expect($skippedNames)->toContain('backup-corrupted-20260801_010101.zip');

    // File valid tetap terjaga
    expect($evaluation['keep'])->toHaveKey($fnValid);
});
