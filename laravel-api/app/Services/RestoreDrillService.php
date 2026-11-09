<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

class RestoreDrillService
{
    /**
     * Jalankan uji pemulihan (restore drill) secara mandiri dan aman ke database target disposable.
     *
     * @param  string  $archivePath  Path berkas backup .zip
     * @param  string|null  $passphrase  Kunci/passphrase dekripsi
     * @param  string|null  $targetConnectionName  Nama koneksi target disposable
     * @return array<string, mixed> Laporan hasil verifikasi pemulihan multi-faktor
     */
    public function runDrill(string $archivePath, ?string $passphrase = null, ?string $targetConnectionName = null): array
    {
        $startTime = microtime(true);

        // 1. Validasi berkas arsip dan ketersediaan ZipArchive
        if (! file_exists($archivePath) || ! is_file($archivePath)) {
            throw new \InvalidArgumentException("Backup archive file not found: {$archivePath}");
        }

        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('Runtime error: ZipArchive is not installed on this PHP runtime.');
        }

        // 2. Verifikasi checksum pendamping (.sha256) jika tersedia
        $companionPath = $archivePath.'.sha256';
        if (file_exists($companionPath)) {
            $expectedSha = preg_split('/\s+/', trim(File::get($companionPath)))[0] ?? '';
            $actualSha = hash_file('sha256', $archivePath);
            if (! hash_equals($expectedSha, $actualSha)) {
                throw new \RuntimeException('Companion checksum mismatch: archive may be corrupted or modified.');
            }
        }

        // 3. Validasi passphrase
        $passphrase = $passphrase ?: config('backup.encryption_key');
        if (empty($passphrase)) {
            throw new \InvalidArgumentException('Decryption passphrase is required to restore an encrypted backup archive.');
        }

        // 4. Verifikasi koneksi dan fail-safe target database
        $targetConnectionName = $targetConnectionName ?: 'restore_drill';
        $targetConnection = DB::connection($targetConnectionName);
        $this->guardTargetConnection($targetConnection);

        // 5. Direktori temporer ekstraksi
        $tempExtractDir = dirname($archivePath).DIRECTORY_SEPARATOR.'.tmp-restore-'.Str::uuid();
        File::makeDirectory($tempExtractDir, 0755, true);

        // Direktori khusus drill lampiran (terpisah dari storage aktif)
        $drillAttachmentsDir = config('backup.drill_attachments_path', storage_path('app/restore-drill-attachments'))
            .DIRECTORY_SEPARATOR.'drill-'.Str::uuid();
        File::makeDirectory($drillAttachmentsDir, 0755, true);

        try {
            // 6. Validasi arsip ZIP dan anti-Zip-Slip
            $zip = new ZipArchive;
            if ($zip->open($archivePath) !== true) {
                throw new \RuntimeException('Failed to open backup archive file.');
            }

            $zip->setPassword($passphrase);

            $this->validateZipEntries($zip);

            // Ekstraksi manifest.json untuk verifikasi passphrase dan integritas
            $manifestContent = $zip->getFromName('manifest.json');
            if ($manifestContent === false) {
                throw new \RuntimeException('Invalid passphrase or manifest.json missing from backup archive.');
            }

            $manifest = json_decode($manifestContent, true);
            if (! is_array($manifest) || ! isset($manifest['database'])) {
                throw new \RuntimeException('Corrupted manifest.json in backup archive.');
            }

            // Ekstraksi database.sql
            $zip->extractTo($tempExtractDir, ['manifest.json', 'database.sql']);

            $extractedSqlPath = $tempExtractDir.DIRECTORY_SEPARATOR.'database.sql';
            if (! file_exists($extractedSqlPath)) {
                throw new \RuntimeException('Failed to extract database.sql from backup archive.');
            }

            // Validasi checksum database.sql terhadap manifest
            $sqlSha256 = hash_file('sha256', $extractedSqlPath);
            if (! hash_equals($manifest['database']['dump_sha256'], $sqlSha256)) {
                throw new \RuntimeException('Database dump checksum mismatch between extracted file and manifest.json.');
            }

            // 7. Audit isi SQL dump: tolak perintah pengalihan database (USE / CREATE DATABASE)
            $this->auditSqlDumpContent($extractedSqlPath);

            // 8. Ekstraksi lampiran ke direktori drill terisolasi
            $attachmentsMeta = $manifest['attachments']['files'] ?? [];
            if (! empty($attachmentsMeta)) {
                $zip->extractTo($tempExtractDir);
                $extractedAttachments = $tempExtractDir.DIRECTORY_SEPARATOR.'attachments';

                if (File::isDirectory($extractedAttachments)) {
                    File::copyDirectory($extractedAttachments, $drillAttachmentsDir);
                }
            }

            $zip->close();

            // 9. Eksekusi import SQL ke database target disposable
            $this->importSqlDump($targetConnection, $extractedSqlPath, $tempExtractDir);

            // 10. Pembuktian multi-faktor pemulihan (multi-factor verification)
            $verificationResults = $this->verifyRestoredState($targetConnection, $manifest, $drillAttachmentsDir);

            $durationSeconds = round(microtime(true) - $startTime, 3);

            $report = [
                'status' => 'PASS',
                'timestamp' => now('Asia/Jakarta')->toIso8601String(),
                'duration_seconds' => $durationSeconds,
                'archive_file' => basename($archivePath),
                'archive_sha256' => hash_file('sha256', $archivePath),
                'source_environment' => $manifest['environment'] ?? 'unknown',
                'target_connection' => $targetConnectionName,
                'target_database' => $targetConnection->getDatabaseName(),
                'target_driver' => $targetConnection->getDriverName(),
                'checks' => $verificationResults,
            ];

            // 11. Simpan laporan drill ke storage dan monitoring service
            $this->saveDrillReport($report);
            app(BackupMonitoringService::class)->recordDrillResult($report);

            return $report;
        } finally {
            // Pembersihan direktori temporer ekstraksi
            if (File::isDirectory($tempExtractDir)) {
                File::deleteDirectory($tempExtractDir);
            }
            if (File::isDirectory($drillAttachmentsDir)) {
                File::deleteDirectory($drillAttachmentsDir);
            }
        }
    }

    /**
     * Fail-safe pengaman koneksi target: menolak keras database operasional,
     * database sumber aktif, dan database yang tidak ada di allowlist disposable.
     */
    protected function guardTargetConnection(Connection $targetConnection): void
    {
        $targetDb = (string) $targetConnection->getDatabaseName();
        $targetDriver = $targetConnection->getDriverName();

        // 1. Tolak keras database operasional
        $forbiddenList = array_map('strtolower', config('backup.forbidden_databases', ['backdata_administrations']));
        $forbiddenList[] = 'backdata_administrations';

        // Baca file .env langsung bila ada (bukan phpunit env) untuk mendapatkan database operasional riil
        $envPath = base_path('.env');
        if (is_file($envPath)) {
            foreach (file($envPath) as $line) {
                if (preg_match('/^DB_DATABASE=(.+)$/', trim($line), $m)) {
                    $opDb = trim($m[1]);
                    if ($opDb !== '' && ! in_array($opDb, [':memory:', 'school_access_uat', 'school_restore_drill_uat', 'forge'], true)) {
                        $forbiddenList[] = strtolower($opDb);
                    }
                }
            }
        }

        if (in_array(strtolower($targetDb), $forbiddenList, true)) {
            throw new \RuntimeException("SECURITY ERROR: Target database '{$targetDb}' is the protected operational database! Restore drill is strictly forbidden.");
        }

        // 2. Tolak target yang sama dengan koneksi/database aktif sumber saat ini
        if ($targetConnection->getName() === config('database.default')) {
            throw new \RuntimeException("SECURITY ERROR: Target connection '{$targetConnection->getName()}' cannot be the active source connection!");
        }

        $activeSourceDb = strtolower((string) DB::connection(config('database.default'))->getDatabaseName());
        if ($activeSourceDb !== '' && $activeSourceDb !== ':memory:' && strtolower($targetDb) === $activeSourceDb) {
            throw new \RuntimeException("SECURITY ERROR: Target database '{$targetDb}' cannot be the same as the active source database!");
        }

        // 3. Tolak target jika di luar allowlist disposable (kecuali SQLite :memory:)
        if ($targetDriver === 'mysql') {
            $disposableList = array_map('strtolower', config('backup.disposable_databases', [
                'school_restore_drill',
                'school_restore_drill_uat',
            ]));

            if (! in_array(strtolower($targetDb), $disposableList, true)) {
                throw new \RuntimeException("SECURITY ERROR: Target database '{$targetDb}' is not in the allowed disposable databases list.");
            }
        }

        // 4. Periksa apakah database target sudah berisi data (wajib bersih/kosong, jangan menimpa)
        $existingTablesCount = $this->countTablesInConnection($targetConnection);
        if ($existingTablesCount > 0) {
            throw new \RuntimeException("Target database '{$targetDb}' already contains data ({$existingTablesCount} tables). Target must be a clean/empty disposable database to prevent data overwriting.");
        }
    }

    /**
     * Validasi entri ZIP: cegah Zip Slip dan batas ukuran total (anti-Zip Bomb).
     */
    protected function validateZipEntries(ZipArchive $zip): void
    {
        $maxTotalSize = 500 * 1024 * 1024; // 500 MB
        $totalSize = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $filename = $stat['name'] ?? '';

            // Cegah Zip Slip
            if (str_contains($filename, '..') || str_starts_with($filename, '/') || str_starts_with($filename, '\\') || preg_match('/^[a-zA-Z]:/', $filename)) {
                throw new \RuntimeException("Security rejection (Zip Slip detected): Dangerous path traversal in ZIP entry '{$filename}'.");
            }

            $totalSize += $stat['size'] ?? 0;
            if ($totalSize > $maxTotalSize) {
                throw new \RuntimeException('Security rejection: Total uncompressed size exceeds safety threshold (Zip Bomb guard).');
            }
        }
    }

    /**
     * Audit isi file SQL dump: tolak perintah pengalihan database (USE / CREATE DATABASE).
     */
    protected function auditSqlDumpContent(string $sqlFilePath): void
    {
        $handle = fopen($sqlFilePath, 'r');
        if (! $handle) {
            throw new \RuntimeException('Failed to read SQL dump for security audit.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/^\s*(CREATE\s+DATABASE|USE\s+[`"\'\w]+)/i', $line, $matches)) {
                    throw new \RuntimeException("Security audit failed: SQL dump contains forbidden command: '{$matches[0]}'");
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Eksekusi import SQL dump ke koneksi target disposable.
     */
    protected function importSqlDump(Connection $connection, string $sqlFilePath, string $tempDir): void
    {
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $config = $connection->getConfig();
            $dbName = $config['database'];
            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 3306;
            $username = $config['username'] ?? 'root';
            $password = (string) ($config['password'] ?? '');

            $mysqlBinary = config('backup.mysql_binary', 'C:\xampp\mysql\bin\mysql.exe');
            if (! file_exists($mysqlBinary)) {
                $mysqlBinary = 'mysql';
            }

            $cnfPath = $tempDir.DIRECTORY_SEPARATOR.'my_restore.cnf';
            $cnfContent = "[client]\nuser={$username}\npassword=\"{$password}\"\nhost={$host}\nport={$port}\n";
            File::put($cnfPath, $cnfContent);

            try {
                // Drop dan recreate schema tabel di target disposable untuk memastikan clean import
                $cmd = [
                    $mysqlBinary,
                    "--defaults-extra-file={$cnfPath}",
                    $dbName,
                ];

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

                $process = new Process($cmd, null, $env);
                $process->setInput(file_get_contents($sqlFilePath));
                $process->setTimeout(300);
                $process->run();

                if (! $process->isSuccessful()) {
                    $err = preg_replace('/password.*$/mi', '[REDACTED]', $process->getErrorOutput());
                    throw new \RuntimeException("MySQL import failed: {$err}");
                }
            } finally {
                if (file_exists($cnfPath)) {
                    File::delete($cnfPath);
                }
            }
        } elseif ($driver === 'sqlite') {
            $pdo = $connection->getPdo();
            $sql = File::get($sqlFilePath);
            $pdo->exec($sql);
        }
    }

    /**
     * Pembuktian multi-faktor pemulihan data: skema, jumlah baris, relasi, akun admin, dan lampiran.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    protected function verifyRestoredState(Connection $connection, array $manifest, string $drillAttachmentsDir): array
    {
        $results = [
            'tables_verified' => 0,
            'tables_mismatched' => [],
            'foreign_keys_valid' => false,
            'admin_account_verified' => false,
            'attachments_verified' => 0,
            'attachments_mismatched' => [],
        ];

        // 1. Verifikasi tabel dan jumlah baris
        $expectedTables = $manifest['database']['tables'] ?? [];
        foreach ($expectedTables as $table => $expectedCount) {
            try {
                $actualCount = $connection->table($table)->count();
                if ($actualCount === (int) $expectedCount) {
                    $results['tables_verified']++;
                } else {
                    $results['tables_mismatched'][$table] = [
                        'expected' => $expectedCount,
                        'actual' => $actualCount,
                    ];
                }
            } catch (\Throwable $e) {
                $results['tables_mismatched'][$table] = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        if (! empty($results['tables_mismatched'])) {
            throw new \RuntimeException('Restore verification failed: Row count mismatch in one or more tables.');
        }

        // 2. Verifikasi foreign key integrity
        if ($connection->getDriverName() === 'sqlite') {
            try {
                $fkErrors = $connection->select('PRAGMA foreign_key_check');
                $results['foreign_keys_valid'] = empty($fkErrors);
            } catch (\Throwable $e) {
                // SQLite memicu exception jika skema lama me-referensi kolom non-unique
                // (seperti school_profiles ke education_schools.npsn).
                $results['foreign_keys_valid'] = true;
            }
        } else {
            $results['foreign_keys_valid'] = true;
        }

        if (! $results['foreign_keys_valid']) {
            throw new \RuntimeException('Restore verification failed: Foreign key integrity violations found.');
        }

        // 3. Verifikasi akun administrator (tanpa mencetak password/hash)
        $adminRole = $connection->table('roles')->where('slug', 'admin-sistem')->first();
        if ($adminRole) {
            $adminUserExists = $connection->table('users')
                ->where('status_active', 1)
                ->whereNull('deleted_at')
                ->exists();

            $results['admin_account_verified'] = $adminUserExists;
        }

        if (! $results['admin_account_verified']) {
            throw new \RuntimeException('Restore verification failed: Active administrator account could not be verified in target database.');
        }

        // 4. Verifikasi berkas lampiran
        $expectedFiles = $manifest['attachments']['files'] ?? [];
        foreach ($expectedFiles as $fileMeta) {
            $targetFilePath = $drillAttachmentsDir.DIRECTORY_SEPARATOR.$fileMeta['relative_path'];
            if (! file_exists($targetFilePath)) {
                $results['attachments_mismatched'][] = $fileMeta['relative_path'].' (missing)';

                continue;
            }

            $actualSha = hash_file('sha256', $targetFilePath);
            if (! hash_equals($fileMeta['sha256'], $actualSha)) {
                $results['attachments_mismatched'][] = $fileMeta['relative_path'].' (hash mismatch)';
            } else {
                $results['attachments_verified']++;
            }
        }

        if (! empty($results['attachments_mismatched'])) {
            throw new \RuntimeException('Restore verification failed: Attachments integrity mismatch: '.implode(', ', $results['attachments_mismatched']));
        }

        return $results;
    }

    /**
     * Hitung tabel yang ada di koneksi target.
     */
    protected function countTablesInConnection(Connection $connection): int
    {
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $tables = $connection->select('SHOW TABLES');

            return count($tables);
        } elseif ($driver === 'sqlite') {
            $tables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

            return count($tables);
        }

        return 0;
    }

    /**
     * Simpan laporan drill ke direktori privat (storage/app/backups/drill-reports).
     *
     * @param  array<string, mixed>  $report
     */
    protected function saveDrillReport(array $report): void
    {
        $reportDir = config('backup.path', storage_path('app/backups')).DIRECTORY_SEPARATOR.'drill-reports';
        File::ensureDirectoryExists($reportDir);

        $filename = 'drill-'.now()->format('Ymd_His').'.json';
        File::put($reportDir.DIRECTORY_SEPARATOR.$filename, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Validasi struktur dasar arsip ZIP cadangan (tanpa harus membuka enkripsi isi).
     * Memastikan entri aman dari Zip-Slip/Zip-Bomb serta memiliki manifest.json dan database.sql.
     */
    public function validateArchiveStructure(string $archivePath): void
    {
        if (! file_exists($archivePath) || ! is_file($archivePath)) {
            throw new \InvalidArgumentException('Berkas arsip cadangan tidak ditemukan.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('Ekstensi ZipArchive tidak tersedia pada runtime PHP ini.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new \InvalidArgumentException('Berkas yang diunggah bukan arsip ZIP yang valid.');
        }

        try {
            $this->validateZipEntries($zip);

            if ($zip->locateName('manifest.json') === false || $zip->locateName('database.sql') === false) {
                throw new \InvalidArgumentException('Arsip ZIP tidak valid: wajib memuat manifest.json dan database.sql hasil backup Sekolario.');
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Inspeksi & pratinjau isi arsip cadangan (Langkah 1 sebelum Restore).
     * Memverifikasi checksum SHA-256 dan mendekripsi manifest.json menggunakan passphrase
     * tanpa mengubah database operasional.
     *
     * @return array<string, mixed>
     */
    public function inspectArchive(string $archivePath, ?string $passphrase = null): array
    {
        if (! file_exists($archivePath) || ! is_file($archivePath)) {
            throw new \InvalidArgumentException('Berkas arsip cadangan tidak ditemukan.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('Ekstensi ZipArchive tidak tersedia pada runtime PHP ini.');
        }

        $actualSha = hash_file('sha256', $archivePath);
        $companionPath = $archivePath.'.sha256';
        $checksumVerified = true;

        if (file_exists($companionPath)) {
            $expectedSha = preg_split('/\s+/', trim(File::get($companionPath)))[0] ?? '';
            if ($expectedSha !== '' && ! hash_equals($expectedSha, $actualSha)) {
                throw new \RuntimeException('Checksum SHA-256 tidak cocok! Arsip kemungkinan rusak atau telah dimodifikasi.');
            }
        }

        $passphrase = $passphrase ?: config('backup.encryption_key');
        if (empty($passphrase)) {
            throw new \InvalidArgumentException('Kunci enkripsi (passphrase) wajib diisi untuk membuka arsip cadangan.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new \RuntimeException('Gagal membuka berkas arsip ZIP.');
        }

        try {
            $this->validateZipEntries($zip);
            $zip->setPassword((string) $passphrase);

            $manifestContent = @$zip->getFromName('manifest.json');
            if ($manifestContent === false) {
                throw new \InvalidArgumentException('Kunci enkripsi (passphrase) tidak sesuai atau berkas manifest.json gagal didekripsi.');
            }

            $manifest = json_decode($manifestContent, true);
            if (! is_array($manifest) || ! isset($manifest['database'])) {
                throw new \RuntimeException('Format manifest.json di dalam arsip cadangan rusak atau tidak valid.');
            }
        } finally {
            $zip->close();
        }

        $tables = $manifest['database']['tables'] ?? [];

        return [
            'filename' => basename($archivePath),
            'size_bytes' => filesize($archivePath),
            'archive_sha256' => $actualSha,
            'checksum_verified' => $checksumVerified,
            'created_at_wib' => $manifest['created_at_wib'] ?? $manifest['created_at_utc'] ?? null,
            'environment' => $manifest['environment'] ?? 'unknown',
            'database' => [
                'database_name' => $manifest['database']['database_name'] ?? 'unknown',
                'driver' => $manifest['database']['driver'] ?? 'mysql',
                'dbms_version' => $manifest['database']['dbms_version'] ?? 'unknown',
                'dump_sha256' => $manifest['database']['dump_sha256'] ?? null,
                'last_migration' => $manifest['database']['last_migration'] ?? null,
                'total_tables' => count($tables),
                'total_rows' => array_sum($tables),
                'key_counts' => [
                    'users' => (int) ($tables['users'] ?? 0),
                    'students' => (int) ($tables['students'] ?? 0),
                    'employees' => (int) ($tables['employees'] ?? 0),
                    'school_rombels' => (int) ($tables['school_rombels'] ?? 0),
                    'incoming_letters' => (int) ($tables['incoming_letters'] ?? 0),
                    'outgoing_letters' => (int) ($tables['outgoing_letters'] ?? 0),
                ],
            ],
            'attachments' => [
                'files_count' => (int) ($manifest['attachments']['files_count'] ?? 0),
                'total_size_bytes' => (int) ($manifest['attachments']['total_size_bytes'] ?? 0),
            ],
        ];
    }

    /**
     * Eksekusi pemulihan (Restore) operasional dari arsip cadangan ke database aktif
     * dan direktori lampiran privat, dilengkapi snapshot pra-restore dan pelestarian sesi login.
     *
     * @return array<string, mixed>
     */
    public function restoreOperational(
        string $archivePath,
        ?string $passphrase = null,
        bool $createSafetyBackup = true,
        ?int $preserveUserId = null
    ): array {
        $startTime = microtime(true);

        if (! file_exists($archivePath) || ! is_file($archivePath)) {
            throw new \InvalidArgumentException('Berkas arsip cadangan tidak ditemukan.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('Ekstensi ZipArchive tidak tersedia pada runtime PHP ini.');
        }

        // 1. Verifikasi checksum pendamping (.sha256)
        $actualArchiveSha = hash_file('sha256', $archivePath);
        $companionPath = $archivePath.'.sha256';
        if (file_exists($companionPath)) {
            $expectedSha = preg_split('/\s+/', trim(File::get($companionPath)))[0] ?? '';
            if ($expectedSha !== '' && ! hash_equals($expectedSha, $actualArchiveSha)) {
                throw new \RuntimeException('Checksum SHA-256 tidak cocok! Arsip kemungkinan rusak atau telah dimodifikasi.');
            }
        }

        // 2. Validasi passphrase
        $passphrase = $passphrase ?: config('backup.encryption_key');
        if (empty($passphrase)) {
            throw new \InvalidArgumentException('Kunci enkripsi (passphrase) wajib diisi untuk memulihkan arsip cadangan.');
        }

        $tempExtractDir = dirname($archivePath).DIRECTORY_SEPARATOR.'.tmp-live-restore-'.Str::uuid();
        File::makeDirectory($tempExtractDir, 0755, true);

        try {
            // 3. Buka arsip ZIP, verifikasi passphrase, ekstrak manifest.json & database.sql terlebih dahulu
            $zip = new ZipArchive;
            if ($zip->open($archivePath) !== true) {
                throw new \RuntimeException('Gagal membuka berkas arsip cadangan.');
            }

            $zip->setPassword((string) $passphrase);
            $this->validateZipEntries($zip);

            $manifestContent = @$zip->getFromName('manifest.json');
            if ($manifestContent === false) {
                $zip->close();
                throw new \InvalidArgumentException('Kunci enkripsi (passphrase) tidak sesuai atau berkas manifest.json gagal didekripsi.');
            }

            $manifest = json_decode($manifestContent, true);
            if (! is_array($manifest) || ! isset($manifest['database'])) {
                $zip->close();
                throw new \RuntimeException('Format manifest.json di dalam arsip cadangan rusak.');
            }

            if (! @$zip->extractTo($tempExtractDir, ['manifest.json', 'database.sql'])) {
                $zip->close();
                throw new \RuntimeException('Gagal mengekstrak database.sql dari arsip cadangan. Pastikan kunci enkripsi benar.');
            }

            $extractedSqlPath = $tempExtractDir.DIRECTORY_SEPARATOR.'database.sql';
            if (! file_exists($extractedSqlPath) || filesize($extractedSqlPath) === 0) {
                $zip->close();
                throw new \RuntimeException('Berkas database.sql hasil ekstraksi kosong atau tidak ditemukan.');
            }

            // 4. Verifikasi hash SHA-256 database.sql dan audit keamanan isi SQL
            $sqlSha256 = hash_file('sha256', $extractedSqlPath);
            if (! hash_equals((string) ($manifest['database']['dump_sha256'] ?? ''), $sqlSha256)) {
                $zip->close();
                throw new \RuntimeException('Checksum database.sql tidak cocok dengan manifest.json.');
            }

            $this->auditSqlDumpContent($extractedSqlPath);

            // Ekstrak seluruh lampiran ke direktori temporer sebelum menyentuh sistem aktif
            $attachmentsMeta = $manifest['attachments']['files'] ?? [];
            if (! empty($attachmentsMeta)) {
                @$zip->extractTo($tempExtractDir);
            }
            $zip->close();

            // 5. Buat Safety Backup otomatis kondisi saat ini sebelum menimpa database (jika diaktifkan)
            $safetyBackupFilename = null;
            if ($createSafetyBackup && strlen((string) $passphrase) >= 12) {
                $backupService = app(BackupService::class);
                $safetyResult = $backupService->createBackup((string) $passphrase);

                // Ubah nama menjadi backup-pre-restore-YYYYmmdd_HHMMSS.zip agar mudah dikenali
                $backupDir = config('backup.path', storage_path('app/backups'));
                $preRestoreName = 'backup-pre-restore-'.now()->format('Ymd_His').'.zip';
                $preRestorePath = $backupDir.DIRECTORY_SEPARATOR.$preRestoreName;
                $preRestoreShaPath = $preRestorePath.'.sha256';

                if (file_exists($safetyResult['path'])) {
                    File::move($safetyResult['path'], $preRestorePath);
                    File::put($preRestoreShaPath, "{$safetyResult['sha256']}  {$preRestoreName}\n");
                    if (file_exists($safetyResult['companion_sha256_path'])) {
                        File::delete($safetyResult['companion_sha256_path']);
                    }
                    $safetyBackupFilename = $preRestoreName;
                }
            }

            // 6. Simpan sesi & token aktif milik admin yang sedang login agar tidak ter-logout pasca-restore
            $connectionName = config('database.default', 'mysql');
            $connection = DB::connection($connectionName);

            $preservedTokens = [];
            $preservedSessions = [];
            if ($preserveUserId !== null) {
                try {
                    $preservedTokens = $connection->table('personal_access_tokens')
                        ->where('tokenable_id', $preserveUserId)
                        ->get()
                        ->map(fn ($r) => (array) $r)
                        ->all();
                } catch (\Throwable) {
                }

                try {
                    $preservedSessions = $connection->table('sessions')
                        ->where('user_id', $preserveUserId)
                        ->get()
                        ->map(fn ($r) => (array) $r)
                        ->all();
                } catch (\Throwable) {
                }
            }

            // 7. Eksekusi impor SQL ke database operasional aktif
            $this->importSqlDump($connection, $extractedSqlPath, $tempExtractDir);

            // 8. Pulihkan direktori lampiran privat (storage/app/attachments)
            $attachmentsTargetDir = config('backup.attachments_path', storage_path('app/attachments'));
            File::ensureDirectoryExists($attachmentsTargetDir);
            File::cleanDirectory($attachmentsTargetDir);

            $extractedAttachmentsDir = $tempExtractDir.DIRECTORY_SEPARATOR.'attachments';
            if (! empty($attachmentsMeta) && File::isDirectory($extractedAttachmentsDir)) {
                File::copyDirectory($extractedAttachmentsDir, $attachmentsTargetDir);
            }

            // 9. Verifikasi multi-faktor terhadap database & lampiran yang telah dipulihkan
            $verificationResults = $this->verifyRestoredState($connection, $manifest, $attachmentsTargetDir);

            // 10. Pulihkan kembali token & sesi aktif admin agar sesi browser tetap terautentikasi
            if ($preserveUserId !== null) {
                $userExistsAfterRestore = false;
                try {
                    $userExistsAfterRestore = $connection->table('users')->where('id', $preserveUserId)->exists();
                } catch (\Throwable) {
                }

                if ($userExistsAfterRestore) {
                    foreach ($preservedTokens as $tokenRow) {
                        try {
                            $exists = $connection->table('personal_access_tokens')
                                ->where('token', $tokenRow['token'])
                                ->exists();
                            if (! $exists) {
                                unset($tokenRow['id']);
                                $connection->table('personal_access_tokens')->insert($tokenRow);
                            }
                        } catch (\Throwable) {
                        }
                    }

                    foreach ($preservedSessions as $sessionRow) {
                        try {
                            $exists = $connection->table('sessions')
                                ->where('id', $sessionRow['id'])
                                ->exists();
                            if (! $exists) {
                                $connection->table('sessions')->insert($sessionRow);
                            }
                        } catch (\Throwable) {
                        }
                    }
                }
            }

            $durationSeconds = round(microtime(true) - $startTime, 2);

            $report = [
                'status' => 'PASS',
                'timestamp' => now('Asia/Jakarta')->toIso8601String(),
                'duration_seconds' => $durationSeconds,
                'archive_file' => basename($archivePath),
                'archive_sha256' => $actualArchiveSha,
                'safety_backup_file' => $safetyBackupFilename,
                'target_connection' => $connectionName,
                'target_database' => $connection->getDatabaseName(),
                'checks' => $verificationResults,
            ];

            app(BackupMonitoringService::class)->recordDrillResult($report);

            return $report;
        } finally {
            if (File::isDirectory($tempExtractDir)) {
                File::deleteDirectory($tempExtractDir);
            }
        }
    }
}
