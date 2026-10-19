<?php

namespace App\Console\Commands;

use App\Services\RestoreDrillService;
use Illuminate\Console\Command;

class RestoreDrillCommand extends Command
{
    /**
     * The name and signature of the console command.
     * Passphrase is never accepted via CLI flags to prevent exposure in process listing.
     *
     * @var string
     */
    protected $signature = 'app:restore-drill
                            {archive : Path or filename of the backup archive to test}
                            {--target-connection=restore_drill : Database connection name for the disposable target}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform an isolated disaster recovery drill into a disposable target database and verify data/attachment integrity';

    /**
     * Execute the console command.
     */
    public function handle(RestoreDrillService $restoreDrillService): int
    {
        $archive = $this->argument('archive');

        // Resolve path if filename only is provided
        if (! file_exists($archive)) {
            $backupDir = config('backup.path', storage_path('app/backups'));
            $archive = $backupDir.DIRECTORY_SEPARATOR.$archive;
        }

        if (! file_exists($archive)) {
            $this->error("Backup archive not found: {$archive}");

            return Command::FAILURE;
        }

        $targetConnection = $this->option('target-connection') ?: 'restore_drill';
        $connConfig = config("database.connections.{$targetConnection}", []);
        $targetHost = $connConfig['host'] ?? 'local/in-memory';
        $targetPort = $connConfig['port'] ?? 'N/A';
        $targetDb = $connConfig['database'] ?? 'N/A';
        $drillAttachmentsPath = config('backup.drill_attachments_path', storage_path('app/restore-drill-attachments'));

        $this->info('Starting disaster recovery restore drill...');
        $this->line("Archive file: {$archive}");
        $this->line("Target connection: {$targetConnection}");
        $this->line("Effective Target: host={$targetHost}, port={$targetPort}, database={$targetDb}");
        $this->line("Drill Attachments Path: {$drillAttachmentsPath}");

        $passphrase = config('backup.encryption_key');
        if (! $passphrase) {
            $passphrase = $this->secret('Enter backup decryption passphrase');
        }

        if (! $passphrase) {
            $this->error('Decryption passphrase is required to run the restore drill.');

            return Command::FAILURE;
        }

        try {
            $report = $restoreDrillService->runDrill($archive, $passphrase, $targetConnection);

            $this->info('=== RESTORE DRILL VERIFICATION: PASS ===');
            $this->line("Status: {$report['status']}");
            $this->line("Duration: {$report['duration_seconds']}s");
            $this->line("Target Database: {$report['target_database']} ({$report['target_driver']})");
            $this->line("Tables Verified: {$report['checks']['tables_verified']}");
            $this->line('Foreign Keys Intact: '.($report['checks']['foreign_keys_valid'] ? 'YES' : 'NO'));
            $this->line('Admin Account Verified: '.($report['checks']['admin_account_verified'] ? 'YES' : 'NO'));
            $this->line("Attachments Verified: {$report['checks']['attachments_verified']}");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('=== RESTORE DRILL VERIFICATION: FAILED ===');
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
