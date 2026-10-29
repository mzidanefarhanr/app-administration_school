<?php

namespace App\Console\Commands;

use App\Services\EmployeeBackfillService;
use Illuminate\Console\Command;

class EmployeeBackfillCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:employee-account-backfill {--dry-run : Report candidates without writing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Link legacy employee profiles to user accounts by unique NIK without leaking PII';

    /**
     * Execute the console command.
     */
    public function handle(EmployeeBackfillService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'Running employee account backfill in DRY-RUN mode...'
            : 'Running employee account backfill...');

        $stats = $service->backfill($dryRun);

        $this->table(
            ['Metric / Category', 'Count'],
            [
                ['Legacy Rows Evaluated', $stats['total_rows']],
                ['Skipped: Not a Legacy Profile', $stats['skipped_non_legacy']],
                ['Already Resolved', $stats['already_resolved']],
                ['Matched & Linked (or candidates in dry-run)', $stats['matched']],
                ['Unresolved: Empty / Null NIK', $stats['unresolved_empty']],
                ['Unresolved: Invalid Format (!16 digits)', $stats['unresolved_invalid_format']],
                ['Unresolved: Unmatched User', $stats['unresolved_unmatched']],
                ['Unresolved: Ambiguous (Multiple Users)', $stats['unresolved_ambiguous']],
                ['Unresolved: Soft-Deleted Account', $stats['unresolved_soft_deleted']],
                ['Unresolved: Account Already Linked', $stats['unresolved_account_already_linked']],
                ['Total Unresolved', $stats['total_unresolved']],
            ]
        );

        if ($dryRun) {
            $this->info('Dry run complete. No data was written.');
        } elseif ($stats['total_unresolved'] > 0) {
            $this->warn("Completed with {$stats['total_unresolved']} unresolved rows. Unresolved records remain untouched.");
        } else {
            $this->info('Backfill completed successfully with 0 unresolved rows.');
        }

        return Command::SUCCESS;
    }
}
