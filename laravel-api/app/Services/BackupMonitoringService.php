<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class BackupMonitoringService
{
    /**
     * Dapatkan path file monitoring terstruktur.
     */
    public function getMonitoringFilePath(): string
    {
        return config('backup.monitoring_file', storage_path('app/backups/monitoring.json'));
    }

    /**
     * Baca data monitoring mentah dari file.
     *
     * @return array<string, mixed>
     */
    public function readRawData(): array
    {
        $filePath = $this->getMonitoringFilePath();

        if (! file_exists($filePath)) {
            return [
                'updated_at' => null,
                'timezone' => config('backup.schedule_timezone', 'Asia/Jakarta'),
                'last_attempt' => null,
                'last_success' => null,
                'last_verified_drill' => null,
            ];
        }

        try {
            $content = File::get($filePath);
            $data = json_decode($content, true);

            if (! is_array($data)) {
                return [
                    'updated_at' => null,
                    'timezone' => config('backup.schedule_timezone', 'Asia/Jakarta'),
                    'last_attempt' => null,
                    'last_success' => null,
                    'last_verified_drill' => null,
                    '_corrupted' => true,
                ];
            }

            return $data;
        } catch (\Throwable $e) {
            return [
                'updated_at' => null,
                'timezone' => config('backup.schedule_timezone', 'Asia/Jakarta'),
                'last_attempt' => null,
                'last_success' => null,
                'last_verified_drill' => null,
                '_corrupted' => true,
            ];
        }
    }

    /**
     * Simpan data monitoring secara atomik.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveRawData(array $data): void
    {
        $filePath = $this->getMonitoringFilePath();
        File::ensureDirectoryExists(dirname($filePath));

        $data['updated_at'] = now(config('backup.schedule_timezone', 'Asia/Jakarta'))->toIso8601String();
        $data['timezone'] = config('backup.schedule_timezone', 'Asia/Jakarta');

        $tempPath = $filePath.'.tmp-'.uniqid();
        File::put($tempPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        File::move($tempPath, $filePath);
    }

    /**
     * Catat keberhasilan backup baru.
     *
     * @param  array<string, mixed>  $backupResult
     */
    public function recordBackupSuccess(array $backupResult, float $durationSeconds): void
    {
        $data = $this->readRawData();
        unset($data['_corrupted']);

        $timestamp = now(config('backup.schedule_timezone', 'Asia/Jakarta'))->toIso8601String();

        $attemptInfo = [
            'timestamp' => $timestamp,
            'result' => 'SUCCESS',
            'duration_seconds' => round($durationSeconds, 2),
            'archive_file' => $backupResult['filename'],
            'error_message' => null,
        ];

        $successInfo = [
            'timestamp' => $timestamp,
            'duration_seconds' => round($durationSeconds, 2),
            'archive_file' => $backupResult['filename'],
            'size_bytes' => $backupResult['size_bytes'] ?? 0,
            'sha256' => $backupResult['sha256'] ?? null,
        ];

        $data['last_attempt'] = $attemptInfo;
        $data['last_success'] = $successInfo;

        $this->saveRawData($data);
    }

    /**
     * Catat kegagalan proses backup.
     * PENTING: Kegagalan baru TIDAK BOLEH menimpa data last_success!
     */
    public function recordBackupFailure(string $errorMessage, float $durationSeconds): void
    {
        $data = $this->readRawData();
        unset($data['_corrupted']);

        // Sanitasi pesan error agar tidak membocorkan password atau kredensial
        $sanitizedError = preg_replace('/(password|passphrase|secret)[^,\n]*/i', '$1 [REDACTED]', $errorMessage);

        $data['last_attempt'] = [
            'timestamp' => now(config('backup.schedule_timezone', 'Asia/Jakarta'))->toIso8601String(),
            'result' => 'FAILED',
            'duration_seconds' => round($durationSeconds, 2),
            'archive_file' => null,
            'error_message' => $sanitizedError,
        ];

        $this->saveRawData($data);
    }

    /**
     * Catat proses backup yang dilewati (skipped) karena mutex lock aktif.
     */
    public function recordBackupSkipped(string $reason): void
    {
        $data = $this->readRawData();
        unset($data['_corrupted']);

        $data['last_attempt'] = [
            'timestamp' => now(config('backup.schedule_timezone', 'Asia/Jakarta'))->toIso8601String(),
            'result' => 'SKIPPED',
            'duration_seconds' => 0.0,
            'archive_file' => null,
            'error_message' => $reason,
        ];

        $this->saveRawData($data);
    }

    /**
     * Catat hasil verifikasi restore drill.
     *
     * @param  array<string, mixed>  $drillReport
     */
    public function recordDrillResult(array $drillReport): void
    {
        $data = $this->readRawData();
        unset($data['_corrupted']);

        $data['last_verified_drill'] = [
            'timestamp' => $drillReport['timestamp'] ?? now(config('backup.schedule_timezone', 'Asia/Jakarta'))->toIso8601String(),
            'result' => $drillReport['status'] ?? 'PASS',
            'duration_seconds' => $drillReport['duration_seconds'] ?? 0.0,
            'target_database' => $drillReport['target_database'] ?? null,
            'verified_archive' => $drillReport['archive_file'] ?? null,
            'verified_sha256' => $drillReport['archive_sha256'] ?? null,
        ];

        $this->saveRawData($data);
    }

    /**
     * Hitung status kesehatan terkini (freshness detection) saat status dibaca.
     * Tidak mengasumsikan sehat secara default.
     *
     * @return array<string, mixed>
     */
    public function getHealthStatus(): array
    {
        $data = $this->readRawData();
        $tz = config('backup.schedule_timezone', 'Asia/Jakarta');
        $maxAgeHours = config('backup.max_age_hours', 28);
        $backupDir = config('backup.path', storage_path('app/backups'));

        $lastSuccess = $data['last_success'] ?? null;
        $lastAttempt = $data['last_attempt'] ?? null;
        $lastDrill = $data['last_verified_drill'] ?? null;

        $isCorrupted = ! empty($data['_corrupted']);

        $status = 'HEALTHY';
        $statusMessage = 'Backup system is operating normally within threshold.';
        $hoursSinceLastSuccess = null;
        $isOverdue = false;
        $backupFileExists = false;

        if ($isCorrupted) {
            $status = 'METADATA_CORRUPTED';
            $statusMessage = 'Monitoring metadata file is corrupted or cannot be parsed.';
        } elseif (! $lastSuccess) {
            $status = 'NEVER_BACKED_UP';
            $statusMessage = 'No successful backup has been recorded yet.';
        } else {
            // Periksa keberadaan fisik berkas cadangan terakhir yang berhasil
            $archiveFile = $lastSuccess['archive_file'] ?? '';
            $archivePath = $backupDir.DIRECTORY_SEPARATOR.$archiveFile;
            $backupFileExists = $archiveFile !== '' && file_exists($archivePath);

            if (! $backupFileExists) {
                $status = 'ARCHIVE_MISSING';
                $statusMessage = "Last recorded successful backup file '{$archiveFile}' is missing from storage.";
            } else {
                $lastSuccessTime = Carbon::parse($lastSuccess['timestamp'], $tz);
                $currentTime = now($tz);
                $hoursSinceLastSuccess = round($lastSuccessTime->diffInMinutes($currentTime) / 60, 2);

                if ($hoursSinceLastSuccess > $maxAgeHours) {
                    $status = 'OVERDUE';
                    $statusMessage = "Backup is overdue: {$hoursSinceLastSuccess} hours elapsed since last successful backup (threshold: {$maxAgeHours} hours).";
                    $isOverdue = true;
                } elseif (($lastAttempt['result'] ?? '') === 'FAILED') {
                    $status = 'DEGRADED';
                    $statusMessage = 'Last backup attempt failed, though a valid previous backup exists within threshold.';
                }
            }
        }

        // Pembedaan tegas backup_created vs restore_verified
        $backupCreated = $lastSuccess !== null && $backupFileExists;

        $restoreVerified = false;
        $verifiedArchive = null;
        if ($lastDrill && ($lastDrill['result'] ?? '') === 'PASS') {
            $verifiedArchive = $lastDrill['verified_archive'] ?? null;
            $restoreVerified = true;
        }

        return [
            'status' => $status,
            'message' => $statusMessage,
            'timezone' => $tz,
            'checked_at' => now($tz)->toIso8601String(),
            'schedule_enabled' => (bool) config('backup.schedule_enabled', false),
            'schedule_backup_time' => config('backup.schedule_backup_time', '02:00'),
            'schedule_prune_time' => config('backup.schedule_prune_time', '02:30'),
            'hours_since_last_success' => $hoursSinceLastSuccess,
            'max_allowed_age_hours' => $maxAgeHours,
            'is_overdue' => $isOverdue,
            'backup_created' => $backupCreated,
            'restore_verified' => $restoreVerified,
            'verified_archive' => $verifiedArchive,
            'last_attempt' => $lastAttempt,
            'last_success' => $lastSuccess,
            'last_verified_drill' => $lastDrill,
            'notice' => 'Passive monitoring: requires periodic administrator inspection. No automated external notification is dispatched.',
        ];
    }
}
