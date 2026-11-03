<?php

namespace App\Services\Archives;

use App\Models\ArchiveDocument;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tugas kedaluwarsa arsip yang dihitung saat baca (Fase 09b, DEC-38 D4).
 *
 * Tidak ada tabel, job, atau notifikasi push; dokumen terarsip dikecualikan
 * dan visibilitas domain 09a tetap ditegakkan.
 */
class ArchiveTaskService
{
    public function __construct(
        private readonly ArchiveSubjectService $subjects,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function expiring(User $actor, int $limit): array
    {
        $now = Carbon::now('Asia/Jakarta');
        $today = $now->copy()->startOfDay();
        $threshold = (int) config('archives.expiring_soon_days');
        $soon = $today->copy()->addDays($threshold);

        return [
            'threshold_days' => $threshold,
            'generated_at' => $now->toIso8601String(),
            'expiring_soon' => $this->group($actor, $limit, $today, $soon, false),
            'expired' => $this->group($actor, $limit, $today, $soon, true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function group(User $actor, int $limit, Carbon $today, Carbon $soon, bool $expired): array
    {
        $query = ArchiveDocument::query()
            ->whereNull('archived_at')
            ->whereNotNull('valid_until');

        $this->subjects->constrainVisible($query, $actor);

        if ($expired) {
            $query->whereDate('valid_until', '<', $today->toDateString());
        } else {
            $query->whereDate('valid_until', '>=', $today->toDateString())
                ->whereDate('valid_until', '<=', $soon->toDateString());
        }

        // Cermin presedensi validityStatus(): upcoming menang, jadi tugas
        // hanya untuk dokumen yang masa berlakunya sudah dimulai.
        $query->where(function (Builder $sub) use ($today) {
            $sub->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today->toDateString());
        });

        $total = (clone $query)->count();

        $documents = $query
            ->orderBy('valid_until')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $summaries = $this->subjects->summaries($documents);

        return [
            'total' => $total,
            'items' => $documents
                ->map(function (ArchiveDocument $document) use ($today, $summaries) {
                    $subject = $document->subject_type === null || $document->subject_id === null
                        ? null
                        : ($summaries[$document->subject_type.':'.$document->subject_id] ?? null);

                    return [
                        'id' => $document->id,
                        'category' => $document->category,
                        'category_label' => $document->categoryLabel(),
                        'title' => $document->title,
                        'document_number' => $document->document_number,
                        'valid_from' => $document->valid_from?->format('Y-m-d'),
                        'valid_until' => $document->valid_until?->format('Y-m-d'),
                        'validity_status' => $document->validityStatus($today->toDateString()),
                        'days_remaining' => (int) $today->diffInDays($document->valid_until, false),
                        'current_version' => (int) $document->current_version,
                        'subject' => $subject,
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
