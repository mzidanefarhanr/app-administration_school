<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class BackupMutex
{
    /**
     * Jalankan callback di bawah perlindungan file lock atomik (non-blocking).
     * Mencegah overlap antara backup manual, backup terjadwal, dan rotasi retensi.
     * Batasan: File lock berlaku untuk proses pada mesin/server yang sama (single-server).
     *
     * @template T
     *
     * @param  callable(): T  $callback  Aksi yang dijalankan jika lock berhasil diperoleh
     * @param  (callable(): void)|null  $onLocked  Aksi jika lock sedang dipakai proses lain
     * @return T|null
     */
    public static function withLock(callable $callback, ?callable $onLocked = null)
    {
        $lockFilePath = config('backup.lock_file', storage_path('app/backups/.backup.lock'));
        File::ensureDirectoryExists(dirname($lockFilePath));

        $fp = fopen($lockFilePath, 'c+');
        if (! $fp) {
            throw new \RuntimeException('Failed to open backup lock file.');
        }

        try {
            $acquired = flock($fp, LOCK_EX | LOCK_NB);

            if (! $acquired) {
                if ($onLocked !== null) {
                    return $onLocked();
                }

                return null;
            }

            try {
                // Tulis PID proses saat ini ke lock file untuk observabilitas
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, (string) getmypid());
                fflush($fp);

                return $callback();
            } finally {
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
    }

    /**
     * Periksa apakah proses backup/rotasi saat ini sedang terkunci/berjalan.
     */
    public static function isLocked(): bool
    {
        $lockFilePath = config('backup.lock_file', storage_path('app/backups/.backup.lock'));
        if (! file_exists($lockFilePath)) {
            return false;
        }

        $fp = fopen($lockFilePath, 'c+');
        if (! $fp) {
            return false;
        }

        try {
            $acquired = flock($fp, LOCK_EX | LOCK_NB);
            if ($acquired) {
                flock($fp, LOCK_UN);

                return false;
            }

            return true;
        } finally {
            fclose($fp);
        }
    }
}
