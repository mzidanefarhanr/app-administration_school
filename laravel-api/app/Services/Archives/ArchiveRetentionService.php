<?php

namespace App\Services\Archives;

use App\Models\ArchiveAccessLog;
use App\Models\ArchiveDocument;
use App\Models\ArchiveFileVersion;
use App\Models\Role;
use Carbon\Carbon;

/**
 * Laporan retensi arsip read-only (Fase 09b, DEC-38 D6 Opsi A).
 *
 * Tidak ada penulisan, penghapusan, atau scheduler. Kemampuan archives.purge
 * dibuat tanpa penugasan peran; eksekusi pemusnahan memerlukan keputusan
 * pemilik terpisah.
 */
class ArchiveRetentionService
{
    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $today = $now->copy()->startOfDay();
        $periods = (array) config('archives.retention.periods');

        $documentCounts = ArchiveDocument::query()
            ->selectRaw(
                'category, count(*) as documents_total,
                 coalesce(sum(case when archived_at is null then 0 else 1 end), 0) as archived_total,
                 min(document_date) as oldest_document_date'
            )
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $versionCounts = ArchiveFileVersion::query()
            ->join('archive_documents', 'archive_documents.id', '=', 'archive_file_versions.archive_document_id')
            ->selectRaw(
                'archive_documents.category as category,
                 count(*) as versions_total,
                 coalesce(sum(archive_file_versions.size_bytes), 0) as size_bytes_total'
            )
            ->groupBy('archive_documents.category')
            ->get()
            ->keyBy('category');

        $categories = [];
        $totals = [
            'documents_total' => 0,
            'archived_total' => 0,
            'eligible_total' => 0,
            'versions_total' => 0,
            'size_bytes_total' => 0,
        ];

        foreach ((array) config('archives.categories') as $category => $label) {
            $years = $periods[$category]['years'] ?? null;
            $cutoff = $years === null
                ? null
                : $today->copy()->subYears((int) $years)->toDateString();

            $eligible = $cutoff === null
                ? 0
                : ArchiveDocument::query()
                    ->where('category', $category)
                    ->whereDate('document_date', '<=', $cutoff)
                    ->count();

            $documents = (int) ($documentCounts[$category]->documents_total ?? 0);
            $archived = (int) ($documentCounts[$category]->archived_total ?? 0);
            $versions = (int) ($versionCounts[$category]->versions_total ?? 0);
            $sizeBytes = (int) ($versionCounts[$category]->size_bytes_total ?? 0);

            $categories[] = [
                'category' => $category,
                'label' => $label,
                'retention_years' => $years === null ? null : (int) $years,
                'retention_label' => (string) ($periods[$category]['label'] ?? 'Permanent'),
                'cutoff_date' => $cutoff,
                'documents_total' => $documents,
                'active_total' => $documents - $archived,
                'archived_total' => $archived,
                'eligible_total' => $eligible,
                'versions_total' => $versions,
                'size_bytes_total' => $sizeBytes,
                'oldest_document_date' => $documentCounts[$category]->oldest_document_date ?? null,
            ];

            $totals['documents_total'] += $documents;
            $totals['archived_total'] += $archived;
            $totals['eligible_total'] += $eligible;
            $totals['versions_total'] += $versions;
            $totals['size_bytes_total'] += $sizeBytes;
        }

        return [
            'generated_at' => $now->toIso8601String(),
            'policy' => [
                'unit' => (string) config('archives.retention.unit'),
                'anchor' => (string) config('archives.retention.anchor'),
                'purge_enabled' => false,
                'purge_ability' => 'archives.purge',
                'purge_assigned_roles' => Role::query()
                    ->whereHas('abilities', fn ($query) => $query->where('abilities.name', 'archives.purge'))
                    ->pluck('slug')
                    ->values()
                    ->all(),
                'note' => 'Read-only report. Purge execution is not authorized and no data is deleted.',
            ],
            'categories' => $categories,
            'totals' => $totals,
            'access_logs' => $this->accessLogs($today),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accessLogs(Carbon $today): array
    {
        $years = (int) config('archives.retention.access_log_years');
        $cutoff = $today->copy()->subYears($years)->toDateString();

        return [
            'retention_years' => $years,
            'cutoff_date' => $cutoff,
            'total' => ArchiveAccessLog::query()->count(),
            'eligible_total' => ArchiveAccessLog::query()->whereDate('created_at', '<=', $cutoff)->count(),
            'oldest_created_at' => ArchiveAccessLog::query()->min('created_at'),
        ];
    }
}
