<?php

namespace App\Console\Commands;

use App\Services\Archives\ArchiveRetentionService;
use Illuminate\Console\Command;

/**
 * Laporan retensi arsip read-only (Fase 09b, DEC-38 D6 Opsi A).
 *
 * Hanya --dry-run yang diizinkan: kemampuan archives.purge ada tetapi tidak
 * ditugaskan ke peran mana pun dan eksekusi pemusnahan belum diotorisasi.
 */
class ArchivesRetentionCommand extends Command
{
    protected $signature = 'app:archives-retention
                            {--dry-run : Report retention eligibility without modifying any data}';

    protected $description = 'Report archive retention eligibility per category (read-only; purge execution is not authorized)';

    public function handle(ArchiveRetentionService $retention): int
    {
        if (! $this->option('dry-run')) {
            $this->error('Purge execution is not authorized (DEC-38 D6 Option A). Re-run with --dry-run for a read-only report.');

            return Command::FAILURE;
        }

        $report = $retention->report();

        $this->info('Evaluating archive retention policy (DRY-RUN — no data is modified)...');
        $this->line('');

        $rows = [];

        foreach ($report['categories'] as $category) {
            $rows[] = [
                $category['label'],
                $category['retention_years'] === null ? 'Permanent' : $category['retention_years'].' year(s)',
                $category['cutoff_date'] ?? '-',
                number_format((int) $category['documents_total']),
                number_format((int) $category['active_total']),
                number_format((int) $category['archived_total']),
                number_format((int) $category['eligible_total']),
                $this->bytes((int) $category['size_bytes_total']),
                $category['oldest_document_date'] ?? '-',
            ];
        }

        $this->table(
            ['Category', 'Retention', 'Cutoff', 'Documents', 'Active', 'Archived', 'Eligible', 'File Size', 'Oldest'],
            $rows,
        );

        $totals = $report['totals'];

        $this->line('');
        $this->info(sprintf(
            'Totals: %s documents (%s archived), %s versions, %s stored, %s past retention.',
            number_format((int) $totals['documents_total']),
            number_format((int) $totals['archived_total']),
            number_format((int) $totals['versions_total']),
            $this->bytes((int) $totals['size_bytes_total']),
            number_format((int) $totals['eligible_total']),
        ));

        $accessLogs = $report['access_logs'];

        $this->info(sprintf(
            'Access logs: %s rows, %s past the %s-year retention (cutoff %s).',
            number_format((int) $accessLogs['total']),
            number_format((int) $accessLogs['eligible_total']),
            $accessLogs['retention_years'],
            $accessLogs['cutoff_date'],
        ));

        $roles = $report['policy']['purge_assigned_roles'];

        $this->line('');

        if ($roles === []) {
            $this->warn('archives.purge is not assigned to any role; no destructive action is available.');
        } else {
            $this->warn('archives.purge is assigned to: '.implode(', ', $roles).'. Purge execution still requires a separate owner decision.');
        }

        return Command::SUCCESS;
    }

    private function bytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 KB';
        }

        return number_format($bytes / 1024, 2).' KB';
    }
}
