<?php

namespace App\Services\Archives;

use App\Models\ArchiveDocument;
use App\Models\ArchiveFileVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Orkestrasi metadata, versi file, pencarian lintas domain, dan unduhan
 * arsip (Fase 09a, DEC-37).
 */
class ArchiveDocumentService
{
    public function __construct(
        private readonly ArchiveFileService $files,
        private readonly ArchiveSubjectService $subjects,
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

        $document->loadCount('versions');
        $document->load([
            'creator:id,name',
            'updater:id,name',
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

    public function download(int $documentId, int $versionNumber, User $actor): StreamedResponse
    {
        $document = $this->findVisibleOrFail($documentId, $actor);

        $version = ArchiveFileVersion::query()
            ->where('archive_document_id', $document->id)
            ->where('version_number', $versionNumber)
            ->first();

        if ($version === null) {
            $this->notFound('Archive version not found.');
        }

        return $this->files->downloadResponse($version);
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
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

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
            'subject' => $subject,
            'current_version' => (int) $document->current_version,
            'versions_count' => (int) ($document->versions_count ?? $document->versions()->count()),
            'version' => (int) $document->version,
            'updated_at' => $document->updated_at?->toIso8601String(),
            'can' => [
                'view' => $gate->allows('view', $document),
                'update' => $gate->allows('update', $document),
                'add_version' => $gate->allows('addVersion', $document),
                'download' => $gate->allows('download', $document),
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
