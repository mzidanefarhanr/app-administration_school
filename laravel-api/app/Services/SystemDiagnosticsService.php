<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

class SystemDiagnosticsService
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly BackupMonitoringService $monitoringService,
        private readonly BackupRetentionService $retentionService
    ) {}

    /**
     * Mengambil status sistem menyeluruh mencakup kesehatan layanan,
     * kesiapan direktori penyimpanan, konfigurasi otomatisasi, dan arsip cadangan.
     *
     * @return array<string, mixed>
     */
    public function getSystemStatus(): array
    {
        $health = $this->monitoringService->getHealthStatus();
        $backups = $this->backupService->listBackups();
        $retentionEval = $this->retentionService->evaluateRetention();
        $jsonSnapshots = $this->listJsonSnapshots();

        $encryptionKey = config('backup.encryption_key');
        $hasConfiguredKey = ! empty($encryptionKey) && strlen((string) $encryptionKey) >= 12;

        return [
            'backup' => [
                'health' => $health,
                'archives' => $backups,
                'archives_count' => count($backups),
                'total_archives_bytes' => array_sum(array_column($backups, 'size_bytes')),
                'json_snapshots' => $jsonSnapshots,
                'retention_preview' => [
                    'keep_count' => count($retentionEval['keep'] ?? []),
                    'delete_candidates_count' => count($retentionEval['delete'] ?? []),
                ],
                'schedule_config' => [
                    'schedule_enabled' => (bool) config('backup.schedule_enabled', false),
                    'timezone' => config('backup.schedule_timezone', 'Asia/Jakarta'),
                    'backup_time' => config('backup.schedule_backup_time', '02:00'),
                    'prune_time' => config('backup.schedule_prune_time', '02:30'),
                    'retention_days' => (int) config('backup.retention_days', 7),
                    'retention_weeks' => (int) config('backup.retention_weeks', 4),
                    'max_age_hours' => (int) config('backup.max_age_hours', 28),
                    'has_configured_encryption_key' => $hasConfiguredKey,
                ],
            ],
            'services' => [
                'database' => $this->inspectDatabaseService(),
                'php_runtime' => $this->inspectPhpRuntime(),
                'storage_directories' => $this->inspectStorageDirectories(),
                'binaries' => $this->inspectBinaries(),
            ],
            'generated_at' => Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Mengambil metrik performa sistem, kapasitas disk, penggunaan memori,
     * rincian tabel terbesar, aktivitas sesi pengguna, dan ringkasan log error.
     *
     * @return array<string, mixed>
     */
    public function getSystemPerformance(): array
    {
        $dbMetrics = $this->collectDatabaseTableMetrics();
        $diskMetrics = $this->collectDiskMetrics($dbMetrics['total_db_size_bytes']);
        $runtimeMetrics = $this->collectRuntimeAndMemoryMetrics();
        $activityMetrics = $this->collectActivityAndConcurrencyMetrics();
        $errorLogsSummary = $this->collectErrorLogsSummary();

        return [
            'disk_usage' => $diskMetrics,
            'memory_and_runtime' => $runtimeMetrics,
            'database_tables' => $dbMetrics,
            'concurrency_and_activity' => $activityMetrics,
            'error_logs_summary' => $errorLogsSummary,
            'generated_at' => Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Inspeksi koneksi dan kesehatan layanan database.
     *
     * @return array<string, mixed>
     */
    protected function inspectDatabaseService(): array
    {
        $connectionName = config('database.default', 'mysql');
        $connected = false;
        $driver = 'unknown';
        $dbName = 'unknown';
        $version = 'unknown';
        $latencyMs = 0.0;
        $allInnoDb = true;
        $nonInnoDbCount = 0;

        try {
            $connection = DB::connection($connectionName);
            $driver = $connection->getDriverName();
            $dbName = (string) $connection->getDatabaseName();

            $start = microtime(true);
            $connection->select('SELECT 1');
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            $connected = true;

            $pdo = $connection->getPdo();
            $version = (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

            if ($driver === 'mysql') {
                $nonTrans = $connection->select(
                    "SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND (ENGINE IS NULL OR UPPER(ENGINE) != 'INNODB')",
                    [$dbName]
                );
                $nonInnoDbCount = (int) ($nonTrans[0]->cnt ?? 0);
                $allInnoDb = $nonInnoDbCount === 0;
            }
        } catch (\Throwable) {
            $connected = false;
        }

        return [
            'connected' => $connected,
            'connection' => $connectionName,
            'driver' => $driver,
            'database_name' => $dbName,
            'version' => $version,
            'latency_ms' => $latencyMs,
            'all_tables_innodb' => $allInnoDb,
            'non_innodb_count' => $nonInnoDbCount,
        ];
    }

    /**
     * Inspeksi lingkungan PHP dan ekstensi wajib.
     *
     * @return array<string, mixed>
     */
    protected function inspectPhpRuntime(): array
    {
        $requiredExtensions = [
            'pdo_mysql' => 'Koneksi Database MySQL/MariaDB',
            'zip' => 'Kompresi Arsip ZIP Backup',
            'openssl' => 'Enkripsi Kriptografi & Token',
            'fileinfo' => 'Deteksi Tipe MIME Berkas',
            'mbstring' => 'Pemrosesan String Multibyte UTF-8',
            'gd' => 'Pemrosesan Gambar & Grafik PDF',
        ];

        $extensions = [];
        foreach ($requiredExtensions as $ext => $desc) {
            $extensions[] = [
                'name' => $ext,
                'description' => $desc,
                'loaded' => extension_loaded($ext),
            ];
        }

        $zipAes256Supported = class_exists(ZipArchive::class) && defined('ZipArchive::EM_AES_256');

        return [
            'php_version' => PHP_VERSION,
            'os_family' => PHP_OS_FAMILY,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'PHP CLI / Built-in Server',
            'environment' => config('app.env', 'production'),
            'debug_mode' => (bool) config('app.debug', false),
            'timezone' => config('app.timezone', 'Asia/Jakarta'),
            'server_time_wib' => Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'zip_aes256_supported' => $zipAes256Supported,
            'extensions' => $extensions,
        ];
    }

    /**
     * Inspeksi ketersediaan dan izin tulis direktori penyimpanan penting.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function inspectStorageDirectories(): array
    {
        $dirs = [
            [
                'key' => 'backups',
                'label' => 'Direktori Arsip Cadangan (Backups)',
                'path' => config('backup.path', storage_path('app/backups')),
                'relative_path' => 'storage/app/backups',
            ],
            [
                'key' => 'attachments',
                'label' => 'Direktori Lampiran Privat (Attachments)',
                'path' => config('backup.attachments_path', storage_path('app/attachments')),
                'relative_path' => 'storage/app/attachments',
            ],
            [
                'key' => 'logs',
                'label' => 'Direktori Log Aplikasi (Logs)',
                'path' => storage_path('logs'),
                'relative_path' => 'storage/logs',
            ],
            [
                'key' => 'cache',
                'label' => 'Direktori Cache & Sesi Framework',
                'path' => storage_path('framework/cache'),
                'relative_path' => 'storage/framework/cache',
            ],
        ];

        $result = [];
        foreach ($dirs as $item) {
            $path = $item['path'];
            $exists = File::isDirectory($path);
            $writable = $exists && is_writable($path);
            $stats = $exists ? $this->calculateDirectoryStats($path) : ['files_count' => 0, 'size_bytes' => 0];

            $result[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'relative_path' => $item['relative_path'],
                'exists' => $exists,
                'writable' => $writable,
                'files_count' => $stats['files_count'],
                'size_bytes' => $stats['size_bytes'],
            ];
        }

        return $result;
    }

    /**
     * Inspeksi ketersediaan binary pendukung mysqldump dan mysql.
     *
     * @return array<string, mixed>
     */
    protected function inspectBinaries(): array
    {
        $mysqldumpPath = config('backup.mysqldump_binary', 'C:\xampp\mysql\bin\mysqldump.exe');
        $mysqlPath = config('backup.mysql_binary', 'C:\xampp\mysql\bin\mysql.exe');

        return [
            'mysqldump_available' => file_exists($mysqldumpPath),
            'mysql_cli_available' => file_exists($mysqlPath),
        ];
    }

    /**
     * Daftar berkas snapshot JSON (misal hasil reset sebelum impor).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function listJsonSnapshots(): array
    {
        $backupDir = config('backup.path', storage_path('app/backups'));
        if (! File::isDirectory($backupDir)) {
            return [];
        }

        $files = File::files($backupDir);
        $snapshots = [];

        foreach ($files as $file) {
            $filename = $file->getFilename();
            if (! str_starts_with($filename, 'backup_before_reset_') || ! str_ends_with($filename, '.json')) {
                continue;
            }

            $snapshots[] = [
                'filename' => $filename,
                'size_bytes' => $file->getSize(),
                'created_at' => date('c', $file->getMTime()),
            ];
        }

        usort($snapshots, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $snapshots;
    }

    /**
     * Kumpulkan metrik ukuran tabel dan jumlah baris di database.
     *
     * @return array<string, mixed>
     */
    protected function collectDatabaseTableMetrics(): array
    {
        $connectionName = config('database.default', 'mysql');
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();
        $dbName = (string) $connection->getDatabaseName();

        $tablesList = [];
        $totalTablesCount = 0;
        $totalRowsCount = 0;
        $totalDbSizeBytes = 0;

        try {
            if ($driver === 'mysql') {
                $rows = $connection->select(
                    'SELECT TABLE_NAME as table_name, ENGINE as engine, TABLE_ROWS as approx_rows, DATA_LENGTH as data_bytes, INDEX_LENGTH as index_bytes
                     FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = ?
                     ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC',
                    [$dbName]
                );

                $totalTablesCount = count($rows);

                foreach ($rows as $row) {
                    $tName = (string) $row->table_name;
                    $dataBytes = (int) ($row->data_bytes ?? 0);
                    $indexBytes = (int) ($row->index_bytes ?? 0);
                    $totalBytes = $dataBytes + $indexBytes;
                    $totalDbSizeBytes += $totalBytes;

                    // Hitung exact count untuk akurasi penuh
                    try {
                        $exactCount = (int) $connection->table($tName)->count();
                    } catch (\Throwable) {
                        $exactCount = (int) ($row->approx_rows ?? 0);
                    }

                    $totalRowsCount += $exactCount;

                    $tablesList[] = [
                        'table_name' => $tName,
                        'engine' => (string) ($row->engine ?? 'InnoDB'),
                        'rows_count' => $exactCount,
                        'data_bytes' => $dataBytes,
                        'index_bytes' => $indexBytes,
                        'total_bytes' => $totalBytes,
                    ];
                }
            } elseif ($driver === 'sqlite') {
                $rows = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $totalTablesCount = count($rows);

                foreach ($rows as $row) {
                    $tName = (string) $row->name;
                    $exactCount = (int) $connection->table($tName)->count();
                    $totalRowsCount += $exactCount;

                    $tablesList[] = [
                        'table_name' => $tName,
                        'engine' => 'SQLite',
                        'rows_count' => $exactCount,
                        'data_bytes' => $exactCount * 256,
                        'index_bytes' => 0,
                        'total_bytes' => $exactCount * 256,
                    ];
                    $totalDbSizeBytes += $exactCount * 256;
                }
            }
        } catch (\Throwable) {
            // Fallback safe empty
        }

        // Urutkan berdasarkan jumlah baris terbanyak terlebih dahulu, lalu ukuran bytes
        usort($tablesList, function ($a, $b) {
            if ($b['rows_count'] !== $a['rows_count']) {
                return $b['rows_count'] <=> $a['rows_count'];
            }

            return $b['total_bytes'] <=> $a['total_bytes'];
        });

        return [
            'total_tables_count' => $totalTablesCount,
            'total_rows_count' => $totalRowsCount,
            'total_db_size_bytes' => $totalDbSizeBytes,
            'top_tables' => array_slice($tablesList, 0, 15),
        ];
    }

    /**
     * Kumpulkan metrik penggunaan disk server dan rincian komponen aplikasi.
     *
     * @return array<string, mixed>
     */
    protected function collectDiskMetrics(int $dbSizeBytes): array
    {
        $storagePath = storage_path();
        $totalBytes = @disk_total_space($storagePath) ?: 0;
        $freeBytes = @disk_free_space($storagePath) ?: 0;
        $usedBytes = max(0, $totalBytes - $freeBytes);
        $usedPercent = $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100, 2) : 0.0;

        $backupsStats = $this->calculateDirectoryStats(config('backup.path', storage_path('app/backups')));
        $attachmentsStats = $this->calculateDirectoryStats(config('backup.attachments_path', storage_path('app/attachments')));
        $logsStats = $this->calculateDirectoryStats(storage_path('logs'));

        return [
            'total_bytes' => (int) $totalBytes,
            'free_bytes' => (int) $freeBytes,
            'used_bytes' => (int) $usedBytes,
            'used_percent' => $usedPercent,
            'breakdown' => [
                'database_bytes' => $dbSizeBytes,
                'backups_bytes' => $backupsStats['size_bytes'],
                'attachments_bytes' => $attachmentsStats['size_bytes'],
                'logs_bytes' => $logsStats['size_bytes'],
            ],
        ];
    }

    /**
     * Kumpulkan metrik penggunaan memori RAM PHP dan batas konfigurasi runtime.
     *
     * @return array<string, mixed>
     */
    protected function collectRuntimeAndMemoryMetrics(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimitStr = (string) ini_get('memory_limit');
        $memoryLimitBytes = $this->parseIniSizeToBytes($memoryLimitStr);

        $memoryUsagePercent = ($memoryLimitBytes > 0)
            ? round(($memoryPeak / $memoryLimitBytes) * 100, 2)
            : 0.0;

        $dbStart = microtime(true);
        try {
            DB::select('SELECT 1');
            $dbPingMs = round((microtime(true) - $dbStart) * 1000, 2);
        } catch (\Throwable) {
            $dbPingMs = 0.0;
        }

        return [
            'memory_usage_bytes' => $memoryUsage,
            'memory_peak_bytes' => $memoryPeak,
            'memory_limit' => $memoryLimitStr,
            'memory_limit_bytes' => $memoryLimitBytes,
            'memory_usage_percent' => $memoryUsagePercent,
            'max_execution_time' => (int) ini_get('max_execution_time'),
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'post_max_size' => (string) ini_get('post_max_size'),
            'db_ping_ms' => $dbPingMs,
        ];
    }

    /**
     * Kumpulkan metrik sesi aktif dan aktivitas pengguna.
     *
     * @return array<string, mixed>
     */
    protected function collectActivityAndConcurrencyMetrics(): array
    {
        $totalUsers = 0;
        $onlineUsers = 0;
        $activeTokens = 0;
        $auditLogs24h = 0;

        try {
            $totalUsers = User::query()->count();
            $onlineUsers = User::query()->where('status_online', true)->count();
        } catch (\Throwable) {
        }

        try {
            $activeTokens = (int) DB::table('personal_access_tokens')->count();
        } catch (\Throwable) {
        }

        try {
            $since = Carbon::now()->subHours(24);
            $auditLogs24h = (int) DB::table('activities')->where('created_at', '>=', $since)->count();
        } catch (\Throwable) {
        }

        return [
            'total_users_count' => $totalUsers,
            'online_users_count' => $onlineUsers,
            'active_tokens_count' => $activeTokens,
            'activities_last_24h' => $auditLogs24h,
        ];
    }

    /**
     * Baca dan sanitasi ringkasan log error dari storage/logs/laravel.log.
     *
     * @return array<string, mixed>
     */
    protected function collectErrorLogsSummary(): array
    {
        $logFile = storage_path('logs/laravel.log');

        if (! file_exists($logFile) || ! is_readable($logFile)) {
            return [
                'log_file_exists' => false,
                'log_size_bytes' => 0,
                'error_count_recent' => 0,
                'warning_count_recent' => 0,
                'recent_entries' => [],
            ];
        }

        $fileSize = (int) filesize($logFile);
        $readBytes = min($fileSize, 131072); // Baca maksimal 128 KB terakhir

        $content = '';
        if ($readBytes > 0) {
            $handle = @fopen($logFile, 'rb');
            if ($handle) {
                fseek($handle, -$readBytes, SEEK_END);
                $content = (string) fread($handle, $readBytes);
                fclose($handle);
            }
        }

        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}[^\]]*)\]\s+([a-zA-Z0-9_-]+)\.(ERROR|CRITICAL|ALERT|EMERGENCY|WARNING):\s+([^\r\n]+)/m';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $errorCount = 0;
        $warningCount = 0;
        $entries = [];

        foreach ($matches as $match) {
            $timestamp = $match[1];
            $env = $match[2];
            $level = strtoupper($match[3]);
            $rawMsg = trim($match[4]);

            if ($level === 'WARNING') {
                $warningCount++;
            } else {
                $errorCount++;
            }

            // Sanitasi pesan: potong stacktrace/JSON panjang & sembunyikan informasi sensitif
            $cleanMsg = preg_replace('/\{.*$/', '', $rawMsg) ?: $rawMsg;
            $cleanMsg = preg_replace('/(password|token|secret|key|passphrase)=[^\s&]+/i', '$1=[REDACTED]', $cleanMsg);
            $cleanMsg = mb_substr(trim((string) $cleanMsg), 0, 180);

            $entries[] = [
                'timestamp' => $timestamp,
                'environment' => $env,
                'level' => $level,
                'message' => $cleanMsg,
            ];
        }

        $entries = array_reverse($entries);

        return [
            'log_file_exists' => true,
            'log_size_bytes' => $fileSize,
            'error_count_recent' => $errorCount,
            'warning_count_recent' => $warningCount,
            'recent_entries' => array_slice($entries, 0, 12),
        ];
    }

    /**
     * Hitung jumlah berkas dan total ukuran bytes dalam suatu direktori.
     *
     * @return array{files_count: int, size_bytes: int}
     */
    protected function calculateDirectoryStats(string $dirPath): array
    {
        if (! File::isDirectory($dirPath)) {
            return ['files_count' => 0, 'size_bytes' => 0];
        }

        try {
            $files = File::allFiles($dirPath);
            $totalSize = 0;
            foreach ($files as $file) {
                $totalSize += $file->getSize();
            }

            return [
                'files_count' => count($files),
                'size_bytes' => $totalSize,
            ];
        } catch (\Throwable) {
            return ['files_count' => 0, 'size_bytes' => 0];
        }
    }

    /**
     * Konversi nilai ukuran konfigurasi php.ini (misal "512M", "2G") ke bytes.
     */
    protected function parseIniSizeToBytes(string $sizeStr): int
    {
        $sizeStr = trim($sizeStr);
        if ($sizeStr === '' || $sizeStr === '-1') {
            return 0;
        }

        $unit = strtolower(substr($sizeStr, -1));
        $val = (int) $sizeStr;

        return match ($unit) {
            'g' => $val * 1024 * 1024 * 1024,
            'm' => $val * 1024 * 1024,
            'k' => $val * 1024,
            default => $val,
        };
    }
}
