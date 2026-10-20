<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BackupRetentionService
{
    protected BackupService $backupService;

    protected BackupMonitoringService $monitoringService;

    public function __construct(BackupService $backupService, BackupMonitoringService $monitoringService)
    {
        $this->backupService = $backupService;
        $this->monitoringService = $monitoringService;
    }

    /**
     * Evaluasi seluruh berkas backup di direktori privat berdasarkan aturan retensi:
     * 7 hari kalender terbaru + 4 minggu kalender terbaru (Senin-Minggu, Asia/Jakarta).
     *
     * @return array{keep: array<string, array<string, mixed>>, delete: array<string, array<string, mixed>>, skipped: array<int, array<string, string>>}
     */
    public function evaluateRetention(?string $backupDir = null): array
    {
        $backupDir = $backupDir ?: config('backup.path', storage_path('app/backups'));
        $tz = config('backup.schedule_timezone', 'Asia/Jakarta');

        if (! File::isDirectory($backupDir)) {
            return ['keep' => [], 'delete' => [], 'skipped' => []];
        }

        $allFiles = File::files($backupDir);
        $validArchives = [];
        $skipped = [];

        foreach ($allFiles as $file) {
            $filename = $file->getFilename();

            // Abaikan file checksum pendamping atau file tersembunyi/lock
            if (str_ends_with($filename, '.sha256') || str_starts_with($filename, '.')) {
                continue;
            }

            // Validasi format nama arsip resmi
            if (! preg_match('/^backup-[a-zA-Z0-9._-]+\.zip$/', $filename)) {
                $skipped[] = [
                    'filename' => $filename,
                    'reason' => 'Invalid backup archive name format',
                ];

                continue;
            }

            // Anti-symlink check: realpath harus berada di dalam backupDir
            $realPath = $file->getRealPath();
            $realBackupDir = realpath($backupDir);
            if (! $realPath || ! $realBackupDir || ! str_starts_with($realPath, $realBackupDir.DIRECTORY_SEPARATOR)) {
                $skipped[] = [
                    'filename' => $filename,
                    'reason' => 'Symlink or path outside backup directory',
                ];

                continue;
            }

            // Verifikasi integritas dasar: periksa companion sha256 jika ada
            $companionPath = $realPath.'.sha256';
            if (file_exists($companionPath)) {
                $expectedSha = preg_split('/\s+/', trim(File::get($companionPath)))[0] ?? '';
                $actualSha = hash_file('sha256', $realPath);
                if (! hash_equals($expectedSha, $actualSha)) {
                    $skipped[] = [
                        'filename' => $filename,
                        'reason' => 'Corrupted archive: companion checksum mismatch',
                    ];

                    continue;
                }
            }

            // Ekstrak waktu pembuatan dalam timezone Asia/Jakarta
            $createdAt = Carbon::createFromTimestamp($file->getMTime(), $tz);

            // Jika ada pola timestamp pada nama file (e.g. backup-uat-20261020_020000.zip), gunakan itu
            if (preg_match('/_?(\d{8})_(\d{6})\.zip$/', $filename, $m)) {
                try {
                    $parsed = Carbon::createFromFormat('Ymd_His', "{$m[1]}_{$m[2]}", $tz);
                    if ($parsed) {
                        $createdAt = $parsed;
                    }
                } catch (\Throwable $e) {
                    // Fallback to file mtime
                }
            }

            $validArchives[] = [
                'filename' => $filename,
                'path' => $realPath,
                'companion_path' => file_exists($companionPath) ? $companionPath : null,
                'size_bytes' => $file->getSize(),
                'created_at' => $createdAt,
                'date' => $createdAt->format('Y-m-d'),
                'week_key' => $createdAt->copy()->startOfWeek(Carbon::MONDAY)->format('o-W'), // ISO-8601 week
            ];
        }

        // Urutkan arsip dari yang paling baru ke paling lama
        usort($validArchives, fn ($a, $b) => $b['created_at']->getTimestamp() <=> $a['created_at']->getTimestamp());

        // Jika arsip valid <= 1, lindungi seluruhnya (tidak ada penghapusan)
        if (count($validArchives) <= 1) {
            $keep = [];
            foreach ($validArchives as $archive) {
                $keep[$archive['filename']] = array_merge($archive, ['reasons' => ['only_or_latest_backup']]);
            }

            return ['keep' => $keep, 'delete' => [], 'skipped' => $skipped];
        }

        $keep = [];

        // 1. Aturan Pengaman: Backup valid paling akhir SELALU dilindungi
        $mostRecent = $validArchives[0];
        $keep[$mostRecent['filename']] = array_merge($mostRecent, ['reasons' => ['most_recent']]);

        // 2. Kelompok Harian: 7 tanggal unik terbaru yang memiliki backup sukses
        $byDate = [];
        foreach ($validArchives as $archive) {
            $byDate[$archive['date']][] = $archive;
        }

        // Ambil maksimal 7 tanggal terbaru
        $recentDates = array_slice(array_keys($byDate), 0, 7);
        foreach ($recentDates as $date) {
            // Pilih arsip paling baru pada tanggal tersebut
            $latestOnDate = $byDate[$date][0];
            $fn = $latestOnDate['filename'];

            if (isset($keep[$fn])) {
                $keep[$fn]['reasons'][] = 'daily_slot ('.$date.')';
            } else {
                $keep[$fn] = array_merge($latestOnDate, ['reasons' => ['daily_slot ('.$date.')']]);
            }
        }

        // 3. Kelompok Mingguan: 4 minggu kalender unik terbaru (Senin-Minggu)
        // Memilih satu backup sukses terbaru pada minggu tersebut tanpa mensyaratkan hari Sabtu/Minggu.
        $byWeek = [];
        foreach ($validArchives as $archive) {
            $byWeek[$archive['week_key']][] = $archive;
        }

        // Ambil maksimal 4 minggu terbaru yang memiliki backup
        $recentWeeks = array_slice(array_keys($byWeek), 0, 4);
        foreach ($recentWeeks as $weekKey) {
            // Pilih arsip paling baru yang tersedia pada minggu tersebut (hari apa saja)
            $latestInWeek = $byWeek[$weekKey][0];
            $fn = $latestInWeek['filename'];

            if (isset($keep[$fn])) {
                $keep[$fn]['reasons'][] = 'weekly_slot (Week '.$weekKey.')';
            } else {
                $keep[$fn] = array_merge($latestInWeek, ['reasons' => ['weekly_slot (Week '.$weekKey.')']]);
            }
        }

        // 4. Tentukan kandidat penghapusan (Arsip valid yang tidak masuk ke keepSet)
        $delete = [];
        foreach ($validArchives as $archive) {
            $fn = $archive['filename'];
            if (! isset($keep[$fn])) {
                $delete[$fn] = $archive;
            }
        }

        return [
            'keep' => $keep,
            'delete' => $delete,
            'skipped' => $skipped,
        ];
    }

    /**
     * Jalankan rotasi retensi cadangan.
     *
     * @param  bool  $dryRun  Mode simulasi tanpa mutasi fisik berkas
     * @return array<string, mixed> Ringkasan hasil rotasi
     */
    public function prune(bool $dryRun = false): array
    {
        // 1. Periksa status monitoring: jika backup hari ini baru saja gagal, lewati rotasi!
        $monitoringData = $this->monitoringService->readRawData();
        $lastAttempt = $monitoringData['last_attempt'] ?? null;
        $tz = config('backup.schedule_timezone', 'Asia/Jakarta');

        if ($lastAttempt && ($lastAttempt['result'] ?? '') === 'FAILED') {
            $attemptDate = Carbon::parse($lastAttempt['timestamp'], $tz)->format('Y-m-d');
            $today = now($tz)->format('Y-m-d');

            if ($attemptDate === $today) {
                $reason = "Rotation skipped: Today's backup attempt failed. Retaining all previous backups for safety.";
                Log::warning($reason);

                return [
                    'status' => 'SKIPPED',
                    'reason' => $reason,
                    'kept_count' => 0,
                    'deleted_count' => 0,
                    'dry_run' => $dryRun,
                ];
            }
        }

        // 2. Eksekusi di bawah perlindungan Mutex Lock bersama
        return BackupMutex::withLock(function () use ($dryRun) {
            $evaluation = $this->evaluateRetention();
            $backupDir = realpath(config('backup.path', storage_path('app/backups')));

            $deleted = [];
            $deleteErrors = [];

            if (! $dryRun) {
                foreach ($evaluation['delete'] as $filename => $item) {
                    $zipPath = $item['path'];
                    $companionPath = $item['companion_path'];

                    // Verifikasi keamanan path sebelum unlink
                    if (! str_starts_with($zipPath, $backupDir.DIRECTORY_SEPARATOR)) {
                        $deleteErrors[$filename] = 'Security rejection: path outside backup directory';

                        continue;
                    }

                    $zipDeleted = false;
                    $companionDeleted = false;

                    try {
                        if (file_exists($zipPath)) {
                            $zipDeleted = File::delete($zipPath);
                        }

                        if ($companionPath && file_exists($companionPath)) {
                            $companionDeleted = File::delete($companionPath);
                        }

                        $deleted[] = [
                            'filename' => $filename,
                            'zip_deleted' => $zipDeleted,
                            'companion_deleted' => $companionDeleted,
                            'size_bytes' => $item['size_bytes'],
                        ];
                    } catch (\Throwable $e) {
                        $deleteErrors[$filename] = $e->getMessage();
                        Log::error("Failed to delete backup archive {$filename}: {$e->getMessage()}");
                    }
                }
            }

            return [
                'status' => 'SUCCESS',
                'dry_run' => $dryRun,
                'kept_count' => count($evaluation['keep']),
                'deleted_count' => $dryRun ? count($evaluation['delete']) : count($deleted),
                'skipped_count' => count($evaluation['skipped']),
                'kept' => $evaluation['keep'],
                'delete_candidates' => $evaluation['delete'],
                'deleted' => $deleted,
                'errors' => $deleteErrors,
                'skipped' => $evaluation['skipped'],
            ];
        }, function () {
            $reason = 'Backup prune skipped: another backup or restore process is currently running.';
            Log::info($reason);

            return [
                'status' => 'SKIPPED',
                'reason' => $reason,
                'kept_count' => 0,
                'deleted_count' => 0,
                'dry_run' => false,
            ];
        });
    }
}
