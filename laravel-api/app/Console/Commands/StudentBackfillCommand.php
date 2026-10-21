<?php

namespace App\Console\Commands;

use App\Services\StudentBackfillService;
use Illuminate\Console\Command;

class StudentBackfillCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:student-backfill {--dry-run : Simulate backfill without modifying database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill student_id on student_rombels using unique NIK match without leaking PII';

    /**
     * Execute the console command.
     */
    public function handle(StudentBackfillService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running student_rombels backfill in DRY-RUN mode...');
        } else {
            $this->info('Running student_rombels backfill...');
        }

        $stats = $service->backfill($dryRun);

        $this->table(
            ['Metric / Category', 'Count'],
            [
                ['Total Rombel Rows Evaluated', $stats['total_rows']],
                ['Already Resolved (student_id set)', $stats['already_resolved']],
                ['Matched & Backfilled (or candidate in dry-run)', $stats['matched']],
                ['Unresolved: Empty / Null NIK', $stats['unresolved_empty']],
                ['Unresolved: Invalid Format (!16 digits)', $stats['unresolved_invalid_format']],
                ['Unresolved: Unmatched in Students Table', $stats['unresolved_unmatched']],
                ['Unresolved: Ambiguous (Multiple Students)', $stats['unresolved_ambiguous']],
                ['Total Unresolved', $stats['total_unresolved']],
            ]
        );

        if ($stats['total_unresolved'] > 0) {
            $this->warn("Completed with {$stats['total_unresolved']} unresolved rows. Unresolved records remain safe and untouched.");
        } else {
            $this->info('Backfill completed successfully with 0 unresolved rows.');
        }

        return Command::SUCCESS;
    }
}
