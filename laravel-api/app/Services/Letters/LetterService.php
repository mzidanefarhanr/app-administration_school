<?php

namespace App\Services\Letters;

use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Agenda surat masuk dan lampiran (Fase 07a, DEC-32).
 *
 * Semua mutasi berjalan dalam transaksi dengan lock baris surat, kontrol
 * `expected_version`, dan event domain yang immutable. Surat void bersifat
 * read-only; lampiran memakai soft delete dan file tetap disimpan.
 */
class LetterService
{
    public function __construct(private readonly LetterAttachmentService $attachments) {}

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'categories' => config('letters.categories'),
            'statuses' => [
                ['value' => Letter::STATUS_ACTIVE, 'label' => 'Active'],
                ['value' => Letter::STATUS_VOID, 'label' => 'Void'],
            ],
            'per_page_options' => config('letters.per_page_options'),
            'default_per_page' => config('letters.default_per_page'),
            'max_file_kb' => config('letters.max_file_kb'),
            'max_files_per_letter' => config('letters.max_files_per_letter'),
            'allowed_extensions' => config('letters.allowed_extensions'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $category = $filters['category'] ?? null;
        $status = $filters['status'] ?? Letter::STATUS_ACTIVE;
        $receivedFrom = $filters['received_from'] ?? null;
        $receivedTo = $filters['received_to'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? config('letters.default_per_page'));

        $query = Letter::query()
            ->where('direction', Letter::DIRECTION_INCOMING)
            ->withCount('attachments')
            ->with(['creator:id,name', 'voider:id,name']);

        if ($search !== '') {
            $pattern = $this->likePattern($search);

            $query->where(function ($sub) use ($pattern) {
                $sub->where('source_number', 'like', $pattern)
                    ->orWhere('sender', 'like', $pattern)
                    ->orWhere('subject', 'like', $pattern);
            });
        }

        if ($category !== null && $category !== '') {
            $query->where('category', $category);
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($receivedFrom !== null && $receivedFrom !== '') {
            $query->whereDate('received_date', '>=', $receivedFrom);
        }

        if ($receivedTo !== null && $receivedTo !== '') {
            $query->whereDate('received_date', '<=', $receivedTo);
        }

        $paginator = $query
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'items' => collect($paginator->items())
                ->map(fn (Letter $letter) => $this->serializeLetter($letter, (int) $letter->attachments_count))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'filters' => [
                'search' => $search,
                'category' => $category,
                'status' => $status,
                'received_from' => $receivedFrom,
                'received_to' => $receivedTo,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $letterId): array
    {
        return $this->serializeDetail($this->findIncoming($letterId));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, User $actor): array
    {
        $letter = DB::transaction(function () use ($data, $actor) {
            $letter = Letter::create([
                'direction' => Letter::DIRECTION_INCOMING,
                'version' => 1,
                'received_date' => $data['received_date'],
                'letter_date' => $data['letter_date'],
                'source_number' => $this->nullableTrimmed($data['source_number'] ?? null),
                'sender' => trim((string) $data['sender']),
                'subject' => trim((string) $data['subject']),
                'category' => $data['category'],
                'notes' => $this->nullableTrimmed($data['notes'] ?? null),
                'status' => Letter::STATUS_ACTIVE,
                'created_by' => $actor->id,
            ]);

            $this->recordEvent($letter, LetterEvent::EVENT_CREATED, $actor);

            return $letter;
        });

        return $this->serializeDetail($letter);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $letterId, array $data, int $expectedVersion, User $actor): array
    {
        $letter = DB::transaction(function () use ($letterId, $data, $expectedVersion, $actor) {
            $letter = $this->lockIncoming($letterId);

            $this->assertActive($letter);
            $this->assertVersion($letter, $expectedVersion);

            $fill = [];

            foreach (['received_date', 'letter_date', 'sender', 'subject', 'category'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fill[$field] = $data[$field];
                }
            }

            if (array_key_exists('source_number', $data)) {
                $fill['source_number'] = $this->nullableTrimmed($data['source_number']);
            }

            if (array_key_exists('notes', $data)) {
                $fill['notes'] = $this->nullableTrimmed($data['notes']);
            }

            $letter->fill($fill);
            $changedFields = array_keys($letter->getDirty());

            if ($changedFields === []) {
                return $letter;
            }

            $letter->version = $letter->version + 1;
            $letter->save();

            $this->recordEvent($letter, LetterEvent::EVENT_UPDATED, $actor, [
                'changed_fields' => array_values($changedFields),
            ]);

            return $letter;
        });

        return $this->serializeDetail($letter);
    }

    /**
     * @return array<string, mixed>
     */
    public function void(int $letterId, string $reason, int $expectedVersion, User $actor): array
    {
        $letter = DB::transaction(function () use ($letterId, $reason, $expectedVersion, $actor) {
            $letter = $this->lockIncoming($letterId);

            if ($letter->isVoided()) {
                $this->conflict('status', 'This letter is already void.', [
                    'letter_version' => $letter->version,
                    'status' => $letter->status,
                ]);
            }

            $this->assertVersion($letter, $expectedVersion);

            $letter->status = Letter::STATUS_VOID;
            $letter->voided_at = Carbon::now();
            $letter->voided_by = $actor->id;
            $letter->void_reason = trim($reason);
            $letter->version = $letter->version + 1;
            $letter->save();

            $this->recordEvent($letter, LetterEvent::EVENT_VOIDED, $actor);

            return $letter;
        });

        return $this->serializeDetail($letter);
    }

    /**
     * @return array<string, mixed>
     */
    public function addAttachment(int $letterId, UploadedFile $file, User $actor): array
    {
        $extension = $this->attachments->validateFile($file);

        $letter = $this->findIncoming($letterId);
        $this->assertActive($letter);
        $this->assertAttachmentLimit($letter);

        $stored = null;

        try {
            $stored = $this->attachments->writeToDisk($letter, $file, $extension);

            DB::transaction(function () use ($letterId, $stored, $actor) {
                $letter = $this->lockIncoming($letterId);

                $this->assertActive($letter);
                $this->assertAttachmentLimit($letter);

                $attachment = $letter->attachments()->create([
                    'original_name' => $stored['original_name'],
                    'path' => $stored['path'],
                    'mime_type' => $stored['mime_type'],
                    'size_bytes' => $stored['size_bytes'],
                    'checksum_sha256' => $stored['checksum_sha256'],
                    'uploaded_by' => $actor->id,
                ]);

                $this->recordEvent($letter, LetterEvent::EVENT_ATTACHMENT_ADDED, $actor, [
                    'attachment_id' => $attachment->id,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                ]);
            });
        } catch (\Throwable $exception) {
            if ($stored !== null) {
                $this->attachments->discard($stored['path']);
            }

            throw $exception;
        }

        return $this->serializeDetail($this->findIncoming($letterId));
    }

    /**
     * @return array<string, mixed>
     */
    public function removeAttachment(int $letterId, int $attachmentId, User $actor): array
    {
        $letter = DB::transaction(function () use ($letterId, $attachmentId, $actor) {
            $letter = $this->lockIncoming($letterId);

            $this->assertActive($letter);

            $attachment = $letter->attachments()
                ->whereKey($attachmentId)
                ->lockForUpdate()
                ->first();

            if (! $attachment) {
                $this->notFound('Attachment not found.');
            }

            $this->attachments->delete($attachment);

            $this->recordEvent($letter, LetterEvent::EVENT_ATTACHMENT_REMOVED, $actor, [
                'attachment_id' => $attachment->id,
            ]);

            return $letter;
        });

        return $this->serializeDetail($letter);
    }

    public function attachmentForDownload(int $letterId, int $attachmentId): LetterAttachment
    {
        $letter = $this->findIncoming($letterId);

        $attachment = $letter->attachments()->whereKey($attachmentId)->first();

        if (! $attachment) {
            $this->notFound('Attachment not found.');
        }

        return $attachment;
    }

    private function findIncoming(int $letterId): Letter
    {
        $letter = Letter::query()
            ->where('direction', Letter::DIRECTION_INCOMING)
            ->whereKey($letterId)
            ->first();

        if (! $letter) {
            $this->notFound('Letter not found.');
        }

        return $letter;
    }

    private function lockIncoming(int $letterId): Letter
    {
        $letter = Letter::query()
            ->where('direction', Letter::DIRECTION_INCOMING)
            ->whereKey($letterId)
            ->lockForUpdate()
            ->first();

        if (! $letter) {
            $this->notFound('Letter not found.');
        }

        return $letter;
    }

    private function assertActive(Letter $letter): void
    {
        if ($letter->isVoided()) {
            $this->conflict('status', 'This letter is void and can no longer be changed.', [
                'letter_version' => $letter->version,
                'status' => $letter->status,
            ]);
        }
    }

    private function assertVersion(Letter $letter, int $expectedVersion): void
    {
        if ($letter->version !== $expectedVersion) {
            $this->conflict(
                'expected_version',
                'This letter was changed by another request. Reload the latest version and try again.',
                ['letter_version' => $letter->version],
            );
        }
    }

    private function assertAttachmentLimit(Letter $letter): void
    {
        $limit = (int) config('letters.max_files_per_letter');

        if ($letter->attachments()->count() >= $limit) {
            throw ValidationException::withMessages([
                'file' => ["A letter can have at most {$limit} attachments."],
            ]);
        }
    }

    private function recordEvent(Letter $letter, string $event, User $actor, ?array $metadata = null): void
    {
        $letter->events()->create([
            'event' => $event,
            'actor_id' => $actor->id,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetail(Letter $letter): array
    {
        $letter->load([
            'creator:id,name',
            'voider:id,name',
            'attachments' => fn ($query) => $query->orderBy('id'),
            'events' => fn ($query) => $query
                ->with('actor:id,name')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit((int) config('letters.events_limit')),
        ]);

        return $this->serializeLetter($letter, $letter->attachments->count()) + [
            'attachments' => $letter->attachments
                ->map(fn (LetterAttachment $attachment) => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'uploaded_at' => $attachment->created_at?->toIso8601String(),
                    'uploader' => $attachment->uploaded_by ? [
                        'id' => $attachment->uploaded_by,
                    ] : null,
                ])
                ->values()
                ->all(),
            'events' => $letter->events
                ->map(fn (LetterEvent $event) => [
                    'id' => $event->id,
                    'event' => $event->event,
                    'actor' => [
                        'id' => $event->actor_id,
                        'name' => $event->actor?->name,
                    ],
                    'metadata' => $event->metadata,
                    'created_at' => $event->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLetter(Letter $letter, int $attachmentsCount): array
    {
        return [
            'id' => $letter->id,
            'reference' => '#'.str_pad((string) $letter->id, 6, '0', STR_PAD_LEFT),
            'direction' => $letter->direction,
            'received_date' => $letter->received_date?->format('Y-m-d'),
            'letter_date' => $letter->letter_date?->format('Y-m-d'),
            'source_number' => $letter->source_number,
            'sender' => $letter->sender,
            'subject' => $letter->subject,
            'category' => $letter->category,
            'notes' => $letter->notes,
            'status' => $letter->status,
            'version' => $letter->version,
            'attachments_count' => $attachmentsCount,
            'voided_at' => $letter->voided_at?->toIso8601String(),
            'void_reason' => $letter->void_reason,
            'voided_by' => $letter->voided_by ? [
                'id' => $letter->voided_by,
                'name' => $letter->voider?->name,
            ] : null,
            'created_by' => [
                'id' => $letter->created_by,
                'name' => $letter->creator?->name,
            ],
            'created_at' => $letter->created_at?->toIso8601String(),
            'updated_at' => $letter->updated_at?->toIso8601String(),
        ];
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function likePattern(string $search): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        return '%'.$escaped.'%';
    }

    private function conflict(string $field, string $message, array $currentVersions = []): never
    {
        $payload = [
            'message' => 'Conflict: '.$message,
            'errors' => [$field => [$message]],
        ];

        if ($currentVersions !== []) {
            $payload['current_versions'] = $currentVersions;
        }

        abort(response()->json($payload, 409));
    }

    private function notFound(string $message): never
    {
        abort(response()->json(['message' => $message], 404));
    }
}
