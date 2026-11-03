<?php

namespace App\Services\Archives;

use App\Models\ArchiveAccessLog;
use App\Models\ArchiveDocument;
use App\Models\ArchiveFileVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Audit akses download/preview arsip (Fase 09b, DEC-38 D3).
 *
 * Tabel append-only; hanya akses berizin yang berhasil yang dicatat, ditulis
 * sebelum byte dikirim, dan gagal-closed (500 tanpa byte) bila penulisan gagal.
 * Tidak menyimpan judul, nama file, path, atau checksum.
 */
class ArchiveAccessService
{
    public const FAILURE_MESSAGE = 'The access event could not be recorded.';

    public function record(
        ArchiveDocument $document,
        ArchiveFileVersion $version,
        string $action,
        User $actor,
        Request $request,
    ): void {
        if (! in_array($action, ArchiveAccessLog::ACTIONS, true)) {
            abort(response()->json(['message' => self::FAILURE_MESSAGE], 500));
        }

        $userAgent = (string) $request->userAgent();
        $limit = (int) config('archives.access_log.user_agent_max');

        try {
            ArchiveAccessLog::create([
                'archive_document_id' => $document->id,
                'archive_file_version_id' => $version->id,
                'user_id' => $actor->id,
                'action' => $action,
                'ip_address' => $request->ip(),
                'user_agent' => $userAgent === '' ? null : mb_substr($userAgent, 0, $limit),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Archive access log write failed.', [
                'action' => $action,
                'exception' => $exception->getMessage(),
            ]);

            abort(response()->json(['message' => self::FAILURE_MESSAGE], 500));
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(ArchiveDocument $document, array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? config('archives.default_per_page'));

        $paginator = ArchiveAccessLog::query()
            ->where('archive_document_id', $document->id)
            ->with(['version:id,version_number', 'user:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'items' => collect($paginator->items())
                ->map(fn (ArchiveAccessLog $log) => $this->serialize($log))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ArchiveAccessLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'action_label' => $log->actionLabel(),
            'version_number' => $log->version === null ? null : (int) $log->version->version_number,
            'user' => $log->user === null ? null : [
                'id' => $log->user->id,
                'name' => $log->user->name,
            ],
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
