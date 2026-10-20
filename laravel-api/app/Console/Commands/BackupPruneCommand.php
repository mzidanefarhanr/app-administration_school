<?php

namespace App\Console\Commands;

use App\Services\BackupRetentionService;
use Illuminate\Console\Command;

class BackupPruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup-prune
                            {--dry-run : Preview which archives would be kept and deleted without modifying files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old backup archives according to retention policy (7 daily + 4 weekly) with safety preservation';

    /**
     * Execute the console command.
     */
    public function handle(BackupRetentionService $retentionService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Evaluating backup retention policy (DRY-RUN mode)...' : 'Starting backup retention prune process...');

        $result = $retentionService->prune($dryRun);

        if (($result['status'] ?? '') === 'SKIPPED') {
            $this->warn("Prune skipped: {$result['reason']}");

            return Command::SUCCESS;
        }

        // Display Kept Archives Table
        $keptRows = [];
        foreach ($result['kept'] as $filename => $item) {
            $keptRows[] = [
                $filename,
                $item['date'],
                implode(', ', $item['reasons']),
                number_format($item['size_bytes'] / 1024, 2).' KB',
            ];
        }

        if (! empty($keptRows)) {
            $this->line('');
            $this->info('=== ARCHIVES TO KEEP ===');
            $this->table(['Filename', 'Date', 'Retention Reasons', 'Size'], $keptRows);
        }

        // Display Deleted / Candidates Table
        $deleteRows = [];
        $candidates = $dryRun ? $result['delete_candidates'] : $result['deleted'];
        foreach ($candidates as $item) {
            $deleteRows[] = [
                $item['filename'],
                $item['date'] ?? '-',
                $dryRun ? 'Candidate for deletion' : 'Deleted successfully',
                number_format($item['size_bytes'] / 1024, 2).' KB',
            ];
        }

        if (! empty($deleteRows)) {
            $this->line('');
            $this->warn($dryRun ? '=== DELETION CANDIDATES (DRY RUN - NOT DELETED) ===' : '=== DELETED ARCHIVES ===');
            $this->table(['Filename', 'Date', 'Status', 'Size'], $deleteRows);
        } else {
            $this->line('');
            $this->info('No archives qualified for deletion. All valid backups are preserved.');
        }

        // Display Skipped Files
        if (! empty($result['skipped'])) {
            $skippedRows = [];
            foreach ($result['skipped'] as $skip) {
                $skippedRows[] = [$skip['filename'], $skip['reason']];
            }
            $this->line('');
            $this->warn('=== SKIPPED / UNVERIFIABLE FILES (PRESERVED) ===');
            $this->table(['Filename', 'Reason'], $skippedRows);
        }

        $this->line('');
        $this->info("Summary: {$result['kept_count']} archives kept, {$result['deleted_count']} archives ".($dryRun ? 'flagged for deletion' : 'deleted').'.');

        return Command::SUCCESS;
    }
}
