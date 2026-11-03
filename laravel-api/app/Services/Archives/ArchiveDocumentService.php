<?php

namespace App\Services\Archives;

use App\Models\ArchiveAccessLog;
use App\Models\ArchiveDocument;
use App\Models\ArchiveFileVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Orkestrasi metadata, versi file, pencarian lintas domain, dan unduhan
 * arsip (Fase 09a, DEC-37; akses aman & siklus hidup 09b, DEC-38).
 */
class ArchiveDocumentService
{
    public function __construct(
        private readonly ArchiveFileService $files,
        private readonly ArchiveSubjectService $subjects,
        private readonly ArchiveAccessService $access,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function options(User $actor): array
    {
        $subjectTypes = [];

        foreach ((array) config('archives.subject_types') as $type => $definition) {
            if ($actor->hasAbility((string) $definition['ability'])) {
                $subjectTypes[] = [
                    'value' => $type,
                    'label' => $definition['label'],
                ];
            }
        }

        return [
            'categories' => collect((array) config('archives.categories'))
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'subject_types' => $subjectTypes,
            'validity_statuses' => collect((array) config('archives.validity_statuses'))
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'statuses' => collect((array) config('archives.statuses'))
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'access_log_actions' => collect((array) config('archives.access_log.actions'))
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'expiring_task_limit' => [
                'default' => (int) config('archives.expiring_task.default_limit'),
                'max' => (int) config('archives.expiring_task.max_limit'),
            ],
            'archive_reason' => [
                'min' => (int) config('archives.archive_reason.min'),
                'max' => (int) config('archives.archive_reason.max'),
            ],
            'retention' => [
                'unit' => (string) config('archives.retention.unit'),
                'anchor' => (string) config('archives.retention.anchor'),
                'periods' => collect((array) config('archives.retention.periods'))
                    ->map(fn (array $period, string $category) => [
                        'category' => $category,
                        'label' => (string) config('archives.categories.'.$category, $category),
                        'years' => $period['years'] === null ? null : (int) $period['years'],
                        'description' => (string) ($period['label'] ?? 'Permanent'),
                    ])
                    ->values()
                    ->all(),
                'purge_enabled' => false,
            ],
            'per_page_options' => config('archives.per_page_options'),
            'default_per_page' => (int) config('archives.default_per_page'),
            'search_max_length' => (int) config('archives.search_max_length'),
            'expiring_soon_days' => (int) config('archives.expiring_soon_days'),
            'title' => [
                'min' => (int) config('archives.title.min'),
                'max' => (int) config('archives.title.max'),
            ],
            'document_number' => [
                'max' => (int) config('archives.document_number.max'),
            ],
            'description' => [
                'max' => (int) config('archives.description.max'),
            ],
            'version_note' => [
                'max' => (int) config('archives.version_note.max'),
            ],
            'file' => [
                'max_file_kb' => (int) config('archives.attachment.max_file_kb'),
                'allowed_extensions' => config('archives.attachment.allowed_extensions'),
            ],
            'can' => [
                'view' => true,
                'manage' => $actor->hasAbility('archives.manage'),
                'view_access_log' => $actor->hasAbility((string) config('archives.access_log.ability')),
                'view_retention_report' => $actor->hasAbility('archives.manage'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters, User $actor): array
    {
        $query = ArchiveDocument::query()
            ->withCount('versions')
            ->with(['creator:id,name', 'updater:id,name']);

        $this->subjects->constrainVisible($query, $actor);
        $this->applyFilters($query, $filters, $actor);

        $perPage = (int) ($filters['per_page'] ?? config('archives.default_per_page'));

        $paginator = $query
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        $documents = collect($paginator->items());
        $summaries = $this->subjects->summaries($documents);

        return [
            'items' => $documents
                ->map(fn (ArchiveDocument $document) => $this->serializeSummary(
                    $document,
                    $actor,
                    $this->subjectSummary($document, $summaries),
                ))
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
    public function show(int $documentId, User $actor): array
    {
        $document = $this->findVisibleOrFail($documentId, $actor);

        $document->loadCount(['versions', 'accessLogs']);
        $document->load([
            'creator:id,name',
            'updater:id,name',
            'archiver:id,name',
            'latestVersion.uploader:id,name',
        ]);

        return $this->serializeDetail(
            $document,
            $actor,
            $this->subjects->summaryFor($document->subject_type, $document->subject_id === null ? null : (int) $document->subject_id),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function subjects(string $type, array $filters, User $actor): array
    {
        $this->subjects->ensureDomainAbility($actor, $type);

        return $this->subjects->picker(
            $type,
            (string) ($filters['q'] ?? ''),
            (int) ($filters['limit'] ?? config('archives.subject_picker_limit')),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, UploadedFile $file, User $actor): array
    {
        [$subjectType, $subjectId] = $this->subjects->resolveForCreate(
            isset($data['subject_type']) && $data['subject_type'] !== '' ? (string) $data['subject_type'] : null,
            isset($data['subject_id']) ? (int) $data['subject_id'] : null,
            $actor,
        );

        $this->assertValidityPair($data['valid_from'] ?? null, $data['valid_until'] ?? null);
        $extension = $this->files->validateFile($file);

        $metadata = null;

        try {
            $document = DB::transaction(function () use ($data, $file, $extension, $actor, $subjectType, $subjectId, &$metadata) {
                $document = ArchiveDocument::create([
                    'category' => (string) $data['category'],
                    'title' => trim((string) $data['title']),
                    'document_number' => $this->textOrNull($data['document_number'] ?? null),
                    'document_date' => (string) $data['document_date'],
                    'valid_from' => $this->textOrNull($data['valid_from'] ?? null),
                    'valid_until' => $this->textOrNull($data['valid_until'] ?? null),
                    'description' => $this->textOrNull($data['description'] ?? null),
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'current_version' => 1,
                    'version' => 1,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                $metadata = $this->files->writeToDisk($document, $file, $extension);

                try {
                    $document->versions()->create([
                        'version_number' => 1,
                        'original_name' => $metadata['original_name'],
                        'path' => $metadata['path'],
                        'mime_type' => $metadata['mime_type'],
                        'size_bytes' => $metadata['size_bytes'],
                        'checksum_sha256' => $metadata['checksum_sha256'],
                        'note' => null,
                        'uploaded_by' => $actor->id,
                    ]);
                } catch (\Throwable $exception) {
                    $this->files->discard($metadata['path']);
                    $metadata = null;

                    throw $exception;
                }

                return $document;
            });
        } catch (\Throwable $exception) {
            if ($metadata !== null) {
                $this->files->discard($metadata['path']);
            }

            throw $exception;
        }

        return $this->show($document->id, $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $documentId, array $data, int $expectedVersion, User $actor): array
    {
        DB::transaction(function () use ($documentId, $data, $expectedVersion, $actor) {
            $document = $this->lockOrFail($documentId);

            Gate::forUser($actor)->authorize('update', $document);
            $this->assertVersion($document, $expectedVersion);
            $this->assertNotArchived($document, 'editing it');
            $this->assertSubjectUnchanged($data);

            $fields = [];

            foreach (['category', 'title', 'document_number', 'document_date', 'valid_from', 'valid_until', 'description'] as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $fields[$field] = in_array($field, ['document_number', 'valid_from', 'valid_until', 'description'], true)
                    ? $this->textOrNull($data[$field])
                    : (string) $data[$field];
            }

            if (isset($fields['title'])) {
                $fields['title'] = trim($fields['title']);
            }

            $document->fill($fields);
            $this->assertStoredValidityPair($document);

            if ($document->getDirty() === []) {
                return;
            }

            $document->version = (int) $document->version + 1;
            $document->updated_by = $actor->id;
            $document->save();
        });

        return $this->show($documentId, $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function addVersion(int $documentId, UploadedFile $file, ?string $note, int $expectedVersion, User $actor): array
    {
        $metadata = null;

        try {
            DB::transaction(function () use ($documentId, $file, $note, $expectedVersion, $actor, &$metadata) {
                $document = $this->lockOrFail($documentId);

                Gate::forUser($actor)->authorize('addVersion', $document);
                $this->assertVersion($document, $expectedVersion);
                $this->assertNotArchived($document, 'adding a new version');

                $extension = $this->files->validateFile($file);
                $metadata = $this->files->writeToDisk($document, $file, $extension);
                $nextVersion = (int) $document->current_version + 1;

                try {
                    $document->versions()->create([
                        'version_number' => $nextVersion,
                        'original_name' => $metadata['original_name'],
                        'path' => $metadata['path'],
                        'mime_type' => $metadata['mime_type'],
                        'size_bytes' => $metadata['size_bytes'],
                        'checksum_sha256' => $metadata['checksum_sha256'],
                        'note' => $this->textOrNull($note),
                        'uploaded_by' => $actor->id,
                    ]);
                } catch (\Throwable $exception) {
                    $this->files->discard($metadata['path']);
                    $metadata = null;

                    throw $exception;
                }

                $document->current_version = $nextVersion;
                $document->version = (int) $document->version + 1;
                $document->updated_by = $actor->id;
                $document->save();
            });
        } catch (\Throwable $exception) {
            if ($metadata !== null) {
                $this->files->discard($metadata['path']);
            }

            throw $exception;
        }

        return $this->show($documentId, $actor);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function versions(int $documentId, array $filters, User $actor): array
    {
        $document = $this->findVisibleOrFail($documentId, $actor);
        $perPage = (int) ($filters['per_page'] ?? config('archives.default_per_page'));

        $paginator = ArchiveFileVersion::query()
            ->where('archive_document_id', $document->id)
            ->with('uploader:id,name')
            ->orderByDesc('version_number')
            ->paginate($perPage);

        return [
            'items' => collect($paginator->items())
                ->map(fn (ArchiveFileVersion $version) => $this->serializeVersion($version))
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

    public function download(int $documentId, int $versionNumber, User $actor, Request $request): StreamedResponse
    {
        [$document, $version] = $this->resolveVersion($documentId, $versionNumber, $actor);

        $this->files->assertReadable($version);
        $this->access->record($document, $version, ArchiveAccessLog::ACTION_DOWNLOAD, $actor, $request);

        return $this->files->downloadResponse($version);
    }

    public function preview(int $documentId, int $versionNumber, User $actor, Request $request): StreamedResponse
    {
        [$document, $version] = $this->resolveVersion($documentId, $versionNumber, $actor);

        $this->files->assertReadable($version);
        $this->access->record($document, $version, ArchiveAccessLog::ACTION_PREVIEW, $actor, $request);

        return $this->files->previewResponse($version);
    }

    /**
     * Riwayat akses download/preview (DEC-38 D3): audit.view + archives.view
     * + ability domain; objek di luar domain tetap 404.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function accessLogs(int $documentId, array $filters, User $actor): array
    {
        $document = $this->findVisibleOrFail($documentId, $actor);

        Gate::forUser($actor)->authorize('accessLogs', $document);

        return $this->access->list($document, $filters);
    }

    /**
     * Arsipkan dokumen (DEC-38 D5): alasan wajib, transaksi + lockForUpdate +
     * expected_version; version dinaikkan sehingga tab lain menerima 409.
     *
     * @return array<string, mixed>
     */
    public function archive(int $documentId, string $reason, int $expectedVersion, User $actor): array
    {
        DB::transaction(function () use ($documentId, $reason, $expectedVersion, $actor) {
            $document = $this->lockOrFail($documentId);

            Gate::forUser($actor)->authorize('archive', $document);
            $this->assertVersion($document, $expectedVersion);

            if ($document->isArchived()) {
                throw ValidationException::withMessages([
                    'archived_at' => ['This archive document is already archived.'],
                ]);
            }

            $document->archived_at = Carbon::now('Asia/Jakarta');
            $document->archive_reason = trim($reason);
            $document->archived_by = $actor->id;
            $document->version = (int) $document->version + 1;
            $document->updated_by = $actor->id;
            $document->save();
        });

        return $this->show($documentId, $actor);
    }

    /**
     * Pulihkan dokumen terarsip (DEC-38 D5). Versi file dan tautan subjek
     * tidak disentuh; tidak ada arsip massal maupun arsip otomatis.
     *
     * @return array<string, mixed>
     */
    public function restore(int $documentId, int $expectedVersion, User $actor): array
    {
        DB::transaction(function () use ($documentId, $expectedVersion, $actor) {
            $document = $this->lockOrFail($documentId);

            Gate::forUser($actor)->authorize('unarchive', $document);
            $this->assertVersion($document, $expectedVersion);

            if (! $document->isArchived()) {
                throw ValidationException::withMessages([
                    'archived_at' => ['This archive document is not archived.'],
                ]);
            }

            $document->archived_at = null;
            $document->archive_reason = null;
            $document->archived_by = null;
            $document->version = (int) $document->version + 1;
            $document->updated_by = $actor->id;
            $document->save();
        });

        return $this->show($documentId, $actor);
    }

    /**
     * @return array{0: ArchiveDocument, 1: ArchiveFileVersion}
     */
    private function resolveVersion(int $documentId, int $versionNumber, User $actor): array
    {
        $document = $this->findVisibleOrFail($documentId, $actor);

        $version = ArchiveFileVersion::query()
            ->where('archive_document_id', $document->id)
            ->where('version_number', $versionNumber)
            ->first();

        if ($version === null) {
            $this->notFound('Archive version not found.');
        }

        return [$document, $version];
    }

    private function assertNotArchived(ArchiveDocument $document, string $action): void
    {
        if ($document->isArchived()) {
            throw ValidationException::withMessages([
                'archived_at' => ['This archive document is archived. Restore it before '.$action.'.'],
            ]);
        }
    }

    private function findVisibleOrFail(int $documentId, User $actor): ArchiveDocument
    {
        $document = ArchiveDocument::query()->find($documentId);

        if ($document === null) {
            $this->notFound('Archive document not found.');
        }

        if (! Gate::forUser($actor)->allows('view', $document)) {
            $this->notFound('Archive document not found.');
        }

        return $document;
    }

    private function lockOrFail(int $documentId): ArchiveDocument
    {
        $document = ArchiveDocument::query()->lockForUpdate()->find($documentId);

        if ($document === null) {
            $this->notFound('Archive document not found.');
        }

        return $document;
    }

    /**
     * @param  Builder<ArchiveDocument>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, User $actor): void
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $category = $filters['category'] ?? null;
        $subjectType = $filters['subject_type'] ?? null;
        $subjectId = $filters['subject_id'] ?? null;
        $validity = $filters['validity'] ?? null;
        $status = $filters['status'] ?? ArchiveDocument::STATUS_ACTIVE;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        // Default daftar hanya dokumen aktif (DEC-38 D5); filter eksplisit
        // sudah divalidasi controller terhadap whitelist config.
        match ($status) {
            ArchiveDocument::STATUS_ARCHIVED => $query->whereNotNull('archived_at'),
            ArchiveDocument::STATUS_ACTIVE => $query->whereNull('archived_at'),
            default => null,
        };

        if ($search !== '') {
            $pattern = $this->likePattern($search);

            $query->where(function (Builder $sub) use ($pattern) {
                $sub->where('title', 'like', $pattern)
                    ->orWhere('document_number', 'like', $pattern);
            });
        }

        if ($category !== null && $category !== '') {
            $query->where('category', $category);
        }

        if ($subjectType !== null && $subjectType !== '') {
            $this->subjects->ensureDomainAbility($actor, $subjectType);
            $query->where('subject_type', $subjectType);
        }

        if ($subjectId !== null && $subjectId !== '') {
            $query->where('subject_id', (int) $subjectId);
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $query->whereDate('document_date', '>=', $dateFrom);
        }

        if ($dateTo !== null && $dateTo !== '') {
            $query->whereDate('document_date', '<=', $dateTo);
        }

        if ($validity !== null && $validity !== '') {
            $today = Carbon::now('Asia/Jakarta')->startOfDay();
            $soon = $today->copy()->addDays((int) config('archives.expiring_soon_days'));

            // Cermin presedensi ArchiveDocument::validityStatus(): upcoming
            // menang atas expired/expiring_soon/valid, jadi filter rentang
            // harus mengecualikan valid_from di masa depan.
            match ($validity) {
                ArchiveDocument::VALIDITY_NONE => $query->whereNull('valid_until'),
                ArchiveDocument::VALIDITY_UPCOMING => $query->whereNotNull('valid_until')
                    ->whereDate('valid_from', '>', $today->toDateString()),
                ArchiveDocument::VALIDITY_EXPIRED => $query->whereNotNull('valid_until')
                    ->whereDate('valid_until', '<', $today->toDateString())
                    ->where(function (Builder $sub) use ($today) {
                        $sub->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today->toDateString());
                    }),
                ArchiveDocument::VALIDITY_EXPIRING_SOON => $query->whereDate('valid_until', '>=', $today->toDateString())
                    ->whereDate('valid_until', '<=', $soon->toDateString())
                    ->where(function (Builder $sub) use ($today) {
                        $sub->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today->toDateString());
                    }),
                ArchiveDocument::VALIDITY_VALID => $query->whereDate('valid_until', '>', $soon->toDateString())
                    ->where(function (Builder $sub) use ($today) {
                        $sub->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today->toDateString());
                    }),
                default => null,
            };
        }
    }

    /**
     * @param  array<string, array<string, mixed>|null>  $summaries
     * @return array<string, mixed>|null
     */
    private function subjectSummary(ArchiveDocument $document, array $summaries): ?array
    {
        if ($document->subject_type === null || $document->subject_id === null) {
            return null;
        }

        return $summaries[$document->subject_type.':'.$document->subject_id] ?? null;
    }

    /**
     * @param  array<string, mixed>|null  $subject
     * @return array<string, mixed>
     */
    private function serializeSummary(ArchiveDocument $document, User $actor, ?array $subject): array
    {
        $gate = Gate::forUser($actor);
        $archived = $document->isArchived();

        return [
            'id' => $document->id,
            'category' => $document->category,
            'category_label' => $document->categoryLabel(),
            'title' => $document->title,
            'document_number' => $document->document_number,
            'document_date' => $document->document_date?->format('Y-m-d'),
            'valid_from' => $document->valid_from?->format('Y-m-d'),
            'valid_until' => $document->valid_until?->format('Y-m-d'),
            'validity_status' => $document->validityStatus(),
            'status' => $document->status(),
            'subject' => $subject,
            'current_version' => (int) $document->current_version,
            'versions_count' => (int) ($document->versions_count ?? $document->versions()->count()),
            'version' => (int) $document->version,
            'updated_at' => $document->updated_at?->toIso8601String(),
            'can' => [
                'view' => $gate->allows('view', $document),
                // Dokumen terarsip tetap dapat dibaca tetapi read-only (D5).
                'update' => ! $archived && $gate->allows('update', $document),
                'add_version' => ! $archived && $gate->allows('addVersion', $document),
                'download' => $gate->allows('download', $document),
                'preview' => $gate->allows('preview', $document),
                'archive' => ! $archived && $gate->allows('archive', $document),
                'restore' => $archived && $gate->allows('unarchive', $document),
                'view_access_log' => $gate->allows('accessLogs', $document),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $subject
     * @return array<string, mixed>
     */
    private function serializeDetail(ArchiveDocument $document, User $actor, ?array $subject): array
    {
        return array_merge($this->serializeSummary($document, $actor, $subject), [
            'description' => $document->description,
            'archived_at' => $document->archived_at?->toIso8601String(),
            'archive_reason' => $document->archive_reason,
            'archived_by' => $document->archiver === null ? null : [
                'id' => $document->archiver->id,
                'name' => $document->archiver->name,
            ],
            'access_logs_count' => (int) ($document->access_logs_count ?? $document->accessLogs()->count()),
            'created_at' => $document->created_at?->toIso8601String(),
            'created_by' => $document->creator === null ? null : [
                'id' => $document->creator->id,
                'name' => $document->creator->name,
            ],
            'updated_by' => $document->updater === null ? null : [
                'id' => $document->updater->id,
                'name' => $document->updater->name,
            ],
            'latest_version' => $this->serializeVersion($document->latestVersion),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeVersion(?ArchiveFileVersion $version): ?array
    {
        if ($version === null) {
            return null;
        }

        return [
            'id' => $version->id,
            'version_number' => (int) $version->version_number,
            'original_name' => $version->original_name,
            'mime_type' => $version->mime_type,
            'size_bytes' => (int) $version->size_bytes,
            'note' => $version->note,
            'uploaded_by' => $version->uploader === null ? null : [
                'id' => $version->uploader->id,
                'name' => $version->uploader->name,
            ],
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertSubjectUnchanged(array $data): void
    {
        foreach (['subject_type', 'subject_id'] as $field) {
            if (array_key_exists($field, $data)) {
                throw ValidationException::withMessages([
                    $field => ['The subject link cannot be changed after creation.'],
                ]);
            }
        }
    }

    private function assertValidityPair(mixed $validFrom, mixed $validUntil): void
    {
        $from = $this->textOrNull($validFrom);
        $until = $this->textOrNull($validUntil);

        if (($from === null) !== ($until === null)) {
            throw ValidationException::withMessages([
                'valid_until' => ['The valid from and valid until dates must be provided together.'],
            ]);
        }

        if ($from !== null && $until !== null && $until < $from) {
            throw ValidationException::withMessages([
                'valid_until' => ['The valid until date may not be before the valid from date.'],
            ]);
        }
    }

    private function assertStoredValidityPair(ArchiveDocument $document): void
    {
        $from = $document->valid_from?->format('Y-m-d');
        $until = $document->valid_until?->format('Y-m-d');

        if (($from === null) !== ($until === null)) {
            throw ValidationException::withMessages([
                'valid_until' => ['The valid from and valid until dates must be provided together.'],
            ]);
        }

        if ($from !== null && $until !== null && $until < $from) {
            throw ValidationException::withMessages([
                'valid_until' => ['The valid until date may not be before the valid from date.'],
            ]);
        }
    }

    private function assertVersion(ArchiveDocument $document, int $expectedVersion): void
    {
        if ((int) $document->version !== $expectedVersion) {
            $this->conflict(
                'expected_version',
                'This archive document was changed by another request. Reload the latest version and try again.',
                $document,
            );
        }
    }

    private function conflict(string $field, string $message, ArchiveDocument $document): never
    {
        abort(response()->json([
            'message' => 'Conflict: '.$message,
            'errors' => [$field => [$message]],
            'latest' => [
                'id' => $document->id,
                'version' => (int) $document->version,
                'current_version' => (int) $document->current_version,
                'updated_at' => $document->updated_at?->toIso8601String(),
            ],
        ], 409));
    }

    private function notFound(string $message): never
    {
        abort(response()->json(['message' => $message], 404));
    }

    private function textOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function likePattern(string $search): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        return '%'.$escaped.'%';
    }
}
