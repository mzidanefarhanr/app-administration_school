<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupService
{
    /**
     * Hook pengujian simulasi kegagalan dump untuk memverifikasi cleanup direktori temporer.
     *
     * @var (\Closure(string): void)|null
     */
    public static ?\Closure $testDumpHook = null;

    /**
     * Jalankan proses backup mandiri: dump database, lampiran privat, manifest,
     * enkripsi ZIP AES-256, dan berkas checksum SHA-256 pendamping.
     *
     * @param  string|null  $passphrase  Kunci enkripsi rahasia (min 12 karakter)
     * @param  string|null  $connectionName  Nama koneksi database sumber
     * @param  string|null  $attachmentsPath  Direktori lampiran privat
     * @return array<string, mixed> Metadata hasil backup
     */
    public function createBackup(?string $passphrase = null, ?string $connectionName = null, ?string $attachmentsPath = null): array
    {
        // 1. Verifikasi dukungan enkripsi ZIP AES-256 pada runtime PHP
        if (! class_exists(ZipArchive::class) || ! defined('ZipArchive::EM_AES_256')) {
            throw new \RuntimeException('Runtime error: ZipArchive with AES-256 encryption is not supported by PHP on this system.');
        }

        // 2. Ambil dan validasi passphrase (rahasia tidak boleh kosong, min 12 karakter)
        $passphrase = $passphrase ?: config('backup.encryption_key');
        if (empty($passphrase) || strlen($passphrase) < 12) {
            throw new \InvalidArgumentException('Backup encryption passphrase is required and must be at least 12 characters.');
        }

        $backupDir = config('backup.path', storage_path('app/backups'));
        File::ensureDirectoryExists($backupDir);

        // 3. Buat direktori temporer unik untuk atomic publishing
        $tempDir = $backupDir.DIRECTORY_SEPARATOR.'.tmp-'.Str::uuid();
        File::makeDirectory($tempDir, 0755, true);

        try {
            $connectionName = $connectionName ?: config('database.default');
            $connection = DB::connection($connectionName);

            $attachmentsSource = $attachmentsPath ?: config('backup.attachments_path', storage_path('app/attachments'));

            // 4. Deteksi konsistensi berkas lampiran: Pre-scan sebelum dump database dimulai
            $preScanAttachments = $this->scanAttachmentsDirectory($attachmentsSource);

            // 5. Dump database ke database.sql
            $dumpSqlPath = $tempDir.DIRECTORY_SEPARATOR.'database.sql';
            $this->dumpDatabase($connection, $dumpSqlPath, $tempDir);

            if (static::$testDumpHook !== null) {
                (static::$testDumpHook)($tempDir);
            }

            if (! file_exists($dumpSqlPath) || filesize($dumpSqlPath) === 0) {
                throw new \RuntimeException('Database dump failed: dump file is empty or missing.');
            }

            // 6. Deteksi konsistensi berkas lampiran: Post-scan sesudah dump selesai
            $postScanAttachments = $this->scanAttachmentsDirectory($attachmentsSource);
            if ($preScanAttachments !== $postScanAttachments) {
                throw new \RuntimeException('Consistency violation detected: Attachments were modified, added, or deleted during database dump. Backup publication aborted.');
            }

            $dumpSha256 = hash_file('sha256', $dumpSqlPath);

            // 7. Kumpulkan metadata tabel dan migration
            $tableStats = $this->collectTableStats($connection);
            $lastMigration = $this->getLastMigration($connection);

            // 8. Salin berkas lampiran privat ke folder temporer
            $attachmentsMeta = $this->collectAndCopyAttachments($attachmentsSource, $tempDir.DIRECTORY_SEPARATOR.'attachments');

            // 9. Susun manifest.json di dalam direktori temporer
            $envName = app()->environment();
            $timestampUtc = now('UTC')->toIso8601String();
            $timestampWib = now('Asia/Jakarta')->toIso8601String();

            $dbDriver = $connection->getDriverName();
            $dbName = (string) $connection->getDatabaseName();
            $dbmsVersion = $this->getDbmsVersion($connection);

            $manifestData = [
                'format_version' => '1.0',
                'app_name' => config('app.name', 'Laravel'),
                'app_version' => '1.0.0',
                'environment' => $envName,
                'created_at_utc' => $timestampUtc,
                'created_at_wib' => $timestampWib,
                'database' => [
                    'connection' => $connectionName,
                    'driver' => $dbDriver,
                    'database_name' => $dbName,
                    'dbms_version' => $dbmsVersion,
                    'dump_file' => 'database.sql',
                    'dump_sha256' => $dumpSha256,
                    'tables' => $tableStats,
                    'last_migration' => $lastMigration,
                ],
                'attachments' => [
                    'directory' => 'attachments',
                    'files_count' => count($attachmentsMeta),
                    'total_size_bytes' => array_sum(array_column($attachmentsMeta, 'size_bytes')),
                    'files' => $attachmentsMeta,
                ],
                'consistency' => [
                    'type' => $dbDriver === 'mysql' ? 'innodb_single_transaction_with_attachment_drift_detection' : 'sqlite_read_transaction_with_attachment_drift_detection',
                    'database_engine_verified' => $dbDriver === 'mysql' ? 'InnoDB' : 'SQLite',
                    'attachment_drift_detected' => false,
                    'operational_attachments_consistency' => 'Operational letter attachments are stored under the private attachments path and included in the filesystem scan; restore drill with letter attachments is NOT RUN.',
                    'prerequisites_for_production' => 'Application write quiescence (freeze writes) or filesystem-level snapshot required before operational attachments backup.',
                ],
            ];

            $manifestPath = $tempDir.DIRECTORY_SEPARATOR.'manifest.json';
            File::put($manifestPath, json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // 10. Kompres dan enkripsi ke file archive.zip di dalam folder temporer
            $tempZipPath = $tempDir.DIRECTORY_SEPARATOR.'archive.zip';
            $zip = new ZipArchive;
            if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Failed to create ZIP archive.');
            }

            $zip->setPassword($passphrase);

            // Tambahkan manifest.json terenkripsi
            $zip->addFile($manifestPath, 'manifest.json');
            $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256, $passphrase);

            // Tambahkan database.sql terenkripsi
            $zip->addFile($dumpSqlPath, 'database.sql');
            $zip->setEncryptionName('database.sql', ZipArchive::EM_AES_256, $passphrase);

            // Tambahkan lampiran terenkripsi
            if (! empty($attachmentsMeta)) {
                $attachmentsTempDir = $tempDir.DIRECTORY_SEPARATOR.'attachments';
                foreach ($attachmentsMeta as $fileMeta) {
                    $localPath = $attachmentsTempDir.DIRECTORY_SEPARATOR.$fileMeta['relative_path'];
                    $zipEntryName = 'attachments/'.$fileMeta['relative_path'];
                    $zip->addFile($localPath, $zipEntryName);
                    $zip->setEncryptionName($zipEntryName, ZipArchive::EM_AES_256, $passphrase);
                }
            }

            $zip->close();

            // 11. Atomic Publishing: pindahkan ke berkas final dan tulis checksum pendamping
            $finalZipSha256 = hash_file('sha256', $tempZipPath);
            $filenameTimestamp = now()->format('Ymd_His');
            $finalFilename = "backup-{$envName}-{$filenameTimestamp}.zip";
            $finalZipPath = $backupDir.DIRECTORY_SEPARATOR.$finalFilename;
            $companionShaPath = $finalZipPath.'.sha256';

            File::move($tempZipPath, $finalZipPath);
            File::put($companionShaPath, "{$finalZipSha256}  {$finalFilename}\n");

            return [
                'filename' => $finalFilename,
                'path' => $finalZipPath,
                'sha256' => $finalZipSha256,
                'companion_sha256_path' => $companionShaPath,
                'size_bytes' => filesize($finalZipPath),
                'manifest' => $manifestData,
            ];
        } finally {
            // Pembersihan fail-safe: direktori temporer selalu dibersihkan
            if (File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    /**
     * Dapatkan daftar seluruh arsip backup yang valid dan lengkap.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listBackups(): array
    {
        $backupDir = config('backup.path', storage_path('app/backups'));
        if (! File::isDirectory($backupDir)) {
            return [];
        }

        $files = File::files($backupDir);
        $results = [];

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Hanya arsip yang sesuai pola nama resmi
            if (! preg_match('/^backup-[a-zA-Z0-9._-]+\.zip$/', $filename)) {
                continue;
            }

            $fullPath = $file->getRealPath();
            $sha256 = null;
            $companionPath = $fullPath.'.sha256';

            if (file_exists($companionPath)) {
                $content = trim(File::get($companionPath));
                $parts = preg_split('/\s+/', $content);
                $sha256 = $parts[0] ?? null;
            }

            if (! $sha256) {
                $sha256 = hash_file('sha256', $fullPath);
            }

            $results[] = [
                'filename' => $filename,
                'size_bytes' => $file->getSize(),
                'created_at' => date('c', $file->getMTime()),
                'sha256' => $sha256,
            ];
        }

        usort($results, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $results;
    }

    /**
     * Validasi dan ambil absolute path berkas backup untuk diunduh secara aman.
     * Mencegah Directory Traversal, symlink keluar, dan akses berkas tidak sah.
     */
    public function getBackupFileForDownload(string $filename): string
    {
        // 1. Validasi format nama berkas
        if (! preg_match('/^backup-[a-zA-Z0-9._-]+\.zip$/', $filename)) {
            throw new \InvalidArgumentException('Invalid backup filename.');
        }

        // 2. Cegah path traversal
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new \InvalidArgumentException('Path traversal detected.');
        }

        $backupDir = realpath(config('backup.path', storage_path('app/backups')));
        if (! $backupDir || ! is_dir($backupDir)) {
            throw new \RuntimeException('Backup directory not found.');
        }

        $targetPath = $backupDir.DIRECTORY_SEPARATOR.$filename;
        $realTargetPath = realpath($targetPath);

        if (! $realTargetPath || ! is_file($realTargetPath)) {
            throw new \RuntimeException('Backup archive not found.');
        }

        // 3. Pastikan path absolut berada di dalam backupDir (anti-symlink escape)
        if (! str_starts_with($realTargetPath, $backupDir.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Security rejection: Unauthorized path resolution outside backup directory.');
        }

        return $realTargetPath;
    }

    /**
     * Scan dan kumpulkan peta path relatif => hash sha256 untuk deteksi deviasi.
     *
     * @return array<string, string>
     */
    public function scanAttachmentsDirectory(string $path): array
    {
        if (! File::isDirectory($path)) {
            return [];
        }

        $files = File::allFiles($path);
        $map = [];

        foreach ($files as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            $map[$relativePath] = hash_file('sha256', $file->getRealPath());
        }

        ksort($map);

        return $map;
    }

    /**
     * Dump database secara aman sesuai driver koneksi.
     */
    protected function dumpDatabase(Connection $connection, string $destinationPath, string $tempDir): void
    {
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $this->dumpMysql($connection, $destinationPath, $tempDir);
        } elseif ($driver === 'sqlite') {
            $this->dumpSqlite($connection, $destinationPath);
        } else {
            throw new \RuntimeException("Unsupported database driver for backup: {$driver}");
        }
    }

    /**
     * Dump MySQL/MariaDB menggunakan mysqldump dan file konfigurasi sementara.
     * Mencegah kredensial terlihat di process list baris perintah.
     */
    protected function dumpMysql(Connection $connection, string $destinationPath, string $tempDir): void
    {
        $config = $connection->getConfig();
        $dbName = $config['database'];
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $username = $config['username'] ?? 'root';
        $password = (string) ($config['password'] ?? '');

        // Verifikasi prasyarat engine tabel: seluruh tabel sumber harus menggunakan InnoDB
        $nonTransactional = $connection->select(
            "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND (ENGINE IS NULL OR UPPER(ENGINE) != 'INNODB')",
            [$dbName]
        );

        if (! empty($nonTransactional)) {
            $names = array_map(fn ($r) => ($r->TABLE_NAME ?? '').' ('.($r->ENGINE ?? 'NONE').')', $nonTransactional);
            throw new \RuntimeException('Consistency prerequisite failed: Non-InnoDB tables detected: '.implode(', ', $names).'. Single-transaction consistency cannot be guaranteed.');
        }

        $mysqldumpBinary = config('backup.mysqldump_binary', 'C:\xampp\mysql\bin\mysqldump.exe');
        if (! file_exists($mysqldumpBinary)) {
            $mysqldumpBinary = 'mysqldump';
        }

        // Tulis konfigurasi otentikasi sementara ke file my.cnf
        $cnfPath = $tempDir.DIRECTORY_SEPARATOR.'my.cnf';
        $cnfContent = "[client]\nuser={$username}\npassword=\"{$password}\"\nhost={$host}\nport={$port}\n";
        File::put($cnfPath, $cnfContent);

        try {
            $cmd = [
                $mysqldumpBinary,
                "--defaults-extra-file={$cnfPath}",
                '--single-transaction',
                '--quick',
                '--skip-comments',
                '--routines',
                '--triggers',
                $dbName,
            ];

            $process = new Process($cmd, null, $this->buildSubprocessEnv());
            $process->setTimeout(300);

            $dumpFileHandle = fopen($destinationPath, 'wb');
            if (! $dumpFileHandle) {
                throw new \RuntimeException('Failed to open dump file for writing.');
            }

            try {
                $process->run(function ($type, $buffer) use ($dumpFileHandle) {
                    if ($type === Process::OUT) {
                        fwrite($dumpFileHandle, $buffer);
                    }
                });
            } finally {
                fclose($dumpFileHandle);
            }

            if (! $process->isSuccessful()) {
                // Bersihkan pesan error dari kredensial
                $errorOutput = $process->getErrorOutput();
                $sanitizedError = preg_replace('/password.*$/mi', '[REDACTED]', $errorOutput);
                throw new \RuntimeException("mysqldump failed: {$sanitizedError}");
            }
        } finally {
            // Hapus file my.cnf seketika
            if (file_exists($cnfPath)) {
                File::delete($cnfPath);
            }
        }
    }

    /**
     * Dump SQLite secara mandiri melalui PDO query (skema dan data DDL/DML).
     */
    protected function dumpSqlite(Connection $connection, string $destinationPath): void
    {
        $pdo = $connection->getPdo();
        $sql = "-- SQLite Database Dump\nPRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n\n";

        // Ambil semua tabel kecuali internal sqlite
        $tables = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
            ->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($tables as $table) {
            $tableName = $table['name'];
            $createSql = $table['sql'];
            $sql .= "DROP TABLE IF EXISTS \"{$tableName}\";\n";
            $sql .= "{$createSql};\n";

            $rows = $pdo->query("SELECT * FROM \"{$tableName}\"")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $quotedCols = array_map(fn ($c) => "\"{$c}\"", $cols);
                $quotedVals = array_map(function ($val) use ($pdo) {
                    if ($val === null) {
                        return 'NULL';
                    }

                    return $pdo->quote((string) $val);
                }, array_values($row));

                $sql .= 'INSERT INTO "'.$tableName.'" ('.implode(', ', $quotedCols).') VALUES ('.implode(', ', $quotedVals).");\n";
            }
            $sql .= "\n";
        }

        $sql .= "COMMIT;\n";
        File::put($destinationPath, $sql);
    }

    /**
     * Kumpulkan metadata jumlah baris per tabel.
     *
     * @return array<string, int>
     */
    protected function collectTableStats(Connection $connection): array
    {
        $stats = [];
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $tables = $connection->select('SHOW TABLES');
            $dbName = $connection->getDatabaseName();
            $key = "Tables_in_{$dbName}";

            foreach ($tables as $table) {
                $tableName = $table->$key ?? array_values((array) $table)[0];
                $count = $connection->table($tableName)->count();
                $stats[$tableName] = $count;
            }
        } elseif ($driver === 'sqlite') {
            $tables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            foreach ($tables as $table) {
                $tableName = $table->name;
                $count = $connection->table($tableName)->count();
                $stats[$tableName] = $count;
            }
        }

        ksort($stats);

        return $stats;
    }

    /**
     * Ambil batch terakhir tabel migrations.
     */
    protected function getLastMigration(Connection $connection): ?string
    {
        try {
            $last = $connection->table('migrations')->orderByDesc('batch')->first();

            return $last ? "{$last->migration} (batch {$last->batch})" : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Salin berkas lampiran privat ke direktori temporer dan kumpulkan checksum-nya.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function collectAndCopyAttachments(string $sourcePath, string $destinationPath): array
    {
        if (! File::isDirectory($sourcePath)) {
            return [];
        }

        File::ensureDirectoryExists($destinationPath);
        $files = File::allFiles($sourcePath);
        $meta = [];

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $targetFilePath = $destinationPath.DIRECTORY_SEPARATOR.$relativePath;
            File::ensureDirectoryExists(dirname($targetFilePath));
            File::copy($file->getRealPath(), $targetFilePath);

            $meta[] = [
                'relative_path' => str_replace('\\', '/', $relativePath),
                'size_bytes' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
            ];
        }

        return $meta;
    }

    /**
     * Ambil versi DBMS tanpa mengekspos kredensial.
     */
    protected function getDbmsVersion(Connection $connection): string
    {
        try {
            $pdo = $connection->getPdo();

            return (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /**
     * Bangun variabel lingkungan (environment) untuk subprocess mysqldump.
     * Di Windows (khususnya saat dijalankan via Apache/Web Server), variabel SystemRoot
     * sering tidak terwariskan ke proc_open sehingga Winsock gagal membuat socket TCP/IP (Error 10106).
     *
     * @return array<string, string>
     */
    protected function buildSubprocessEnv(): array
    {
        $rawEnv = getenv();
        $env = is_array($rawEnv) ? $rawEnv : [];

        if (PHP_OS_FAMILY === 'Windows') {
            $systemRoot = $_SERVER['SystemRoot'] ?? $_SERVER['SYSTEMROOT'] ?? $_SERVER['WINDIR'] ?? 'C:\\Windows';
            foreach ($env as $k => $v) {
                if (strcasecmp((string) $k, 'SystemRoot') === 0 && is_string($v) && trim($v) !== '') {
                    $systemRoot = $v;
                    unset($env[$k]);
                }
                if (strcasecmp((string) $k, 'WINDIR') === 0) {
                    unset($env[$k]);
                }
            }

            $env['SystemRoot'] = (string) $systemRoot;
            $env['WINDIR'] = (string) $systemRoot;
        }

        return $env;
    }
}
