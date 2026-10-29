<?php

namespace App\Console\Commands;

use App\Services\EmployeeNameBackfillService;
use Illuminate\Console\Command;

class EmployeeNameBackfillCommand extends Command
{
    protected $signature = 'app:employee-name-backfill {--dry-run : Report candidates without writing}';

    protected $description = 'Fill legacy employee profile names from their linked account without leaking PII';

    public function handle(EmployeeNameBackfillService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? 'Running employee name backfill in DRY-RUN mode...'
            : 'Running employee name backfill...');

        $stats = $service->backfill($dryRun);

        $this->table(
            ['Metric / Category', 'Count'],
            [
                ['Employee Rows Evaluated', $stats['total_rows']],
                ['Already Named', $stats['already_named']],
                ['Filled From Linked Account (or candidates in dry-run)', $stats['filled_from_user_id']],
                ['Filled From Legacy NIK (or candidates in dry-run)', $stats['filled_from_legacy_nik']],
                ['Unresolved: No Resolvable Account', $stats['unresolved_no_account']],
                ['Unresolved: Account Has Empty Name', $stats['unresolved_empty_user_name']],
                ['Unresolved: Ambiguous Legacy NIK', $stats['unresolved_ambiguous_legacy']],
                ['Total Unresolved', $stats['total_unresolved']],
            ]
        );

        if ($dryRun) {
            $this->info('Dry run complete. No data was written.');
        } elseif ($stats['total_unresolved'] > 0) {
            $this->warn("Completed with {$stats['total_unresolved']} unresolved rows. Operator must complete these names manually.");
        } else {
            $this->info('Backfill completed successfully with 0 unresolved rows.');
        }

        return Command::SUCCESS;
    }
}
