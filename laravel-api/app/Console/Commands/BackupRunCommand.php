<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Note: passphrase should ideally be supplied via secret prompt or env to prevent exposure.
     *
     * @var string
     */
    protected $signature = 'app:backup-run
                            {--passphrase= : Passphrase for AES-256 encryption (min 12 characters)}
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
    public function handle(BackupService $backupService): int
    {
        $this->info('Starting automated backup process...');

        // 1. Resolve passphrase safely (CLI option, env, or secret prompt)
        $passphrase = $this->option('passphrase') ?: config('backup.encryption_key');

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

        $connection = $this->option('connection');
        $attachments = $this->option('attachments');

        try {
            $result = $backupService->createBackup($passphrase, $connection, $attachments);

            $this->info('Backup successfully created!');
            $this->line("Archive File: {$result['filename']}");
            $this->line("Archive Path: {$result['path']}");
            $this->line("Archive Size: {$result['size_bytes']} bytes");
            $this->line("Archive SHA-256: {$result['sha256']}");
            $this->line("Companion Checksum File: {$result['companion_sha256_path']}");
            $this->line('Tables Backed Up: '.count($result['manifest']['database']['tables']));
            $this->line("Attachments Backed Up: {$result['manifest']['attachments']['files_count']}");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Backup failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
