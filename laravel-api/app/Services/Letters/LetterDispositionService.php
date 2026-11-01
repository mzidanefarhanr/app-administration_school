<?php

namespace App\Services\Letters;

use App\Models\Employee;
use App\Models\Letter;
use App\Models\LetterDisposition;
use App\Models\LetterEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Disposisi surat masuk per penerima (Fase 07b, DEC-33).
 *
 * Penerima berasal dari direktori pegawai aktif. Semua mutasi memakai lock
 * surat, `expected_version`, dan event immutable; disposisi selesai/dibatalkan
 * tetap menjadi history.
 */
class LetterDispositionService
{
    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [
            'statuses' => $this->statusOptions(),
            'open_statuses' => config('letters.disposition_open_statuses'),
            'per_page_options' => config('letters.disposition_per_page_options'),
            'default_per_page' => config('letters.disposition_default_per_page'),
            'note_max_length' => (int) config('letters.disposition_note_max_length'),
            'follow_up_min_length' => (int) config('letters.disposition_follow_up_min_length'),
            'follow_up_max_length' => (int) config('letters.disposition_follow_up_max_length'),
            'cancel_reason_min_length' => (int) config('letters.disposition_cancel_reason_min_length'),
            'cancel_reason_max_length' => (int) config('letters.disposition_cancel_reason_max_length'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recipients(string $search, int $perPage): array
    {
        $today = $this->today();

        $query = Employee::query()
            ->with('employeeType:id,name')
            ->where(function ($sub) use ($today) {
                $sub->whereNull('exit_date')
                    ->orWhereDate('exit_date', '>=', $today);
            });

        if ($search !== '') {
            $query->where('name', 'like', $this->likePattern($search));
        }

        $paginator = $query->orderBy('name')->orderBy('id')->paginate($perPage);

        return [
            'items' => collect($paginator->items())
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employee_type' => $employee->employeeType?->name,
                ])
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
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? 'open';
        $overdue = filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $recipientId = isset($filters['recipient_employee_id']) && $filters['recipient_employee_id'] !== null && $filters['recipient_employee_id'] !== ''
            ? (int) $filters['recipient_employee_id']
            : null;
        $perPage = (int) ($filters['per_page'] ?? config('letters.disposition_default_per_page'));

        $today = $this->today();

        $query = LetterDisposition::query()
            ->with([
                'letter:id,subject,source_number,sender,received_date',
                'recipient:id,name,employee_type_id,deleted_at',
                'recipient.employeeType:id,name',
                'assigner:id,name',
                'completer:id,name',
                'canceller:id,name',
            ]);

        if ($status === 'open') {
            $query->whereIn('status', LetterDisposition::OPEN_STATUSES);
        } elseif ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($overdue) {
            $query->whereIn('status', LetterDisposition::OPEN_STATUSES)
                ->whereDate('due_date', '<', $today);
        }

        if ($recipientId !== null) {
            $query->where('recipient_employee_id', $recipientId);
        }

        if ($search !== '') {
            $pattern = $this->likePattern($search);

            $query->where(function ($sub) use ($pattern) {
                $sub->whereHas('letter', function ($letterQuery) use ($pattern) {
                    $letterQuery->where('subject', 'like', $pattern)
                        ->orWhere('source_number', 'like', $pattern)
                        ->orWhere('sender', 'like', $pattern);
                })->orWhereHas('recipient', function ($recipientQuery) use ($pattern) {
                    $recipientQuery->where('name', 'like', $pattern);
                });
            });
        }

        $paginator = $query
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate($perPage);

        return [
            'items' => collect($paginator->items())
                ->map(fn (LetterDisposition $disposition) => $this->serialize($disposition))
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
                'status' => $status,
                'overdue' => $overdue,
                'recipient_employee_id' => $recipientId,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $letterId, array $data, User $actor): void
    {
        DB::transaction(function () use ($letterId, $data, $actor) {
            $letter = $this->lockIncoming($letterId);

            if ($letter->isVoided()) {
                $this->conflict('status', 'This letter is void and cannot receive dispositions.', [
                    'letter_version' => $letter->version,
                    'status' => $letter->status,
                ]);
            }

            $recipientIds = array_values(array_unique(array_map('intval', $data['recipients'])));

            $employees = $this->eligibleEmployees($recipientIds);

            if ($employees->count() !== count($recipientIds)) {
                throw ValidationException::withMessages([
                    'recipients' => ['One or more recipients are not active employees.'],
                ]);
            }

            $activeRecipients = $letter->dispositions()
                ->open()
                ->whereIn('recipient_employee_id', $recipientIds)
                ->pluck('recipient_employee_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($activeRecipients !== []) {
                throw ValidationException::withMessages([
                    'recipients' => ['One or more recipients already have an open disposition for this letter.'],
                ]);
            }

            foreach ($recipientIds as $recipientId) {
                $disposition = $letter->dispositions()->create([
                    'recipient_employee_id' => $recipientId,
                    'assigned_by' => $actor->id,
                    'due_date' => $data['due_date'],
                    'status' => LetterDisposition::STATUS_PENDING,
                    'note' => $this->nullableTrimmed($data['note'] ?? null),
                    'version' => 1,
                ]);

                $this->recordEvent($letter, LetterEvent::EVENT_DISPOSITION_CREATED, $actor, [
                    'disposition_id' => $disposition->id,
                    'recipient_employee_id' => $disposition->recipient_employee_id,
                    'due_date' => $disposition->due_date?->format('Y-m-d'),
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $letterId, int $dispositionId, array $data, int $expectedVersion, User $actor): void
    {
        DB::transaction(function () use ($letterId, $dispositionId, $data, $expectedVersion, $actor) {
            $letter = $this->lockIncoming($letterId);

            if ($letter->isVoided()) {
                $this->conflict('status', 'This letter is void; dispositions are read-only.', [
                    'letter_version' => $letter->version,
                    'status' => $letter->status,
                ]);
            }

            $disposition = $this->lockDisposition($letter->id, $dispositionId);

            $this->assertVersion($disposition, $expectedVersion);

            if (! $disposition->isOpen()) {
                $this->conflict('status', 'Completed or cancelled dispositions are read-only.', $this->versionContext($disposition));
            }

            $currentStatus = (string) $disposition->status;
            $targetStatus = (string) ($data['status'] ?? $currentStatus);

            $statusChanged = $targetStatus !== $currentStatus;

            if ($statusChanged && ! $this->transitionAllowed($currentStatus, $targetStatus)) {
                $this->conflict(
                    'status',
                    'This disposition status transition is not allowed.',
                    $this->versionContext($disposition),
                );
            }

            if (array_key_exists('due_date', $data)) {
                $disposition->due_date = $data['due_date'];
            }

            if (array_key_exists('note', $data)) {
                $disposition->note = $this->nullableTrimmed($data['note']);
            }

            if (array_key_exists('follow_up_note', $data)) {
                $disposition->follow_up_note = $this->nullableTrimmed($data['follow_up_note']);
            }

            if ($statusChanged) {
                $disposition->status = $targetStatus;
            }

            if ($targetStatus === LetterDisposition::STATUS_COMPLETED) {
                $followUp = $this->nullableTrimmed($disposition->follow_up_note);
                $min = (int) config('letters.disposition_follow_up_min_length');

                if ($followUp === null || mb_strlen($followUp) < $min) {
                    throw ValidationException::withMessages([
                        'follow_up_note' => ["A follow-up note of at least {$min} characters is required to complete a disposition."],
                    ]);
                }

                $disposition->follow_up_note = $followUp;
                $disposition->completed_at = Carbon::now();
                $disposition->completed_by = $actor->id;
            }

            if ($targetStatus === LetterDisposition::STATUS_CANCELLED) {
                $reason = $this->nullableTrimmed($data['cancel_reason'] ?? $disposition->cancel_reason);
                $min = (int) config('letters.disposition_cancel_reason_min_length');

                if ($reason === null || mb_strlen($reason) < $min) {
                    throw ValidationException::withMessages([
                        'cancel_reason' => ["A cancellation reason of at least {$min} characters is required."],
                    ]);
                }

                $disposition->cancel_reason = $reason;
                $disposition->cancelled_at = Carbon::now();
                $disposition->cancelled_by = $actor->id;
            }

            $changedFields = array_keys($disposition->getDirty());

            if ($changedFields === []) {
                return;
            }

            $disposition->version = $disposition->version + 1;
            $disposition->save();

            if ($statusChanged && $targetStatus === LetterDisposition::STATUS_COMPLETED) {
                $this->recordEvent($letter, LetterEvent::EVENT_DISPOSITION_COMPLETED, $actor, [
                    'disposition_id' => $disposition->id,
                ]);
            } elseif ($statusChanged && $targetStatus === LetterDisposition::STATUS_CANCELLED) {
                $this->recordEvent($letter, LetterEvent::EVENT_DISPOSITION_CANCELLED, $actor, [
                    'disposition_id' => $disposition->id,
                ]);
            } else {
                $this->recordEvent($letter, LetterEvent::EVENT_DISPOSITION_UPDATED, $actor, [
                    'disposition_id' => $disposition->id,
                    'changed_fields' => array_values($changedFields),
                ]);
            }
        });
    }

    public function cancel(int $letterId, int $dispositionId, string $reason, int $expectedVersion, User $actor): void
    {
        DB::transaction(function () use ($letterId, $dispositionId, $reason, $expectedVersion, $actor) {
            $letter = $this->lockIncoming($letterId);

            if ($letter->isVoided()) {
                $this->conflict('status', 'This letter is void; dispositions are read-only.', [
                    'letter_version' => $letter->version,
                    'status' => $letter->status,
                ]);
            }

            $disposition = $this->lockDisposition($letter->id, $dispositionId);

            if (! $disposition->isOpen()) {
                $this->conflict('status', 'Completed or cancelled dispositions cannot be cancelled.', $this->versionContext($disposition));
            }

            $this->assertVersion($disposition, $expectedVersion);

            $disposition->status = LetterDisposition::STATUS_CANCELLED;
            $disposition->cancelled_at = Carbon::now();
            $disposition->cancelled_by = $actor->id;
            $disposition->cancel_reason = trim($reason);
            $disposition->version = $disposition->version + 1;
            $disposition->save();

            $this->recordEvent($letter, LetterEvent::EVENT_DISPOSITION_CANCELLED, $actor, [
                'disposition_id' => $disposition->id,
            ]);
        });
    }

    /**
     * @param  array<int, int>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, Employee>
     */
    private function eligibleEmployees(array $ids)
    {
        $today = $this->today();

        return Employee::query()
            ->whereIn('id', $ids)
            ->where(function ($sub) use ($today) {
                $sub->whereNull('exit_date')
                    ->orWhereDate('exit_date', '>=', $today);
            })
            ->get()
            ->keyBy('id');
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

    private function lockDisposition(int $letterId, int $dispositionId): LetterDisposition
    {
        $disposition = LetterDisposition::query()
            ->where('letter_id', $letterId)
            ->whereKey($dispositionId)
            ->lockForUpdate()
            ->first();

        if (! $disposition) {
            $this->notFound('Disposition not found.');
        }

        return $disposition;
    }

    private function transitionAllowed(string $from, string $to): bool
    {
        if ($from === LetterDisposition::STATUS_PENDING) {
            return in_array($to, [LetterDisposition::STATUS_IN_PROGRESS, LetterDisposition::STATUS_CANCELLED], true);
        }

        if ($from === LetterDisposition::STATUS_IN_PROGRESS) {
            return in_array($to, [LetterDisposition::STATUS_COMPLETED, LetterDisposition::STATUS_CANCELLED], true);
        }

        return false;
    }

    private function assertVersion(LetterDisposition $disposition, int $expectedVersion): void
    {
        if ($disposition->version !== $expectedVersion) {
            $this->conflict(
                'expected_version',
                'This disposition was changed by another request. Reload the latest version and try again.',
                $this->versionContext($disposition),
            );
        }
    }

    /**
     * @return array{disposition_version: int, status: string}
     */
    private function versionContext(LetterDisposition $disposition): array
    {
        return [
            'disposition_version' => $disposition->version,
            'status' => $disposition->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(LetterDisposition $disposition): array
    {
        $today = $this->today();
        $overdue = $disposition->isOpen()
            && $disposition->due_date !== null
            && $disposition->due_date->format('Y-m-d') < $today;

        return [
            'id' => $disposition->id,
            'letter' => [
                'id' => $disposition->letter_id,
                'reference' => '#'.str_pad((string) $disposition->letter_id, 6, '0', STR_PAD_LEFT),
                'subject' => $disposition->letter?->subject,
                'source_number' => $disposition->letter?->source_number,
                'sender' => $disposition->letter?->sender,
                'received_date' => $disposition->letter?->received_date?->format('Y-m-d'),
            ],
            'recipient' => [
                'id' => $disposition->recipient_employee_id,
                'name' => $disposition->recipient?->name,
                'employee_type' => $disposition->recipient?->employeeType?->name,
            ],
            'due_date' => $disposition->due_date?->format('Y-m-d'),
            'status' => $disposition->status,
            'overdue' => $overdue,
            'note' => $disposition->note,
            'follow_up_note' => $disposition->follow_up_note,
            'completed_at' => $disposition->completed_at?->toIso8601String(),
            'completed_by' => $disposition->completed_by ? [
                'id' => $disposition->completed_by,
                'name' => $disposition->completer?->name,
            ] : null,
            'cancelled_at' => $disposition->cancelled_at?->toIso8601String(),
            'cancelled_by' => $disposition->cancelled_by ? [
                'id' => $disposition->cancelled_by,
                'name' => $disposition->canceller?->name,
            ] : null,
            'cancel_reason' => $disposition->cancel_reason,
            'version' => $disposition->version,
            'assigned_by' => [
                'id' => $disposition->assigned_by,
                'name' => $disposition->assigner?->name,
            ],
            'created_at' => $disposition->created_at?->toIso8601String(),
            'updated_at' => $disposition->updated_at?->toIso8601String(),
        ];
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
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        $labels = [
            LetterDisposition::STATUS_PENDING => 'Pending',
            LetterDisposition::STATUS_IN_PROGRESS => 'In Progress',
            LetterDisposition::STATUS_COMPLETED => 'Completed',
            LetterDisposition::STATUS_CANCELLED => 'Cancelled',
        ];

        return array_map(
            fn (string $status) => ['value' => $status, 'label' => $labels[$status]],
            config('letters.disposition_statuses'),
        );
    }

    private function today(): string
    {
        return Carbon::now('Asia/Jakarta')->toDateString();
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
