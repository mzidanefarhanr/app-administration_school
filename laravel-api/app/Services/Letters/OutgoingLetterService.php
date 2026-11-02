<?php

namespace App\Services\Letters;

use App\Models\Letter;
use App\Models\LetterApproval;
use App\Models\LetterAttachment;
use App\Models\LetterEvent;
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
        private readonly LetterSettingsService $settings,
        private readonly LetterDocumentService $documents,
        private readonly LetterTemplateRegistry $templates,
        private readonly LetterSubjectService $subjects,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $this->templates->assertValid();
        $readiness = $this->settings->readiness();

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
            'letter_types' => $this->templates->options(),
            'settings_ready' => $readiness['ready'],
            'settings_missing' => $readiness['missing'],
            'typed_fields' => config('letters.typed_fields'),
            'subject_picker' => config('letters.subject_picker'),
            'document' => [
                'preview_statuses' => [
                    Letter::WORKFLOW_DRAFT,
                    Letter::WORKFLOW_REVIEW,
                    Letter::WORKFLOW_APPROVED,
                ],
                'download_statuses' => [
                    Letter::WORKFLOW_ISSUED,
                    Letter::WORKFLOW_CANCELLED,
                ],
                'watermark' => (string) config('letters.document.preview_watermark'),
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
        $letterType = $filters['letter_type'] ?? null;
        $hasDocument = $filters['has_document'] ?? null;
        $letterFrom = $filters['letter_from'] ?? null;
        $letterTo = $filters['letter_to'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? config('letters.default_per_page'));

        $query = Letter::query()
            ->where('direction', Letter::DIRECTION_OUTGOING)
            ->withCount('attachments')
            ->with([
                'creator:id,name',
                'approver:id,name',
                'issuer:id,name',
                'voider:id,name',
                'document' => fn ($query) => $query->select([
                    'id',
                    'letter_id',
                    'template_code',
                    'template_version',
                    'page_count',
                    'generated_at',
                    'pdf_size_bytes',
                ]),
            ]);

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

        if ($letterType !== null && $letterType !== '' && $letterType !== 'all') {
            if ($letterType === 'general') {
                $query->whereNull('letter_type');
            } else {
                $query->where('letter_type', $letterType);
            }
        }

        if ($hasDocument !== null && $hasDocument !== '') {
            if (in_array($hasDocument, [1, '1', true, 'true'], true)) {
                $query->whereHas('document');
            } elseif (in_array($hasDocument, [0, '0', false, 'false'], true)) {
                $query->whereDoesntHave('document');
            }
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
                'letter_type' => $letterType,
                'has_document' => $hasDocument,
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
        $letterType = $this->requestedLetterType($data);
        $typed = $letterType !== null ? $this->typedAttributes($letterType, $data) : null;

        $letter = DB::transaction(function () use ($data, $actor, $letterType, $typed) {
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
                'letter_type' => $letterType,
                'document_subject_type' => $typed['document_subject_type'] ?? null,
                'document_subject_id' => $typed['document_subject_id'] ?? null,
                'document_fields' => $typed['document_fields'] ?? null,
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
            $this->assertTypedIdentityUnchanged($letter, $data);

            $fill = [];

            foreach (['received_date', 'letter_date', 'sender', 'recipient', 'subject', 'category'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fill[$field] = $data[$field];
                }
            }

            if (array_key_exists('notes', $data)) {
                $fill['notes'] = $this->nullableTrimmed($data['notes']);
            }

            if ($letter->isTyped() && array_key_exists('document_fields', $data)) {
                $fill['document_fields'] = $this->subjects->validateFields(
                    (string) $letter->letter_type,
                    $data['document_fields'],
                );
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

            if ($letter->isTyped()) {
                $this->documents->prepare($letter);
            }

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
        $storedPdfPath = null;

        try {
            $letter = DB::transaction(function () use ($letterId, $expectedVersion, $actor, &$storedPdfPath) {
                $letter = $this->lockOutgoing($letterId);

                if ($letter->workflow_status !== Letter::WORKFLOW_APPROVED) {
                    $this->conflict('workflow_status', 'Only approved letters can be issued.', $this->versionContext($letter));
                }

                $this->assertVersion($letter, $expectedVersion);
                $this->assertNotCreator($letter, $actor, 'issue');

                $prepared = $letter->isTyped() ? $this->documents->prepare($letter) : null;

                $now = Carbon::now();
                $number = $this->numbering->next($now, (string) $letter->category);

                $letter->official_number = $number['official_number'];
                $letter->issued_at = $now;
                $letter->issued_by = $actor->id;
                $letter->workflow_status = Letter::WORKFLOW_ISSUED;
                $letter->version = $letter->version + 1;

                $rendered = null;
                $stored = null;

                if ($prepared !== null) {
                    $rendered = $this->documents->renderIssued(
                        $prepared,
                        $letter,
                        $number['official_number'],
                        $now,
                        $actor,
                    );

                    $stored = $this->documents->write($letter, $rendered['bytes']);
                    $storedPdfPath = $stored['path'];
                }

                $letter->save();

                if ($rendered !== null && $stored !== null) {
                    $this->documents->record($letter, $rendered, $stored, $now, $actor);
                }

                $metadata = [
                    'official_number' => $number['official_number'],
                    'period' => $number['period'],
                    'sequence' => $number['sequence'],
                ];

                if ($rendered !== null) {
                    $metadata['template_code'] = $rendered['template']['code'];
                    $metadata['template_version'] = $rendered['template']['version'];
                    $metadata['page_count'] = $rendered['page_count'];
                }

                $this->recordEvent($letter, LetterEvent::EVENT_ISSUED, $actor, $metadata);

                return $letter;
            });
        } catch (UniqueConstraintViolationException) {
            $this->documents->discard($storedPdfPath);
            $storedPdfPath = null;

            $current = $this->findOutgoing($letterId);

            $this->conflict(
                'official_number',
                'The official number was already issued by another request. Reload the latest version.',
                $this->versionContext($current),
            );
        } catch (\Throwable $exception) {
            $this->documents->discard($storedPdfPath);
            $storedPdfPath = null;

            throw $exception;
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
        return $this->settings->activeSchoolName();
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
            'document',
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
            'document' => $this->documents->summary($letter),
            'document_subject' => $this->subjects->summaryForLetter($letter),
            'document_fields' => $letter->letter_type !== null ? ($letter->document_fields ?? []) : null,
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
            'letter_type' => $letter->letter_type,
            'letter_type_label' => $this->templates->label($letter->letter_type)
                ?? ($letter->letter_type === null ? 'General Letter' : $letter->letter_type),
            'has_document' => $this->documents->hasDocument($letter),
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function requestedLetterType(array $data): ?string
    {
        $value = $data['letter_type'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        $letterType = (string) $value;

        if (! $this->templates->isKnown($letterType)) {
            $this->invalid('letter_type', 'The letter type is not available.');
        }

        return $letterType;
    }

    /**
     * Validasi subjek dan field saat create; tipe/subjek immutable setelahnya.
     *
     * @param  array<string, mixed>  $data
     * @return array{document_subject_type: string, document_subject_id: int, document_fields: array<string, mixed>}
     */
    private function typedAttributes(string $letterType, array $data): array
    {
        $definition = $this->templates->definition($letterType);
        $subjectId = (int) ($data['document_subject_id'] ?? 0);

        if ($subjectId < 1) {
            $this->invalid('document_subject_id', 'A subject is required for this letter type.');
        }

        $context = $this->settings->context();

        $this->subjects->resolveSubject($definition['subject_type'], $subjectId, $context['school_year']);

        return [
            'document_subject_type' => $definition['subject_type'],
            'document_subject_id' => $subjectId,
            'document_fields' => $this->subjects->validateFields($letterType, $data['document_fields'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertTypedIdentityUnchanged(Letter $letter, array $data): void
    {
        $requestedType = $data['letter_type'] ?? null;

        if ($letter->isTyped()) {
            if ($requestedType !== null && $requestedType !== ''
                && (string) $requestedType !== (string) $letter->letter_type) {
                $this->invalid('letter_type', 'The letter type cannot be changed after the letter is created.');
            }

            if (array_key_exists('document_subject_id', $data)
                && $data['document_subject_id'] !== null
                && (int) $data['document_subject_id'] !== (int) $letter->document_subject_id) {
                $this->invalid('document_subject_id', 'The letter subject cannot be changed after the letter is created.');
            }

            return;
        }

        if (($requestedType !== null && $requestedType !== '')
            || ! empty($data['document_subject_id'])
            || ! empty($data['document_fields'])) {
            $this->invalid('letter_type', 'The letter type cannot be changed after the letter is created.');
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => [$message],
        ]);
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
