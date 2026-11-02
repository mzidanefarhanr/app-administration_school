<?php

namespace App\Services\Leaves;

use App\Models\AcademicSetting;
use App\Models\Employee;
use App\Models\LeaveAttachment;
use App\Models\LeaveDecision;
use App\Models\LeaveRequest;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentRombel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pengajuan izin siswa dan pegawai (Fase 08a, DEC-35).
 *
 * Semua mutasi berjalan dalam transaksi dengan lock baris izin/subjek,
 * kontrol `expected_version`, dan pengecekan ability bernama. Alasan izin
 * tidak pernah dicatat ke audit.
 */
class LeaveRequestService
{
    public function __construct(
        private readonly LeaveAttachmentService $attachments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function options(User $actor): array
    {
        $types = [];

        foreach ((array) config('leaves.types') as $subjectType => $definitions) {
            $types[$subjectType] = collect($definitions)
                ->map(fn (array $definition, string $code) => [
                    'code' => $code,
                    'label' => $definition['label'],
                    'max_days' => (int) $definition['max_days'],
                    'attachment_required' => (bool) ($definition['attachment_required'] ?? false),
                ])
                ->values()
                ->all();
        }

        $activeYear = $this->activeSchoolYear();
        $selfEmployee = $actor->hasAbility('leaves.request') ? $this->selfEmployee($actor) : null;

        return [
            'types' => $types,
            'subject_types' => collect((array) config('leaves.subject_types'))
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'statuses' => collect((array) config('leaves.statuses'))
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'max_range_days' => (int) config('leaves.max_range_days'),
            'reason' => config('leaves.reason'),
            'cancel_reason' => config('leaves.cancel_reason'),
            'per_page_options' => config('leaves.per_page_options'),
            'default_per_page' => (int) config('leaves.default_per_page'),
            'attachment' => [
                'max_file_kb' => (int) config('leaves.attachment.max_file_kb'),
                'max_files_per_request' => (int) config('leaves.attachment.max_files_per_request'),
                'allowed_extensions' => config('leaves.attachment.allowed_extensions'),
            ],
            'active_school_year' => $activeYear === null ? null : [
                'id' => $activeYear->id,
                'name' => $activeYear->name,
                'start_date' => $activeYear->start_date?->format('Y-m-d'),
                'end_date' => $activeYear->end_date?->format('Y-m-d'),
            ],
            'self_employee' => $selfEmployee === null ? null : [
                'id' => $selfEmployee->id,
                'name' => $selfEmployee->name,
                'nuptk' => $selfEmployee->nuptk,
                'employee_type' => $selfEmployee->employeeType?->name,
            ],
            'can' => [
                'view' => $actor->hasAbility('leaves.view'),
                'request' => $actor->hasAbility('leaves.request'),
                'manage' => $actor->hasAbility('leaves.manage'),
                'approve' => $actor->hasAbility('leaves.approve'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(array $filters, User $actor): array
    {
        return $this->paginate($this->summaryQuery(), $filters, $actor);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function mine(array $filters, User $actor): array
    {
        $employeeIds = Employee::withTrashed()
            ->where('user_id', $actor->id)
            ->select('id');

        $query = $this->summaryQuery()
            ->where('subject_type', LeaveRequest::SUBJECT_EMPLOYEE)
            ->whereIn('employee_id', $employeeIds);

        return $this->paginate($query, $filters, $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $leaveId, User $actor): array
    {
        return $this->serializeDetail($this->findVisibleOrFail($leaveId, $actor), $actor);
    }

    public function findVisibleOrFail(int $leaveId, User $actor): LeaveRequest
    {
        $leave = $this->detailQuery()->find($leaveId);

        if ($leave === null) {
            $this->notFound('Leave request not found.');
        }

        if (! Gate::forUser($actor)->allows('view', $leave)) {
            $this->notFound('Leave request not found.');
        }

        return $leave;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data, User $actor): array
    {
        $leave = DB::transaction(function () use ($data, $actor) {
            $subject = $this->resolveSubjectData($data, $actor, true);

            return LeaveRequest::create([
                'subject_type' => $subject['subject_type'],
                'student_id' => $subject['student_id'],
                'employee_id' => $subject['employee_id'],
                'school_year_id' => $subject['school_year_id'],
                'student_rombel_id' => $subject['student_rombel_id'],
                'type' => $subject['type'],
                'start_date' => $subject['start_date'],
                'end_date' => $subject['end_date'],
                'reason' => $subject['reason'],
                'status' => LeaveRequest::STATUS_DRAFT,
                'version' => 1,
                'requested_by' => $actor->id,
            ]);
        });

        return $this->show($leave->id, $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(int $leaveId, array $data, int $expectedVersion, User $actor): array
    {
        DB::transaction(function () use ($leaveId, $data, $expectedVersion, $actor) {
            $leave = $this->lockVisibleOrFail($leaveId, $actor);

            Gate::forUser($actor)->authorize('update', $leave);
            $this->assertDraft($leave, 'Only draft leave requests can be edited.');
            $this->assertVersion($leave, $expectedVersion);

            $subject = $this->resolveSubjectData($data, $actor, true);

            $leave->fill([
                'subject_type' => $subject['subject_type'],
                'student_id' => $subject['student_id'],
                'employee_id' => $subject['employee_id'],
                'school_year_id' => $subject['school_year_id'],
                'student_rombel_id' => $subject['student_rombel_id'],
                'type' => $subject['type'],
                'start_date' => $subject['start_date'],
                'end_date' => $subject['end_date'],
                'reason' => $subject['reason'],
            ]);

            if ($leave->getDirty() === []) {
                return;
            }

            $leave->version = $leave->version + 1;
            $leave->save();
        });

        return $this->show($leaveId, $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function submit(int $leaveId, int $expectedVersion, User $actor): array
    {
        DB::transaction(function () use ($leaveId, $expectedVersion, $actor) {
            $leave = $this->lockVisibleOrFail($leaveId, $actor);

            Gate::forUser($actor)->authorize('submit', $leave);
            $this->assertDraft($leave, 'Only draft leave requests can be submitted.');
            $this->assertVersion($leave, $expectedVersion);

            $this->lockStoredSubject($leave);
            $this->assertStoredDates($leave);
            $this->assertNoOverlap($leave);
            $this->assertAttachmentRequirement($leave);

            $leave->status = LeaveRequest::STATUS_SUBMITTED;
            $leave->submitted_at = Carbon::now('Asia/Jakarta');
            $leave->submitted_by = $actor->id;
            $leave->version = $leave->version + 1;
            $leave->save();
        });

        return $this->show($leaveId, $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(int $leaveId, ?string $reason, int $expectedVersion, User $actor): array
    {
        DB::transaction(function () use ($leaveId, $reason, $expectedVersion, $actor) {
            $leave = $this->lockVisibleOrFail($leaveId, $actor);

            Gate::forUser($actor)->authorize('cancel', $leave);

            if (! in_array($leave->status, [LeaveRequest::STATUS_DRAFT, LeaveRequest::STATUS_SUBMITTED], true)) {
                $this->conflict(
                    'status',
                    $leave->status === LeaveRequest::STATUS_APPROVED
                        ? 'Approved leave requests can only be cancelled by an approver in a later phase.'
                        : 'This leave request can no longer be cancelled.',
                    $leave,
                );
            }

            $this->assertVersion($leave, $expectedVersion);

            $reason = $reason === null ? null : trim($reason);

            if ($leave->isSubmitted() && ($reason === null || $reason === '')) {
                throw ValidationException::withMessages([
                    'reason' => ['A cancellation reason is required for submitted leave requests.'],
                ]);
            }

            if ($reason !== null && $reason !== '') {
                $this->assertTextLength('reason', $reason, config('leaves.cancel_reason'), 'cancellation reason');
            }

            $fromStatus = $leave->status;
            $leave->status = LeaveRequest::STATUS_CANCELLED;
            $leave->cancelled_at = Carbon::now('Asia/Jakarta');
            $leave->cancelled_by = $actor->id;
            $leave->version = $leave->version + 1;
            $leave->save();

            LeaveDecision::create([
                'leave_request_id' => $leave->id,
                'action' => LeaveDecision::ACTION_CANCELLED,
                'from_status' => $fromStatus,
                'to_status' => LeaveRequest::STATUS_CANCELLED,
                'actor_id' => $actor->id,
                'reason' => $reason === '' ? null : $reason,
            ]);
        });

        return $this->show($leaveId, $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function addAttachment(int $leaveId, UploadedFile $file, User $actor): array
    {
        DB::transaction(function () use ($leaveId, $file, $actor) {
            $leave = $this->lockVisibleOrFail($leaveId, $actor);

            Gate::forUser($actor)->authorize('uploadAttachment', $leave);
            $this->assertAttachmentWritable($leave);
            $this->assertAttachmentLimit($leave);

            $extension = $this->attachments->validateFile($file);
            $metadata = $this->attachments->writeToDisk($leave, $file, $extension);

            try {
                $leave->attachments()->create(array_merge($metadata, [
                    'uploaded_by' => $actor->id,
                ]));
            } catch (\Throwable $exception) {
                $this->attachments->discard($metadata['path']);

                throw $exception;
            }
        });

        return $this->show($leaveId, $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function removeAttachment(int $leaveId, int $attachmentId, User $actor): array
    {
        DB::transaction(function () use ($leaveId, $attachmentId, $actor) {
            $leave = $this->lockVisibleOrFail($leaveId, $actor);

            Gate::forUser($actor)->authorize('deleteAttachment', $leave);
            $this->assertAttachmentWritable($leave);

            $attachment = $leave->attachments()->whereKey($attachmentId)->first();

            if ($attachment === null) {
                $this->notFound('Attachment not found.');
            }

            $this->attachments->delete($attachment);
        });

        return $this->show($leaveId, $actor);
    }

    public function attachmentForDownload(int $leaveId, int $attachmentId, User $actor): LeaveAttachment
    {
        $leave = $this->findVisibleOrFail($leaveId, $actor);
        $attachment = $leave->attachments->firstWhere('id', $attachmentId);

        if ($attachment === null) {
            $this->notFound('Attachment not found.');
        }

        return $attachment;
    }

    public function downloadResponse(LeaveAttachment $attachment): StreamedResponse
    {
        return $this->attachments->downloadResponse($attachment);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function studentSubjects(array $filters): array
    {
        $year = $this->requireActiveSchoolYear();
        $search = trim((string) ($filters['search'] ?? ''));
        $limit = $this->pickerLimit($filters);

        $query = Student::query()
            ->select(['id', 'name', 'nis', 'nisn'])
            ->whereHas('studentRombels', fn (Builder $query) => $this->applyActiveMembership($query, $year->id))
            ->with([
                'studentRombels' => function ($query) use ($year) {
                    $this->applyActiveMembership($query, $year->id);
                    $query->with('schoolRombel:id,name,school_year_id');
                },
            ]);

        if ($search !== '') {
            $pattern = $this->likePattern($search);

            $query->where(function (Builder $sub) use ($pattern) {
                $sub->where('name', 'like', $pattern)
                    ->orWhere('nis', 'like', $pattern)
                    ->orWhere('nisn', 'like', $pattern);
            });
        }

        $items = $query->orderBy('name')->limit($limit)->get()
            ->map(function (Student $student) {
                $membership = $student->studentRombels->first();

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'nis' => $student->nis,
                    'nisn' => $student->nisn,
                    'class' => $membership?->schoolRombel?->name,
                    'school_year_id' => $membership?->schoolRombel?->school_year_id,
                    'student_rombel_id' => $membership?->id,
                ];
            })
            ->filter(fn (array $item) => $item['student_rombel_id'] !== null)
            ->values()
            ->all();

        return [
            'items' => $items,
            'active_school_year' => [
                'id' => $year->id,
                'name' => $year->name,
                'start_date' => $year->start_date?->format('Y-m-d'),
                'end_date' => $year->end_date?->format('Y-m-d'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function employeeSubjects(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $limit = $this->pickerLimit($filters);
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $query = Employee::query()
            ->select(['id', 'name', 'nuptk', 'employee_type_id'])
            ->with('employeeType:id,name')
            ->where(function (Builder $sub) use ($today) {
                $sub->whereNull('exit_date')->orWhere('exit_date', '>=', $today);
            });

        if ($search !== '') {
            $pattern = $this->likePattern($search);

            $query->where(function (Builder $sub) use ($pattern) {
                $sub->where('name', 'like', $pattern)
                    ->orWhere('nuptk', 'like', $pattern);
            });
        }

        return [
            'items' => $query->orderBy('name')->limit($limit)->get()
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'nuptk' => $employee->nuptk,
                    'employee_type' => $employee->employeeType?->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<LeaveRequest>
     */
    private function summaryQuery(): Builder
    {
        return LeaveRequest::query()
            ->withCount('attachments')
            ->with([
                'student:id,name,nis,nisn',
                'employee:id,name,nuptk,employee_type_id,user_id',
                'employee.employeeType:id,name',
                'schoolYear:id,name,start_date,end_date',
                'studentRombel:id,school_rombel',
                'studentRombel.schoolRombel:id,name',
                'requester:id,name',
            ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<LeaveRequest>
     */
    private function detailQuery(): Builder
    {
        return $this->summaryQuery()->with([
            'submitter:id,name',
            'decider:id,name',
            'canceller:id,name',
            'attachments' => fn ($query) => $query->orderBy('id'),
            'decisions' => fn ($query) => $query->with('actor:id,name'),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<LeaveRequest>  $query
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function paginate(Builder $query, array $filters, User $actor): array
    {
        $this->applyFilters($query, $filters);

        $perPage = (int) ($filters['per_page'] ?? config('leaves.default_per_page'));
        $sort = (string) ($filters['sort'] ?? 'start_date');
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['start_date', 'created_at', 'status', 'type', 'version'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'start_date';
        }

        $paginator = $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'items' => collect($paginator->items())
                ->map(fn (LeaveRequest $leave) => $this->serializeSummary($leave, $actor))
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
     * @param  \Illuminate\Database\Eloquent\Builder<LeaveRequest>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $status = $filters['status'] ?? 'all';
        $type = $filters['type'] ?? null;
        $subjectType = $filters['subject_type'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $schoolYearId = $filters['school_year_id'] ?? null;
        $rombelId = $filters['rombel_id'] ?? null;
        $employeeTypeId = $filters['employee_type_id'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        if ($subjectType !== null && $subjectType !== '') {
            $query->where('subject_type', $subjectType);
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $query->whereDate('end_date', '>=', $dateFrom);
        }

        if ($dateTo !== null && $dateTo !== '') {
            $query->whereDate('start_date', '<=', $dateTo);
        }

        if ($schoolYearId !== null && $schoolYearId !== '') {
            $query->where('school_year_id', (int) $schoolYearId);
        }

        if ($rombelId !== null && $rombelId !== '') {
            $query->where('student_rombel_id', (int) $rombelId);
        }

        if ($employeeTypeId !== null && $employeeTypeId !== '') {
            $query->whereIn(
                'employee_id',
                Employee::withTrashed()->where('employee_type_id', (int) $employeeTypeId)->select('id'),
            );
        }

        if ($search !== '') {
            $pattern = $this->likePattern($search);

            $query->where(function (Builder $sub) use ($pattern) {
                $sub->whereIn(
                    'student_id',
                    Student::withTrashed()
                        ->where(function (Builder $student) use ($pattern) {
                            $student->where('name', 'like', $pattern)
                                ->orWhere('nis', 'like', $pattern)
                                ->orWhere('nisn', 'like', $pattern);
                        })
                        ->select('id'),
                )->orWhereIn(
                    'employee_id',
                    Employee::withTrashed()
                        ->where(function (Builder $employee) use ($pattern) {
                            $employee->where('name', 'like', $pattern)
                                ->orWhere('nuptk', 'like', $pattern);
                        })
                        ->select('id'),
                );
            });
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{subject_type: string, student_id: ?int, employee_id: ?int, school_year_id: ?int, student_rombel_id: ?int, type: string, start_date: string, end_date: string, reason: string}
     */
    private function resolveSubjectData(array $data, User $actor, bool $forUpdate): array
    {
        $subjectType = (string) $data['subject_type'];
        $type = (string) $data['type'];
        $start = Carbon::createFromFormat('Y-m-d', (string) $data['start_date'], 'Asia/Jakarta')->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', (string) $data['end_date'], 'Asia/Jakarta')->startOfDay();
        $reason = trim((string) $data['reason']);

        if ($start->gt($end)) {
            throw ValidationException::withMessages([
                'end_date' => ['The end date may not be before the start date.'],
            ]);
        }

        $typeDefinition = $this->typeDefinition($subjectType, $type);
        $this->assertDateRules($start, $end, $typeDefinition);
        $this->assertTextLength('reason', $reason, config('leaves.reason'), 'reason');

        if ($subjectType === LeaveRequest::SUBJECT_STUDENT) {
            if (! $actor->hasAbility('leaves.manage')) {
                abort(403, 'You are not allowed to submit leave requests for students.');
            }

            $studentId = (int) ($data['student_id'] ?? 0);
            $student = $this->lockOrFind(Student::withTrashed(), $studentId, $forUpdate);

            if ($student === null || $student->trashed()) {
                throw ValidationException::withMessages([
                    'student_id' => ['The selected student is not available.'],
                ]);
            }

            $year = $this->requireActiveSchoolYear($forUpdate);
            $yearStart = $year->start_date?->format('Y-m-d');
            $yearEnd = $year->end_date?->format('Y-m-d');

            if ($yearStart === null || $yearEnd === null) {
                throw ValidationException::withMessages([
                    'school_year' => ['The active school year has no date range. Set it in Academic Period first.'],
                ]);
            }

            if ($start->format('Y-m-d') < $yearStart || $end->format('Y-m-d') > $yearEnd) {
                throw ValidationException::withMessages([
                    'start_date' => ['Student leave dates must fall within the active school year.'],
                ]);
            }

            $membership = $this->activeMembershipForStudent($studentId, $year->id, $forUpdate);

            if ($membership === null) {
                throw ValidationException::withMessages([
                    'student_id' => ['The selected student is not active in the active school year.'],
                ]);
            }

            return [
                'subject_type' => LeaveRequest::SUBJECT_STUDENT,
                'student_id' => $student->id,
                'employee_id' => null,
                'school_year_id' => $year->id,
                'student_rombel_id' => $membership->id,
                'type' => $type,
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'reason' => $reason,
            ];
        }

        if ($subjectType === LeaveRequest::SUBJECT_EMPLOYEE) {
            $employeeId = (int) ($data['employee_id'] ?? 0);
            $employee = $this->lockOrFind(Employee::withTrashed(), $employeeId, $forUpdate);

            if ($employee === null || $employee->trashed()) {
                throw ValidationException::withMessages([
                    'employee_id' => ['The selected employee is not available.'],
                ]);
            }

            $isSelf = (int) $employee->user_id === $actor->id && $employee->user_id !== null;

            if (! $actor->hasAbility('leaves.manage') && ! ($isSelf && $actor->hasAbility('leaves.request'))) {
                abort(403, 'You are not allowed to submit leave requests for this employee.');
            }

            if ($employee->exit_date !== null && Carbon::parse($employee->exit_date)->format('Y-m-d') < $start->format('Y-m-d')) {
                throw ValidationException::withMessages([
                    'employee_id' => ['The selected employee is not active on the leave start date.'],
                ]);
            }

            return [
                'subject_type' => LeaveRequest::SUBJECT_EMPLOYEE,
                'student_id' => null,
                'employee_id' => $employee->id,
                'school_year_id' => null,
                'student_rombel_id' => null,
                'type' => $type,
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'reason' => $reason,
            ];
        }

        throw ValidationException::withMessages([
            'subject_type' => ['The selected subject type is not valid.'],
        ]);
    }

    private function lockStoredSubject(LeaveRequest $leave): void
    {
        if ($leave->subject_type === LeaveRequest::SUBJECT_STUDENT) {
            $student = Student::withTrashed()->lockForUpdate()->find($leave->student_id);

            if ($student === null || $student->trashed()) {
                throw ValidationException::withMessages([
                    'student_id' => ['The student is no longer available.'],
                ]);
            }

            $membership = StudentRombel::withTrashed()->lockForUpdate()->find($leave->student_rombel_id);

            if ($membership === null
                || $membership->trashed()
                || (int) $membership->student_id !== (int) $leave->student_id
                || $membership->archived_at !== null
                || $membership->latest_student_exit_date !== null
                || ($membership->student_status_id !== null && (int) $membership->student_status_id !== 1)) {
                throw ValidationException::withMessages([
                    'student_id' => ['The student no longer has an active class membership.'],
                ]);
            }

            $membership->loadMissing('schoolRombel:id,school_year_id');

            if ($membership->schoolRombel === null
                || (int) $membership->schoolRombel->school_year_id !== (int) $leave->school_year_id) {
                throw ValidationException::withMessages([
                    'student_id' => ['The student class membership no longer matches the recorded school year.'],
                ]);
            }

            return;
        }

        if ($leave->subject_type === LeaveRequest::SUBJECT_EMPLOYEE) {
            $employee = Employee::withTrashed()->lockForUpdate()->find($leave->employee_id);

            if ($employee === null || $employee->trashed()) {
                throw ValidationException::withMessages([
                    'employee_id' => ['The employee is no longer available.'],
                ]);
            }

            if ($employee->exit_date !== null
                && Carbon::parse($employee->exit_date)->format('Y-m-d') < Carbon::parse($leave->start_date)->format('Y-m-d')) {
                throw ValidationException::withMessages([
                    'employee_id' => ['The selected employee is not active on the leave start date.'],
                ]);
            }
        }
    }

    private function assertStoredDates(LeaveRequest $leave): void
    {
        $typeDefinition = $this->typeDefinition($leave->subject_type, $leave->type);
        $start = Carbon::parse($leave->start_date)->startOfDay();
        $end = Carbon::parse($leave->end_date)->startOfDay();

        $this->assertDateRules($start, $end, $typeDefinition);
        $this->assertTextLength('reason', $leave->reason, config('leaves.reason'), 'reason');

        if ($leave->subject_type !== LeaveRequest::SUBJECT_STUDENT) {
            return;
        }

        $year = SchoolYear::withTrashed()->find($leave->school_year_id);

        if ($year === null || $year->trashed() || $year->start_date === null || $year->end_date === null) {
            throw ValidationException::withMessages([
                'school_year' => ['The school year for this student leave is no longer available.'],
            ]);
        }

        if ($start->format('Y-m-d') < $year->start_date->format('Y-m-d')
            || $end->format('Y-m-d') > $year->end_date->format('Y-m-d')) {
            throw ValidationException::withMessages([
                'start_date' => ['Student leave dates must fall within the recorded school year.'],
            ]);
        }
    }

    private function assertNoOverlap(LeaveRequest $leave): void
    {
        $subjectColumn = $leave->subject_type === LeaveRequest::SUBJECT_STUDENT ? 'student_id' : 'employee_id';
        $subjectId = $leave->subject_type === LeaveRequest::SUBJECT_STUDENT ? $leave->student_id : $leave->employee_id;

        $conflicts = LeaveRequest::query()
            ->whereKeyNot($leave->id)
            ->where($subjectColumn, $subjectId)
            ->whereIn('status', [LeaveRequest::STATUS_SUBMITTED, LeaveRequest::STATUS_APPROVED])
            ->whereDate('start_date', '<=', $leave->end_date)
            ->whereDate('end_date', '>=', $leave->start_date)
            ->orderBy('start_date')
            ->lockForUpdate()
            ->get(['id', 'start_date', 'end_date']);

        if ($conflicts->isEmpty()) {
            return;
        }

        $references = $conflicts
            ->map(fn (LeaveRequest $conflict) => '#'.$conflict->id
                .' ('.$conflict->start_date->format('Y-m-d').' to '.$conflict->end_date->format('Y-m-d').')')
            ->implode(', ');

        throw ValidationException::withMessages([
            'overlap' => ['The selected dates overlap with leave request '.$references.'.'],
        ]);
    }

    private function assertAttachmentRequirement(LeaveRequest $leave): void
    {
        $definition = $this->typeDefinition($leave->subject_type, $leave->type);

        if (($definition['attachment_required'] ?? false) && $leave->attachments()->count() === 0) {
            throw ValidationException::withMessages([
                'file' => ['This leave type requires at least one attachment before submission.'],
            ]);
        }
    }

    private function assertAttachmentWritable(LeaveRequest $leave): void
    {
        if (! in_array($leave->status, [LeaveRequest::STATUS_DRAFT, LeaveRequest::STATUS_SUBMITTED], true)) {
            $this->conflict(
                'status',
                'Attachments can only be changed while the leave request is draft or submitted.',
                $leave,
            );
        }
    }

    private function assertAttachmentLimit(LeaveRequest $leave): void
    {
        $limit = (int) config('leaves.attachment.max_files_per_request');

        if ($leave->attachments()->count() >= $limit) {
            throw ValidationException::withMessages([
                'file' => ["A leave request can have at most {$limit} attachments."],
            ]);
        }
    }

    private function assertDraft(LeaveRequest $leave, string $message): void
    {
        if (! $leave->isDraft()) {
            $this->conflict('status', $message, $leave);
        }
    }

    private function assertVersion(LeaveRequest $leave, int $expectedVersion): void
    {
        if ((int) $leave->version !== $expectedVersion) {
            $this->conflict(
                'expected_version',
                'This leave request was changed by another request. Reload the latest version and try again.',
                $leave,
            );
        }
    }

    private function lockVisibleOrFail(int $leaveId, User $actor): LeaveRequest
    {
        $leave = LeaveRequest::query()
            ->with(['employee:id,user_id'])
            ->lockForUpdate()
            ->find($leaveId);

        if ($leave === null) {
            $this->notFound('Leave request not found.');
        }

        if (! Gate::forUser($actor)->allows('view', $leave)) {
            $this->notFound('Leave request not found.');
        }

        return $leave;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>  $query
     * @return TModel|null
     */
    private function lockOrFind(Builder $query, int $id, bool $forUpdate)
    {
        if ($id < 1) {
            return null;
        }

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->find($id);
    }

    private function activeMembershipForStudent(int $studentId, int $schoolYearId, bool $forUpdate): ?StudentRombel
    {
        $query = StudentRombel::query()
            ->where('student_id', $studentId)
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->where(fn (Builder $sub) => $sub->whereNull('student_status_id')->orWhere('student_status_id', 1))
            ->whereNull('latest_student_exit_date')
            ->whereHas('schoolRombel', fn (Builder $sub) => $sub->where('school_year_id', $schoolYearId));

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<StudentRombel>|\Illuminate\Database\Eloquent\Relations\Relation<StudentRombel>  $query
     */
    private function applyActiveMembership(Builder|Relation $query, int $schoolYearId): void
    {
        $query->whereNull('student_rombels.deleted_at')
            ->whereNull('student_rombels.archived_at')
            ->where(fn (Builder $sub) => $sub
                ->whereNull('student_rombels.student_status_id')
                ->orWhere('student_rombels.student_status_id', 1))
            ->whereNull('student_rombels.latest_student_exit_date')
            ->whereHas('schoolRombel', fn (Builder $sub) => $sub->where('school_year_id', $schoolYearId));
    }

    private function activeSchoolYear(): ?SchoolYear
    {
        $settings = AcademicSetting::with('activeSchoolYear')->find(AcademicSetting::SINGLETON_ID);
        $year = $settings?->activeSchoolYear;

        if ($year === null || $year->trashed()) {
            return null;
        }

        return $year;
    }

    private function requireActiveSchoolYear(bool $forUpdate = false): SchoolYear
    {
        $year = $forUpdate
            ? SchoolYear::query()->lockForUpdate()->find(AcademicSetting::find(AcademicSetting::SINGLETON_ID)?->active_school_year_id)
            : $this->activeSchoolYear();

        if ($year === null || $year->trashed()) {
            throw ValidationException::withMessages([
                'school_year' => ['No active school year is configured. Set it in Academic Period first.'],
            ]);
        }

        if ($year->start_date === null || $year->end_date === null) {
            throw ValidationException::withMessages([
                'school_year' => ['The active school year has no date range. Set it in Academic Period first.'],
            ]);
        }

        return $year;
    }

    private function selfEmployee(User $actor): ?Employee
    {
        return Employee::query()
            ->with('employeeType:id,name')
            ->where('user_id', $actor->id)
            ->first();
    }

    /**
     * @return array{label: string, max_days: int, attachment_required: bool}
     */
    private function typeDefinition(string $subjectType, string $type): array
    {
        $definition = config('leaves.types.'.$subjectType.'.'.$type);

        if (! is_array($definition)) {
            throw ValidationException::withMessages([
                'type' => ['The selected leave type is not valid for this subject type.'],
            ]);
        }

        return $definition;
    }

    /**
     * @param  array{label: string, max_days: int, attachment_required: bool}  $definition
     */
    private function assertDateRules(Carbon $start, Carbon $end, array $definition): void
    {
        $days = (int) $start->diffInDays($end) + 1;
        $effectiveMax = min((int) $definition['max_days'], (int) config('leaves.max_range_days'));

        if ($days > $effectiveMax) {
            throw ValidationException::withMessages([
                'end_date' => ["The date range may not exceed {$effectiveMax} days for this leave type."],
            ]);
        }
    }

    /**
     * @param  array{min: int, max: int}  $limits
     */
    private function assertTextLength(string $field, string $value, array $limits, string $label): void
    {
        $length = mb_strlen($value);

        if ($length < (int) $limits['min'] || $length > (int) $limits['max']) {
            throw ValidationException::withMessages([
                $field => ["The {$label} must be between {$limits['min']} and {$limits['max']} characters."],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function pickerLimit(array $filters): int
    {
        $limit = (int) ($filters['limit'] ?? config('leaves.subject_picker_limit'));

        return max(1, min($limit, 100));
    }

    private function likePattern(string $search): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        return '%'.$escaped.'%';
    }

    private function conflict(string $field, string $message, LeaveRequest $leave): never
    {
        abort(response()->json([
            'message' => 'Conflict: '.$message,
            'errors' => [$field => [$message]],
            'latest' => $this->latestSummary($leave),
        ], 409));
    }

    private function notFound(string $message): never
    {
        abort(response()->json(['message' => $message], 404));
    }

    /**
     * @return array<string, mixed>
     */
    private function latestSummary(LeaveRequest $leave): array
    {
        return [
            'id' => $leave->id,
            'status' => $leave->status,
            'version' => (int) $leave->version,
            'type' => $leave->type,
            'start_date' => $leave->start_date?->format('Y-m-d'),
            'end_date' => $leave->end_date?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSummary(LeaveRequest $leave, User $actor): array
    {
        return [
            'id' => $leave->id,
            'subject_type' => $leave->subject_type,
            'subject' => $this->serializeSubject($leave),
            'type' => $leave->type,
            'type_label' => $leave->typeLabel(),
            'start_date' => $leave->start_date?->format('Y-m-d'),
            'end_date' => $leave->end_date?->format('Y-m-d'),
            'days' => $leave->days(),
            'status' => $leave->status,
            'version' => (int) $leave->version,
            'requested_by' => $leave->requester === null ? null : [
                'id' => $leave->requester->id,
                'name' => $leave->requester->name,
            ],
            'submitted_at' => $leave->submitted_at?->toIso8601String(),
            'decided_at' => $leave->decided_at?->toIso8601String(),
            'cancelled_at' => $leave->cancelled_at?->toIso8601String(),
            'attachments_count' => (int) ($leave->attachments_count ?? $leave->attachments->count()),
            'can' => $this->permissions($leave, $actor),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetail(LeaveRequest $leave, User $actor): array
    {
        return array_merge($this->serializeSummary($leave, $actor), [
            'reason' => $leave->reason,
            'submitted_by' => $leave->submitter === null ? null : [
                'id' => $leave->submitter->id,
                'name' => $leave->submitter->name,
            ],
            'decided_by' => $leave->decider === null ? null : [
                'id' => $leave->decider->id,
                'name' => $leave->decider->name,
            ],
            'cancelled_by' => $leave->canceller === null ? null : [
                'id' => $leave->canceller->id,
                'name' => $leave->canceller->name,
            ],
            'attachments' => $leave->attachments
                ->map(fn (LeaveAttachment $attachment) => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => (int) $attachment->size_bytes,
                    'uploaded_at' => $attachment->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'decisions' => $leave->decisions
                ->map(fn (LeaveDecision $decision) => [
                    'id' => $decision->id,
                    'action' => $decision->action,
                    'from_status' => $decision->from_status,
                    'to_status' => $decision->to_status,
                    'reason' => $decision->reason,
                    'actor' => $decision->actor === null ? null : [
                        'id' => $decision->actor->id,
                        'name' => $decision->actor->name,
                    ],
                    'created_at' => $decision->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'created_at' => $leave->created_at?->toIso8601String(),
            'updated_at' => $leave->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeSubject(LeaveRequest $leave): ?array
    {
        if ($leave->subject_type === LeaveRequest::SUBJECT_STUDENT) {
            if ($leave->student === null) {
                return null;
            }

            return [
                'id' => $leave->student->id,
                'name' => $leave->student->name,
                'nis' => $leave->student->nis,
                'nisn' => $leave->student->nisn,
                'class' => $leave->studentRombel?->schoolRombel?->name,
                'school_year' => $leave->schoolYear?->name,
            ];
        }

        if ($leave->employee === null) {
            return null;
        }

        return [
            'id' => $leave->employee->id,
            'name' => $leave->employee->name,
            'nuptk' => $leave->employee->nuptk,
            'employee_type' => $leave->employee->employeeType?->name,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(LeaveRequest $leave, User $actor): array
    {
        $requester = (int) $leave->requested_by === $actor->id;
        $manages = $actor->hasAbility('leaves.manage');
        $modifiable = $requester || $manages;
        $open = in_array($leave->status, [LeaveRequest::STATUS_DRAFT, LeaveRequest::STATUS_SUBMITTED], true);

        return [
            'view' => true,
            'edit' => $modifiable && $leave->isDraft(),
            'submit' => $modifiable && $leave->isDraft(),
            'cancel' => $modifiable && $open,
            'approve' => false,
            'reject' => false,
            'upload' => $modifiable && $open,
            'delete_attachment' => $modifiable && $open,
        ];
    }
}
