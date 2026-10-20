<?php

namespace App\Console\Commands;

use App\Services\BackupMonitoringService;
use App\Services\BackupMutex;
use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Passphrase is never accepted via CLI flags to prevent exposure in process listing.
     *
     * @var string
     */
    protected $signature = 'app:backup-run
                            {--non-interactive : Execute non-interactively reading passphrase strictly from local config}
                            {--connection= : Database connection to backup}
                            {--attachments= : Path to private attachments directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an encrypted backup archive containing database dump, private attachments, and manifest';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService, BackupMonitoringService $monitoringService): int
    {
        $this->info('Starting automated backup process...');

        $isNonInteractive = (bool) $this->option('non-interactive');

        $connection = $this->option('connection') ?: config('database.default');
        $connConfig = config("database.connections.{$connection}", []);
        $host = $connConfig['host'] ?? 'local/in-memory';
        $port = $connConfig['port'] ?? 'N/A';
        $database = $connConfig['database'] ?? 'N/A';
        $attachments = $this->option('attachments') ?: config('backup.attachments_path', storage_path('app/attachments'));

        // Display effective source connection without credentials
        $this->line("Source Connection: {$connection}");
        $this->line("Effective Source Target: host={$host}, port={$port}, database={$database}");
        $this->line("Attachments Source: {$attachments}");

        // Fail-safe: verify source is not the operational database
        if (in_array(strtolower((string) $database), ['backdata_administrations'], true)) {
            $this->error("CRITICAL: Backing up operational database '{$database}' directly via this command is prohibited by safety rules.");

            return Command::FAILURE;
        }

        // 1. Resolve passphrase safely (local config or interactive secret prompt)
        $passphrase = config('backup.encryption_key');

        if ($isNonInteractive) {
            if (empty($passphrase) || strlen((string) $passphrase) < 12) {
                $errMsg = 'Non-interactive backup aborted: BACKUP_ENCRYPTION_KEY is not configured or shorter than 12 characters.';
                $this->error($errMsg);
                $monitoringService->recordBackupFailure($errMsg, 0.0);

                return Command::FAILURE;
            }
        } else {
            if (! $passphrase) {
                $passphrase = $this->secret('Enter backup encryption passphrase (min 12 characters)');
                $confirmation = $this->secret('Confirm passphrase');

                if ($passphrase !== $confirmation) {
                    $this->error('Passphrase confirmation does not match. Backup aborted.');

                    return Command::FAILURE;
                }
            }

            if (strlen((string) $passphrase) < 12) {
                $this->error('Encryption passphrase must be at least 12 characters long.');

                return Command::FAILURE;
            }
        }

        // 2. Wrap execution inside shared atomic mutex
        return BackupMutex::withLock(function () use ($backupService, $monitoringService, $passphrase, $connection, $attachments) {
            $startTime = microtime(true);

            try {
                $result = $backupService->createBackup($passphrase, $connection, $attachments);
                $duration = microtime(true) - $startTime;

                $this->info('Backup successfully created!');
                $this->line("Archive File: {$result['filename']}");
                $this->line("Archive Path: {$result['path']}");
                $this->line("Archive Size: {$result['size_bytes']} bytes");
                $this->line("Archive SHA-256: {$result['sha256']}");
                $this->line("Companion Checksum File: {$result['companion_sha256_path']}");
                $this->line('Tables Backed Up: '.count($result['manifest']['database']['tables']));
                $this->line("Attachments Backed Up: {$result['manifest']['attachments']['files_count']}");

                $monitoringService->recordBackupSuccess($result, $duration);

                return Command::SUCCESS;
            } catch (\Throwable $e) {
                $duration = microtime(true) - $startTime;
                $this->error("Backup failed: {$e->getMessage()}");
                $monitoringService->recordBackupFailure($e->getMessage(), $duration);

                return Command::FAILURE;
            }
        }, function () use ($monitoringService) {
            $skipMsg = 'Another backup or restore process is currently running. Skipping backup run.';
            $this->warn($skipMsg);
            $monitoringService->recordBackupSkipped($skipMsg);

            return Command::SUCCESS;
        });
    }
}
