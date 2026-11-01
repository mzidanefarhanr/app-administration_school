<?php

namespace App\Services\Letters;

use App\Models\Letter;
use App\Models\LetterApproval;
use App\Models\LetterAttachment;
use App\Models\LetterEvent;
use App\Models\SchoolProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Surat keluar, approval/sign, dan penomoran resmi (Fase 07b, DEC-33).
 *
 * Semua transisi berjalan dalam transaksi dengan lock baris surat, kontrol
 * `expected_version`, dan event/approval ledger yang immutable. Edit dan
 * lampiran hanya tersedia saat draft; nomor dibuat atomik saat issue.
 */
class OutgoingLetterService
{
    public function __construct(
        private readonly LetterAttachmentService $attachments,
        private readonly LetterNumberService $numbering,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'categories' => config('letters.categories'),
            'workflow_statuses' => $this->workflowStatusOptions(),
            'per_page_options' => config('letters.per_page_options'),
            'default_per_page' => config('letters.default_per_page'),
            'max_file_kb' => config('letters.max_file_kb'),
            'max_files_per_letter' => config('letters.max_files_per_letter'),
            'allowed_extensions' => config('letters.allowed_extensions'),
            'default_sender' => $this->defaultSender(),
            'approval_notes_max_length' => (int) config('letters.approval_notes_max_length'),
            'cancel_reason_min_length' => (int) config('letters.cancel_reason_min_length'),
            'cancel_reason_max_length' => (int) config('letters.cancel_reason_max_length'),
            'numbering' => [
                'format' => $this->numbering->format(),
                'example' => $this->numbering->example(),
                'scope' => $this->numbering->scope(),
                'period' => (string) config('letters.numbering.period'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $workflowStatus = $filters['workflow_status'] ?? null;
        $letterFrom = $filters['letter_from'] ?? null;
        $letterTo = $filters['letter_to'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? config('letters.default_per_page'));

        $query = Letter::query()
            ->where('direction', Letter::DIRECTION_OUTGOING)
            ->withCount('attachments')
            ->with(['creator:id,name', 'approver:id,name', 'issuer:id,name', 'voider:id,name']);

        if ($search !== '') {
            $pattern = $this->likePattern($search);

            $query->where(function ($sub) use ($pattern) {
                $sub->where('official_number', 'like', $pattern)
                    ->orWhere('recipient', 'like', $pattern)
                    ->orWhere('sender', 'like', $pattern)
                    ->orWhere('subject', 'like', $pattern);
            });
        }

        if ($workflowStatus !== null && $workflowStatus !== '' && $workflowStatus !== 'all') {
            $query->where('workflow_status', $workflowStatus);
        }

        if ($letterFrom !== null && $letterFrom !== '') {
            $query->whereDate('letter_date', '>=', $letterFrom);
        }

        if ($letterTo !== null && $letterTo !== '') {
            $query->whereDate('letter_date', '<=', $letterTo);
        }

        $paginator = $query
            ->orderByDesc('letter_date')
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
                'workflow_status' => $workflowStatus,
                'letter_from' => $letterFrom,
                'letter_to' => $letterTo,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $letterId): array
    {
        return $this->serializeDetail($this->findOutgoing($letterId));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, User $actor): array
    {
        $letter = DB::transaction(function () use ($data, $actor) {
            $letter = Letter::create([
                'direction' => Letter::DIRECTION_OUTGOING,
                'version' => 1,
                'received_date' => $data['received_date'],
                'letter_date' => $data['letter_date'],
                'source_number' => null,
                'sender' => trim((string) $data['sender']),
                'recipient' => trim((string) $data['recipient']),
                'subject' => trim((string) $data['subject']),
                'category' => $data['category'],
                'notes' => $this->nullableTrimmed($data['notes'] ?? null),
                'status' => Letter::STATUS_ACTIVE,
                'workflow_status' => Letter::WORKFLOW_DRAFT,
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
            $letter = $this->lockOutgoing($letterId);

            $this->assertDraft($letter);
            $this->assertVersion($letter, $expectedVersion);

            $fill = [];

            foreach (['received_date', 'letter_date', 'sender', 'recipient', 'subject', 'category'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fill[$field] = $data[$field];
                }
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
    public function submit(int $letterId, int $expectedVersion, User $actor): array
    {
        $letter = DB::transaction(function () use ($letterId, $expectedVersion, $actor) {
            $letter = $this->lockOutgoing($letterId);

            if ($letter->workflow_status !== Letter::WORKFLOW_DRAFT) {
                $this->conflict('workflow_status', 'Only draft letters can be submitted.', $this->versionContext($letter));
            }

            $this->assertVersion($letter, $expectedVersion);

            $letter->workflow_status = Letter::WORKFLOW_REVIEW;
            $letter->version = $letter->version + 1;
            $letter->save();

            $this->recordEvent($letter, LetterEvent::EVENT_SUBMITTED, $actor, [
                'from' => Letter::WORKFLOW_DRAFT,
                'to' => Letter::WORKFLOW_REVIEW,
            ]);

            return $letter;
        });

        return $this->serializeDetail($letter);
    }

    /**
     * @return array<string, mixed>
     */
    public function approve(int $letterId, ?string $notes, int $expectedVersion, User $actor): array
    {
        $letter = DB::transaction(function () use ($letterId, $notes, $expectedVersion, $actor) {
            $letter = $this->lockOutgoing($letterId);

            if ($letter->workflow_status !== Letter::WORKFLOW_REVIEW) {
                $this->conflict('workflow_status', 'Only letters in review can be approved.', $this->versionContext($letter));
            }

            $this->assertVersion($letter, $expectedVersion);
            $this->assertNotCreator($letter, $actor, 'approve');

            $letter->workflow_status = Letter::WORKFLOW_APPROVED;
            $letter->approved_at = Carbon::now();
            $letter->approved_by = $actor->id;
            $letter->version = $letter->version + 1;
            $letter->save();

            $approval = $letter->approvals()->create([
                'action' => LetterApproval::ACTION_APPROVED,
                'actor_id' => $actor->id,
                'notes' => $this->nullableTrimmed($notes),
            ]);

            $this->recordEvent($letter, LetterEvent::EVENT_APPROVED, $actor, [
                'approval_id' => $approval->id,
            ]);

            return $letter;
        });

        return $this->serializeDetail($letter);
    }

    /**
     * @return array<string, mixed>
     */
    public function reject(int $letterId, string $reason, int $expectedVersion, User $actor): array
    {
        $letter = DB::transaction(function () use ($letterId, $reason, $expectedVersion, $actor) {
            $letter = $this->lockOutgoing($letterId);

            if ($letter->workflow_status !== Letter::WORKFLOW_REVIEW) {
                $this->conflict('workflow_status', 'Only letters in review can be rejected.', $this->versionContext($letter));
            }

            $this->assertVersion($letter, $expectedVersion);
            $this->assertNotCreator($letter, $actor, 'reject');

            $letter->workflow_status = Letter::WORKFLOW_DRAFT;
            $letter->approved_at = null;
            $letter->approved_by = null;
            $letter->version = $letter->version + 1;
            $letter->save();

            $approval = $letter->approvals()->create([
                'action' => LetterApproval::ACTION_REJECTED,
                'actor_id' => $actor->id,
                'notes' => trim($reason),
            ]);

            $this->recordEvent($letter, LetterEvent::EVENT_REJECTED, $actor, [
                'approval_id' => $approval->id,
            ]);

            return $letter;
        });

        return $this->serializeDetail($letter);
    }

    /**
     * @return array<string, mixed>
     */
    public function issue(int $letterId, int $expectedVersion, User $actor): array
    {
        try {
            $letter = DB::transaction(function () use ($letterId, $expectedVersion, $actor) {
                $letter = $this->lockOutgoing($letterId);

                if ($letter->workflow_status !== Letter::WORKFLOW_APPROVED) {
                    $this->conflict('workflow_status', 'Only approved letters can be issued.', $this->versionContext($letter));
                }

                $this->assertVersion($letter, $expectedVersion);
                $this->assertNotCreator($letter, $actor, 'issue');

                $now = Carbon::now();
                $number = $this->numbering->next($now, (string) $letter->category);

                $letter->official_number = $number['official_number'];
                $letter->issued_at = $now;
                $letter->issued_by = $actor->id;
                $letter->workflow_status = Letter::WORKFLOW_ISSUED;
                $letter->version = $letter->version + 1;
                $letter->save();

                $this->recordEvent($letter, LetterEvent::EVENT_ISSUED, $actor, [
                    'official_number' => $number['official_number'],
                    'period' => $number['period'],
                    'sequence' => $number['sequence'],
                ]);

                return $letter;
            });
        } catch (UniqueConstraintViolationException) {
            $current = $this->findOutgoing($letterId);

            $this->conflict(
                'official_number',
                'The official number was already issued by another request. Reload the latest version.',
                $this->versionContext($current),
            );
        }

        return $this->serializeDetail($letter);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(int $letterId, string $reason, int $expectedVersion, User $actor): array
    {
        $letter = DB::transaction(function () use ($letterId, $reason, $expectedVersion, $actor) {
            $letter = $this->lockOutgoing($letterId);

            if ($letter->workflow_status === Letter::WORKFLOW_CANCELLED || $letter->isVoided()) {
                $this->conflict('workflow_status', 'This letter is already cancelled.', $this->versionContext($letter));
            }

            $from = (string) $letter->workflow_status;

            $requiredAbility = in_array($from, [Letter::WORKFLOW_DRAFT, Letter::WORKFLOW_REVIEW], true)
                ? 'letters.manage'
                : 'letters.approve';

            if (! $actor->hasAbility($requiredAbility)) {
                abort(response()->json([
                    'message' => 'This action requires the '.$requiredAbility.' ability.',
                ], 403));
            }

            $this->assertVersion($letter, $expectedVersion);

            $letter->status = Letter::STATUS_VOID;
            $letter->workflow_status = Letter::WORKFLOW_CANCELLED;
            $letter->voided_at = Carbon::now();
            $letter->voided_by = $actor->id;
            $letter->void_reason = trim($reason);
            $letter->version = $letter->version + 1;
            $letter->save();

            $this->recordEvent($letter, LetterEvent::EVENT_CANCELLED, $actor, [
                'from_status' => $from,
            ]);

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

        $letter = $this->findOutgoing($letterId);
        $this->assertDraft($letter);
        $this->assertAttachmentLimit($letter);

        $stored = null;

        try {
            $stored = $this->attachments->writeToDisk($letter, $file, $extension);

            DB::transaction(function () use ($letterId, $stored, $actor) {
                $letter = $this->lockOutgoing($letterId);

                $this->assertDraft($letter);
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

        return $this->serializeDetail($this->findOutgoing($letterId));
    }

    /**
     * @return array<string, mixed>
     */
    public function removeAttachment(int $letterId, int $attachmentId, User $actor): array
    {
        $letter = DB::transaction(function () use ($letterId, $attachmentId, $actor) {
            $letter = $this->lockOutgoing($letterId);

            $this->assertDraft($letter);

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
        $letter = $this->findOutgoing($letterId);

        $attachment = $letter->attachments()->whereKey($attachmentId)->first();

        if (! $attachment) {
            $this->notFound('Attachment not found.');
        }

        return $attachment;
    }

    private function findOutgoing(int $letterId): Letter
    {
        $letter = Letter::query()
            ->where('direction', Letter::DIRECTION_OUTGOING)
            ->whereKey($letterId)
            ->first();

        if (! $letter) {
            $this->notFound('Letter not found.');
        }

        return $letter;
    }

    private function lockOutgoing(int $letterId): Letter
    {
        $letter = Letter::query()
            ->where('direction', Letter::DIRECTION_OUTGOING)
            ->whereKey($letterId)
            ->lockForUpdate()
            ->first();

        if (! $letter) {
            $this->notFound('Letter not found.');
        }

        return $letter;
    }

    private function assertDraft(Letter $letter): void
    {
        if ($letter->workflow_status !== Letter::WORKFLOW_DRAFT || $letter->isVoided()) {
            $this->conflict(
                'workflow_status',
                'Only draft letters can be edited or have attachments changed.',
                $this->versionContext($letter),
            );
        }
    }

    private function assertVersion(Letter $letter, int $expectedVersion): void
    {
        if ($letter->version !== $expectedVersion) {
            $this->conflict(
                'expected_version',
                'This letter was changed by another request. Reload the latest version and try again.',
                $this->versionContext($letter),
            );
        }
    }

    private function assertNotCreator(Letter $letter, User $actor, string $action): void
    {
        if ((int) $letter->created_by === (int) $actor->id) {
            abort(response()->json([
                'message' => 'The letter creator cannot '.$action.' their own letter.',
            ], 403));
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
     * @return array{letter_version: int, status: string, workflow_status: string|null, official_number: string|null}
     */
    private function versionContext(Letter $letter): array
    {
        return [
            'letter_version' => $letter->version,
            'status' => $letter->status,
            'workflow_status' => $letter->workflow_status,
            'official_number' => $letter->official_number,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function workflowStatusOptions(): array
    {
        $labels = [
            Letter::WORKFLOW_DRAFT => 'Draft',
            Letter::WORKFLOW_REVIEW => 'Review',
            Letter::WORKFLOW_APPROVED => 'Approved',
            Letter::WORKFLOW_ISSUED => 'Issued',
            Letter::WORKFLOW_CANCELLED => 'Cancelled',
        ];

        return array_map(
            fn (string $status) => ['value' => $status, 'label' => $labels[$status]],
            config('letters.outgoing_workflow_statuses'),
        );
    }

    private function defaultSender(): ?string
    {
        $name = SchoolProfile::query()
            ->with('educationSchool:id,npsn,name')
            ->orderBy('id')
            ->first()
            ?->educationSchool
            ?->name;

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetail(Letter $letter): array
    {
        $letter->load([
            'creator:id,name',
            'approver:id,name',
            'issuer:id,name',
            'voider:id,name',
            'attachments' => fn ($query) => $query->orderBy('id'),
            'events' => fn ($query) => $query
                ->with('actor:id,name')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit((int) config('letters.events_limit')),
            'approvals' => fn ($query) => $query
                ->with('actor:id,name')
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
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
            'approvals' => $letter->approvals
                ->map(fn (LetterApproval $approval) => [
                    'id' => $approval->id,
                    'action' => $approval->action,
                    'actor' => [
                        'id' => $approval->actor_id,
                        'name' => $approval->actor?->name,
                    ],
                    'notes' => $approval->notes,
                    'created_at' => $approval->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'dispositions' => [],
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
            'recipient' => $letter->recipient,
            'sender' => $letter->sender,
            'subject' => $letter->subject,
            'category' => $letter->category,
            'received_date' => $letter->received_date?->format('Y-m-d'),
            'letter_date' => $letter->letter_date?->format('Y-m-d'),
            'notes' => $letter->notes,
            'status' => $letter->status,
            'workflow_status' => $letter->workflow_status,
            'lifecycle_status' => $letter->workflow_status,
            'official_number' => $letter->official_number,
            'approved_at' => $letter->approved_at?->toIso8601String(),
            'approved_by' => $letter->approved_by ? [
                'id' => $letter->approved_by,
                'name' => $letter->approver?->name,
            ] : null,
            'issued_at' => $letter->issued_at?->toIso8601String(),
            'issued_by' => $letter->issued_by ? [
                'id' => $letter->issued_by,
                'name' => $letter->issuer?->name,
            ] : null,
            'attachments_count' => $attachmentsCount,
            'version' => $letter->version,
            'voided_at' => $letter->voided_at?->toIso8601String(),
            'void_reason' => $letter->void_reason,
            'cancel_reason' => $letter->void_reason,
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
