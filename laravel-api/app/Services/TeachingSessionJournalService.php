<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\TeachingAssignment;
use App\Models\TeachingJournal;
use App\Models\TeachingSchedule;
use App\Models\TeachingScheduleException;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeachingSessionJournalService
{
    public function __construct(
        private readonly TeachingScheduleService $scheduleService
    ) {}

    public function todayWib(): string
    {
        return Carbon::now(config('teaching.timezone', 'Asia/Jakarta'))->toDateString();
    }

    public function graceDays(): int
    {
        return max(0, (int) config('teaching.journal_grace_days', 7));
    }

    public function graceDeadlineDate(?string $todayWib = null): string
    {
        $today = Carbon::parse($todayWib ?? $this->todayWib(), config('teaching.timezone', 'Asia/Jakarta'))->startOfDay();

        return $today->copy()->subDays($this->graceDays())->toDateString();
    }

    public function resolveSelfEmployee(?User $actor): ?Employee
    {
        if (! $actor) {
            return null;
        }

        return Employee::query()
            ->eligibleTeacher()
            ->where('user_id', $actor->id)
            ->with('subject:id,name')
            ->first();
    }

    public function serializeSelfEmployee(?Employee $employee): ?array
    {
        if (! $employee) {
            return null;
        }

        return [
            'id' => (int) $employee->id,
            'name' => $employee->name,
            'nuptk' => $employee->nuptk,
            'primary_subject_id' => $employee->subject_id ? (int) $employee->subject_id : null,
            'primary_subject_name' => $employee->subject?->name,
        ];
    }

    /**
     * Evaluate deterministic teaching sessions for a date or date range (max 14 days) (DEC-40 D2).
     */
    public function listSessions(array $filters, User $actor): array
    {
        $schoolYearId = (int) ($filters['school_year_id'] ?? 0);
        $semesterId = (int) ($filters['semester_id'] ?? 0);

        $schoolYear = SchoolYear::find($schoolYearId);
        if (! $schoolYear) {
            throw ValidationException::withMessages([
                'school_year_id' => ['The selected academic year is not available.'],
            ]);
        }

        $semester = Semester::find($semesterId);
        if (! $semester) {
            throw ValidationException::withMessages([
                'semester_id' => ['The selected semester is not available.'],
            ]);
        }

        $todayWib = $this->todayWib();
        $graceDeadline = $this->graceDeadlineDate($todayWib);

        [$dateFrom, $dateTo] = $this->resolveSessionDateRange($filters, $todayWib);

        $selfEmployee = $this->resolveSelfEmployee($actor);
        $canManage = $actor->hasAbility('teaching.manage');
        $canCreate = $canManage || ($actor->hasAbility('teaching.view') && $selfEmployee !== null);
        $canUpdate = $canManage || ($actor->hasAbility('teaching.view') && $selfEmployee !== null);

        $scope = (string) ($filters['scope'] ?? ($canManage ? 'all' : 'mine'));
        if (! in_array($scope, ['mine', 'all'], true)) {
            $scope = 'all';
        }

        $statusFilter = (string) ($filters['status'] ?? 'all');
        if (! in_array($statusFilter, ['all', 'effective', 'recorded', 'missing', 'excluded'], true)) {
            $statusFilter = 'all';
        }

        $rombelId = isset($filters['school_rombel_id']) && $filters['school_rombel_id'] !== ''
            ? (int) $filters['school_rombel_id']
            : null;
        $subjectId = isset($filters['subject_id']) && $filters['subject_id'] !== ''
            ? (int) $filters['subject_id']
            : null;
        $employeeId = isset($filters['employee_id']) && $filters['employee_id'] !== ''
            ? (int) $filters['employee_id']
            : null;

        $schedules = TeachingSchedule::query()
            ->with([
                'assignment.schoolYear:id,name,start_date,end_date,locked_at',
                'assignment.semester:id,name,name_other',
                'assignment.schoolRombel:id,name,school_year_id,school_level_id,student_major_id',
                'assignment.schoolRombel.schoolLevel:id,name',
                'assignment.subject:id,name,description',
                'assignment.employee:id,name,nuptk,subject_id',
            ])
            ->where('status', TeachingSchedule::STATUS_ACTIVE)
            ->whereHas('assignment', function ($q) use ($schoolYearId, $semesterId, $rombelId, $subjectId) {
                $q->where('school_year_id', $schoolYearId)
                    ->where('semester_id', $semesterId)
                    ->where('status', TeachingAssignment::STATUS_ACTIVE)
                    ->when($rombelId !== null, fn ($rq) => $rq->where('school_rombel_id', $rombelId))
                    ->when($subjectId !== null, fn ($sq) => $sq->where('subject_id', $subjectId));
            })
            ->orderBy('day_of_week')
            ->orderBy('period_start')
            ->orderBy('start_time')
            ->get();

        $schedulesById = $schedules->keyBy(fn (TeachingSchedule $s) => (int) $s->id);
        $schedulesByDay = $schedules->groupBy(fn (TeachingSchedule $s) => (int) $s->day_of_week);

        $exceptions = TeachingScheduleException::query()
            ->with([
                'schoolRombel:id,name,school_year_id',
                'schedule.assignment.schoolRombel:id,name,school_year_id,school_level_id,student_major_id',
                'schedule.assignment.schoolRombel.schoolLevel:id,name',
                'schedule.assignment.subject:id,name,description',
                'schedule.assignment.employee:id,name,nuptk,subject_id',
                'substituteEmployee:id,name,nuptk,subject_id',
            ])
            ->where('school_year_id', $schoolYearId)
            ->where('semester_id', $semesterId)
            ->where(function ($q) use ($dateFrom, $dateTo) {
                $q->whereBetween('exception_date', [$dateFrom, $dateTo])
                    ->orWhereBetween('rescheduled_date', [$dateFrom, $dateTo]);
            })
            ->get();

        $journals = TeachingJournal::query()
            ->with([
                'teacher:id,name,nuptk',
                'creator:id,name,username',
                'updater:id,name,username',
                'corrector:id,name,username',
            ])
            ->where('school_year_id', $schoolYearId)
            ->where('semester_id', $semesterId)
            ->whereBetween('session_date', [$dateFrom, $dateTo])
            ->get();

        $journalsBySlotDate = [];
        foreach ($journals as $journal) {
            $key = $journal->teaching_schedule_id.'|'.$journal->session_date?->toDateString();
            $journalsBySlotDate[$key] = $journal;
        }

        $yearStart = $schoolYear->start_date?->toDateString();
        $yearEnd = $schoolYear->end_date?->toDateString();
        $isYearLocked = $schoolYear->locked_at !== null;
        $activeDays = config('teaching.days_of_week', []);

        $evaluatedSessions = [];

        $period = CarbonPeriod::create($dateFrom, $dateTo);
        foreach ($period as $dateObj) {
            $dateStr = $dateObj->toDateString();

            if (($yearStart !== null && $dateStr < $yearStart) || ($yearEnd !== null && $dateStr > $yearEnd)) {
                continue;
            }

            $isoDay = $dateObj->dayOfWeekIso;
            if (! array_key_exists($isoDay, $activeDays)) {
                continue;
            }

            $dayLabel = (string) $activeDays[$isoDay];

            $holidaysOnDate = $exceptions->filter(function (TeachingScheduleException $exc) use ($dateStr) {
                return $exc->exception_type === TeachingScheduleException::TYPE_HOLIDAY
                    && $exc->exception_date?->toDateString() === $dateStr;
            });

            $slotExceptionsOnDate = $exceptions->filter(function (TeachingScheduleException $exc) use ($dateStr) {
                return in_array($exc->exception_type, [
                    TeachingScheduleException::TYPE_CANCELLED,
                    TeachingScheduleException::TYPE_RESCHEDULED,
                ], true) && $exc->exception_date?->toDateString() === $dateStr;
            })->keyBy(fn (TeachingScheduleException $exc) => (int) $exc->teaching_schedule_id);

            $rescheduledInOnDate = $exceptions->filter(function (TeachingScheduleException $exc) use ($dateStr) {
                return $exc->exception_type === TeachingScheduleException::TYPE_RESCHEDULED
                    && $exc->rescheduled_date?->toDateString() === $dateStr;
            });

            $dayRegularSchedules = $schedulesByDay->get($isoDay, collect());

            // 1. Regular sessions on this day of week
            foreach ($dayRegularSchedules as $schedule) {
                $assignment = $schedule->assignment;
                if (! $assignment) {
                    continue;
                }

                $rombelIdForSlot = (int) $assignment->school_rombel_id;

                $matchingHoliday = $holidaysOnDate->first(function (TeachingScheduleException $exc) use ($rombelIdForSlot) {
                    return $exc->school_rombel_id === null || (int) $exc->school_rombel_id === $rombelIdForSlot;
                });

                $slotException = $slotExceptionsOnDate->get((int) $schedule->id);

                if ($matchingHoliday) {
                    $sessionStatus = 'holiday';
                    $isEffective = false;
                    $activeException = $matchingHoliday;
                } elseif ($slotException && $slotException->exception_type === TeachingScheduleException::TYPE_CANCELLED) {
                    $sessionStatus = 'cancelled';
                    $isEffective = false;
                    $activeException = $slotException;
                } elseif ($slotException && $slotException->exception_type === TeachingScheduleException::TYPE_RESCHEDULED) {
                    $sessionStatus = 'rescheduled_out';
                    $isEffective = false;
                    $activeException = $slotException;
                } else {
                    $sessionStatus = 'effective';
                    $isEffective = true;
                    $activeException = null;
                }

                $effectiveTeacherId = (int) $assignment->employee_id;
                $effectiveTeacherName = $assignment->employee?->name;
                $effectiveTeacherNuptk = $assignment->employee?->nuptk;

                $journalKey = $schedule->id.'|'.$dateStr;
                $attachedJournal = $journalsBySlotDate[$journalKey] ?? null;
                // If rescheduled to the same date, the journal belongs to the rescheduled_in session
                if (! $isEffective && $slotException && $slotException->rescheduled_date?->toDateString() === $dateStr) {
                    $attachedJournal = null;
                }

                if (! $this->matchesTeacherAndScopeFilter(
                    $effectiveTeacherId,
                    (int) $assignment->employee_id,
                    $attachedJournal ? (int) $attachedJournal->teacher_employee_id : null,
                    $employeeId,
                    $scope,
                    $selfEmployee
                )) {
                    continue;
                }

                $evaluatedSessions[] = $this->buildSessionItem(
                    $dateStr,
                    $isoDay,
                    $dayLabel,
                    TeachingJournal::SOURCE_REGULAR,
                    $sessionStatus,
                    $isEffective,
                    $schedule,
                    $assignment,
                    $activeException,
                    $attachedJournal,
                    (int) $schedule->period_start,
                    (int) $schedule->period_end,
                    (string) $schedule->start_time,
                    (string) $schedule->end_time,
                    $schedule->room,
                    $effectiveTeacherId,
                    $effectiveTeacherName,
                    $effectiveTeacherNuptk,
                    $todayWib,
                    $graceDeadline,
                    $isYearLocked,
                    $canManage,
                    $canCreate,
                    $canUpdate,
                    $selfEmployee
                );
            }

            // 2. Rescheduled-in sessions targeting this date
            foreach ($rescheduledInOnDate as $rescheduledExc) {
                $schedule = $schedulesById->get((int) $rescheduledExc->teaching_schedule_id);
                if (! $schedule || ! $schedule->assignment) {
                    continue;
                }

                $assignment = $schedule->assignment;
                $rombelIdForSlot = (int) $assignment->school_rombel_id;

                $matchingHoliday = $holidaysOnDate->first(function (TeachingScheduleException $exc) use ($rombelIdForSlot) {
                    return $exc->school_rombel_id === null || (int) $exc->school_rombel_id === $rombelIdForSlot;
                });

                if ($matchingHoliday) {
                    continue;
                }

                $effectiveTeacher = $rescheduledExc->substituteEmployee ?? $assignment->employee;
                $effectiveTeacherId = $rescheduledExc->substitute_employee_id !== null
                    ? (int) $rescheduledExc->substitute_employee_id
                    : (int) $assignment->employee_id;

                $journalKey = $schedule->id.'|'.$dateStr;
                $attachedJournal = $journalsBySlotDate[$journalKey] ?? null;

                if (! $this->matchesTeacherAndScopeFilter(
                    $effectiveTeacherId,
                    (int) $assignment->employee_id,
                    $attachedJournal ? (int) $attachedJournal->teacher_employee_id : null,
                    $employeeId,
                    $scope,
                    $selfEmployee
                )) {
                    continue;
                }

                $evaluatedSessions[] = $this->buildSessionItem(
                    $dateStr,
                    $isoDay,
                    $dayLabel,
                    TeachingJournal::SOURCE_RESCHEDULED,
                    'effective',
                    true,
                    $schedule,
                    $assignment,
                    $rescheduledExc,
                    $attachedJournal,
                    (int) $rescheduledExc->rescheduled_period_start,
                    (int) $rescheduledExc->rescheduled_period_end,
                    (string) $rescheduledExc->rescheduled_start_time,
                    (string) $rescheduledExc->rescheduled_end_time,
                    $rescheduledExc->rescheduled_room ?? $schedule->room,
                    $effectiveTeacherId,
                    $effectiveTeacher?->name,
                    $effectiveTeacher?->nuptk,
                    $todayWib,
                    $graceDeadline,
                    $isYearLocked,
                    $canManage,
                    $canCreate,
                    $canUpdate,
                    $selfEmployee
                );
            }
        }

        usort($evaluatedSessions, function (array $a, array $b) {
            return [$a['session_date'], $a['effective_period_start'], $a['effective_start_time'], $a['school_rombel_name'] ?? '']
                <=> [$b['session_date'], $b['effective_period_start'], $b['effective_start_time'], $b['school_rombel_name'] ?? ''];
        });

        $summary = [
            'total_slots' => count($evaluatedSessions),
            'effective_sessions' => 0,
            'recorded_journals' => 0,
            'missing_journals' => 0,
            'excluded_slots' => 0,
        ];

        foreach ($evaluatedSessions as $session) {
            if ($session['is_effective']) {
                $summary['effective_sessions']++;
                if ($session['has_journal']) {
                    $summary['recorded_journals']++;
                } else {
                    $summary['missing_journals']++;
                }
            } else {
                $summary['excluded_slots']++;
            }
        }

        $filteredSessions = array_values(array_filter($evaluatedSessions, function (array $session) use ($statusFilter) {
            return match ($statusFilter) {
                'effective' => $session['is_effective'] === true,
                'recorded' => $session['is_effective'] === true && $session['has_journal'] === true,
                'missing' => $session['is_effective'] === true && $session['has_journal'] === false,
                'excluded' => $session['is_effective'] === false,
                default => true,
            };
        }));

        return [
            'data' => $filteredSessions,
            'summary' => $summary,
            'meta' => [
                'school_year_id' => $schoolYearId,
                'school_year_name' => $schoolYear->name,
                'is_year_locked' => $isYearLocked,
                'semester_id' => $semesterId,
                'semester_name' => $semester->name,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'today_wib' => $todayWib,
                'grace_days' => $this->graceDays(),
                'grace_deadline_date' => $graceDeadline,
                'scope' => $scope,
                'status_filter' => $statusFilter,
                'self_employee' => $this->serializeSelfEmployee($selfEmployee),
            ],
        ];
    }

    /**
     * Paginated list of recorded teaching journals (DEC-40 D9).
     */
    public function listJournals(array $filters, User $actor): array
    {
        $selfEmployee = $this->resolveSelfEmployee($actor);
        $canManage = $actor->hasAbility('teaching.manage');

        $scope = (string) ($filters['scope'] ?? 'all');
        if (! in_array($scope, ['mine', 'all'], true)) {
            $scope = 'all';
        }

        $perPage = (int) ($filters['per_page'] ?? config('teaching.pagination.default', 20));
        if (! in_array($perPage, config('teaching.pagination.allowed', [10, 20, 50, 100]), true)) {
            $perPage = (int) config('teaching.pagination.default', 20);
        }

        $query = TeachingJournal::query()
            ->with($this->journalRelationFields())
            ->when(
                isset($filters['school_year_id']) && $filters['school_year_id'] !== '',
                fn ($q) => $q->where('school_year_id', (int) $filters['school_year_id'])
            )
            ->when(
                isset($filters['semester_id']) && $filters['semester_id'] !== '',
                fn ($q) => $q->where('semester_id', (int) $filters['semester_id'])
            )
            ->when(
                isset($filters['school_rombel_id']) && $filters['school_rombel_id'] !== '',
                fn ($q) => $q->where('school_rombel_id', (int) $filters['school_rombel_id'])
            )
            ->when(
                isset($filters['subject_id']) && $filters['subject_id'] !== '',
                fn ($q) => $q->where('subject_id', (int) $filters['subject_id'])
            )
            ->when(
                isset($filters['employee_id']) && $filters['employee_id'] !== '',
                fn ($q) => $q->where('teacher_employee_id', (int) $filters['employee_id'])
            )
            ->when(
                isset($filters['method']) && $filters['method'] !== '',
                fn ($q) => $q->where('method', (string) $filters['method'])
            )
            ->when(
                isset($filters['source_type']) && $filters['source_type'] !== '',
                fn ($q) => $q->where('source_type', (string) $filters['source_type'])
            )
            ->when(
                isset($filters['date_from']) && $filters['date_from'] !== '',
                fn ($q) => $q->whereDate('session_date', '>=', (string) $filters['date_from'])
            )
            ->when(
                isset($filters['date_to']) && $filters['date_to'] !== '',
                fn ($q) => $q->whereDate('session_date', '<=', (string) $filters['date_to'])
            );

        if ($scope === 'mine') {
            if (! $selfEmployee) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('teacher_employee_id', $selfEmployee->id);
            }
        }

        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('topic', 'like', $term)
                    ->orWhere('summary', 'like', $term)
                    ->orWhere('notes', 'like', $term)
                    ->orWhereHas('schoolRombel', fn ($rq) => $rq->where('name', 'like', $term))
                    ->orWhereHas('subject', fn ($sq) => $sq->where('name', 'like', $term))
                    ->orWhereHas('teacher', fn ($tq) => $tq->where('name', 'like', $term));
            });
        }

        $paginator = $query
            ->orderByDesc('session_date')
            ->orderBy('actual_start_time')
            ->orderByDesc('id')
            ->paginate($perPage);

        $todayWib = $this->todayWib();
        $graceDeadline = $this->graceDeadlineDate($todayWib);
        $canUpdate = $canManage || ($actor->hasAbility('teaching.view') && $selfEmployee !== null);

        $items = collect($paginator->items())
            ->map(fn (TeachingJournal $j) => $this->serializeJournal($j, $todayWib, $graceDeadline, $canManage, $canUpdate, $selfEmployee))
            ->values()
            ->all();

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'today_wib' => $todayWib,
                'grace_days' => $this->graceDays(),
                'grace_deadline_date' => $graceDeadline,
                'scope' => $scope,
                'self_employee' => $this->serializeSelfEmployee($selfEmployee),
            ],
        ];
    }

    public function showJournal(TeachingJournal $journal, User $actor): array
    {
        $loaded = $this->loadJournalRelations($journal);
        $todayWib = $this->todayWib();
        $graceDeadline = $this->graceDeadlineDate($todayWib);
        $selfEmployee = $this->resolveSelfEmployee($actor);
        $canManage = $actor->hasAbility('teaching.manage');
        $canUpdate = $canManage || ($actor->hasAbility('teaching.view') && $selfEmployee !== null);

        return $this->serializeJournal($loaded, $todayWib, $graceDeadline, $canManage, $canUpdate, $selfEmployee);
    }

    /**
     * Record a teaching journal for an effective session (DEC-40 D3, D4, D5, D7).
     *
     * @return array{journal: array, created: bool, idempotent_replay: bool}
     */
    public function recordJournal(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $scheduleSnapshot = TeachingSchedule::with('assignment')
                ->findOrFail((int) $data['teaching_schedule_id']);

            if (! $scheduleSnapshot->assignment) {
                throw ValidationException::withMessages([
                    'teaching_schedule_id' => ['The selected schedule slot does not have a valid teaching assignment.'],
                ]);
            }

            $schoolYear = $this->scheduleService->lockAndValidateWritableSchoolYear(
                (int) $scheduleSnapshot->assignment->school_year_id
            );

            $schedule = TeachingSchedule::with([
                'assignment.schoolRombel',
                'assignment.subject',
                'assignment.employee',
            ])
                ->whereKey($scheduleSnapshot->id)
                ->lockForUpdate()
                ->firstOrFail();

            $assignment = $schedule->assignment;

            if ($schedule->status !== TeachingSchedule::STATUS_ACTIVE || $assignment->status !== TeachingAssignment::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'teaching_schedule_id' => ['Cannot record a teaching journal for an inactive schedule slot or teaching assignment.'],
                ]);
            }

            $sessionDate = (string) $data['session_date'];
            $todayWib = $this->todayWib();
            $graceDeadline = $this->graceDeadlineDate($todayWib);

            $this->assertSessionDateWithinSchoolYear($schoolYear, $sessionDate);

            if ($sessionDate > $todayWib) {
                throw ValidationException::withMessages([
                    'session_date' => ["Teaching journals cannot be recorded for future dates ({$sessionDate}; today is {$todayWib})."],
                ]);
            }

            [$sourceType, $linkedException, $effectiveTeacherId, $periodStart, $periodEnd, $defaultStartTime, $defaultEndTime, $effectiveRoom] = $this->resolveEffectiveSessionContext(
                $schoolYear,
                (int) $assignment->semester_id,
                $schedule,
                $assignment,
                $sessionDate
            );

            $canManage = $actor->hasAbility('teaching.manage');
            $selfEmployee = $this->resolveSelfEmployee($actor);
            $isWithinGrace = $sessionDate >= $graceDeadline;

            if (! $canManage) {
                if (! $selfEmployee || (int) $selfEmployee->id !== $effectiveTeacherId) {
                    abort(403, 'Forbidden: You can only record teaching journals for your own assigned or substitute sessions.');
                }

                if (
                    isset($data['teacher_employee_id'])
                    && $data['teacher_employee_id'] !== ''
                    && (int) $data['teacher_employee_id'] !== (int) $selfEmployee->id
                ) {
                    abort(403, 'Forbidden: You cannot record a teaching journal on behalf of another teacher.');
                }

                if (! $isWithinGrace) {
                    throw ValidationException::withMessages([
                        'session_date' => [
                            "The {$this->graceDays()}-day grace window for recording this session ({$sessionDate}) has expired (deadline was {$graceDeadline}). Please contact an academic administrator.",
                        ],
                    ]);
                }

                $teacherEmployeeId = (int) $selfEmployee->id;
                $correctionReason = null;
                $correctedBy = null;
                $correctedAt = null;
            } else {
                $teacherEmployeeId = isset($data['teacher_employee_id']) && $data['teacher_employee_id'] !== ''
                    ? (int) $data['teacher_employee_id']
                    : $effectiveTeacherId;

                $this->validateEligibleTeacher($teacherEmployeeId, 'teacher_employee_id');

                $isTeacherOverride = $teacherEmployeeId !== $effectiveTeacherId;
                $isOtherTeacher = ! $selfEmployee || (int) $selfEmployee->id !== $teacherEmployeeId;
                $needsCorrection = ! $isWithinGrace || $isTeacherOverride;

                $rawReason = $this->normalizeOptionalText($data['correction_reason'] ?? null);
                if ($needsCorrection && ($rawReason === null || mb_strlen($rawReason) < 10)) {
                    throw ValidationException::withMessages([
                        'correction_reason' => [
                            'A correction reason (10–255 characters) is required when recording a journal past the 7-day grace window or overriding the assigned teacher.',
                        ],
                    ]);
                }

                $correctionReason = $rawReason;
                $correctedBy = ($needsCorrection || $isOtherTeacher || $rawReason !== null) ? $actor->id : null;
                $correctedAt = ($needsCorrection || $isOtherTeacher || $rawReason !== null) ? now() : null;
            }

            $topic = $this->normalizeRequiredText((string) $data['topic']);
            $method = (string) $data['method'];
            $summary = $this->normalizeOptionalText($data['summary'] ?? null);
            $notes = $this->normalizeOptionalText($data['notes'] ?? null);

            $actualStartTime = isset($data['actual_start_time']) && $data['actual_start_time'] !== ''
                ? (string) $data['actual_start_time']
                : $defaultStartTime;
            $actualEndTime = isset($data['actual_end_time']) && $data['actual_end_time'] !== ''
                ? (string) $data['actual_end_time']
                : $defaultEndTime;

            if ($actualEndTime <= $actualStartTime) {
                throw ValidationException::withMessages([
                    'actual_end_time' => ['The actual end time must be after the actual start time.'],
                ]);
            }

            $existing = TeachingJournal::query()
                ->where('teaching_schedule_id', $schedule->id)
                ->whereDate('session_date', $sessionDate)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $isIdentical = (int) $existing->teacher_employee_id === $teacherEmployeeId
                    && $existing->topic === $topic
                    && $existing->method === $method
                    && $existing->summary === $summary
                    && $existing->notes === $notes
                    && $existing->actual_start_time === $actualStartTime
                    && $existing->actual_end_time === $actualEndTime;

                $loadedExisting = $this->loadJournalRelations($existing);
                $serializedExisting = $this->serializeJournal(
                    $loadedExisting,
                    $todayWib,
                    $graceDeadline,
                    $canManage,
                    $canManage || ($actor->hasAbility('teaching.view') && $selfEmployee !== null),
                    $selfEmployee
                );

                if ($isIdentical) {
                    return [
                        'journal' => $serializedExisting,
                        'created' => false,
                        'idempotent_replay' => true,
                    ];
                }

                $this->abortConflict(
                    $serializedExisting,
                    'Conflict: A teaching journal for this schedule slot and date has already been recorded with different content.'
                );
            }

            $journal = TeachingJournal::create([
                'school_year_id' => $schoolYear->id,
                'semester_id' => $assignment->semester_id,
                'school_rombel_id' => $assignment->school_rombel_id,
                'subject_id' => $assignment->subject_id,
                'teaching_assignment_id' => $assignment->id,
                'teaching_schedule_id' => $schedule->id,
                'teaching_schedule_exception_id' => $linkedException?->id,
                'assigned_employee_id' => $assignment->employee_id,
                'teacher_employee_id' => $teacherEmployeeId,
                'session_date' => $sessionDate,
                'source_type' => $sourceType,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'scheduled_start_time' => $defaultStartTime,
                'scheduled_end_time' => $defaultEndTime,
                'actual_start_time' => $actualStartTime,
                'actual_end_time' => $actualEndTime,
                'room' => $effectiveRoom,
                'topic' => $topic,
                'method' => $method,
                'summary' => $summary,
                'notes' => $notes,
                'correction_reason' => $correctionReason,
                'corrected_by' => $correctedBy,
                'corrected_at' => $correctedAt,
                'version' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $loaded = $this->loadJournalRelations($journal);

            return [
                'journal' => $this->serializeJournal(
                    $loaded,
                    $todayWib,
                    $graceDeadline,
                    $canManage,
                    $canManage || ($actor->hasAbility('teaching.view') && $selfEmployee !== null),
                    $selfEmployee
                ),
                'created' => true,
                'idempotent_replay' => false,
            ];
        });
    }

    /**
     * Update an existing teaching journal with expected_version and grace-window / override checks (DEC-40 D3, D4, D5).
     */
    public function updateJournal(TeachingJournal $snapshot, array $data, User $actor): array
    {
        return DB::transaction(function () use ($snapshot, $data, $actor) {
            $this->scheduleService->lockAndValidateWritableSchoolYear((int) $snapshot->school_year_id);

            $journal = TeachingJournal::whereKey($snapshot->id)
                ->lockForUpdate()
                ->firstOrFail();

            $journal->load(['schedule.assignment', 'scheduleException']);

            $todayWib = $this->todayWib();
            $graceDeadline = $this->graceDeadlineDate($todayWib);
            $canManage = $actor->hasAbility('teaching.manage');
            $selfEmployee = $this->resolveSelfEmployee($actor);

            $expectedVersion = (int) $data['expected_version'];
            if ((int) $journal->version !== $expectedVersion) {
                $this->abortConflict(
                    $this->serializeJournal(
                        $this->loadJournalRelations($journal),
                        $todayWib,
                        $graceDeadline,
                        $canManage,
                        true,
                        $selfEmployee
                    ),
                    'Conflict: This teaching journal was modified by another user. Please reload the latest data.'
                );
            }

            $sessionDate = $journal->session_date?->toDateString() ?? $todayWib;
            $isWithinGrace = $sessionDate >= $graceDeadline;

            $effectiveTeacherId = (int) ($journal->scheduleException?->substitute_employee_id
                ?? $journal->schedule?->assignment?->employee_id
                ?? $journal->teacher_employee_id);

            if (! $canManage) {
                if (
                    ! $selfEmployee
                    || ((int) $selfEmployee->id !== (int) $journal->teacher_employee_id && (int) $selfEmployee->id !== $effectiveTeacherId)
                ) {
                    abort(403, 'Forbidden: You can only update your own teaching journals.');
                }

                if (
                    isset($data['teacher_employee_id'])
                    && $data['teacher_employee_id'] !== ''
                    && (int) $data['teacher_employee_id'] !== (int) $journal->teacher_employee_id
                ) {
                    abort(403, 'Forbidden: You cannot reassign a teaching journal to another teacher.');
                }

                if (! $isWithinGrace) {
                    throw ValidationException::withMessages([
                        'session_date' => [
                            "The {$this->graceDays()}-day grace window for editing this session ({$sessionDate}) has expired (deadline was {$graceDeadline}). Please contact an academic administrator.",
                        ],
                    ]);
                }

                $teacherEmployeeId = (int) $journal->teacher_employee_id;
                $correctionReason = $journal->correction_reason;
                $correctedBy = $journal->corrected_by;
                $correctedAt = $journal->corrected_at;
            } else {
                $teacherEmployeeId = isset($data['teacher_employee_id']) && $data['teacher_employee_id'] !== ''
                    ? (int) $data['teacher_employee_id']
                    : (int) $journal->teacher_employee_id;

                if ($teacherEmployeeId !== (int) $journal->teacher_employee_id) {
                    $this->validateEligibleTeacher($teacherEmployeeId, 'teacher_employee_id');
                }

                $isEditingOtherTeacher = ! $selfEmployee || (int) $selfEmployee->id !== (int) $journal->teacher_employee_id;
                $isTeacherOverride = $teacherEmployeeId !== (int) $journal->teacher_employee_id
                    || $teacherEmployeeId !== $effectiveTeacherId;
                $needsCorrection = ! $isWithinGrace || $isEditingOtherTeacher || $isTeacherOverride;

                $rawReason = array_key_exists('correction_reason', $data)
                    ? $this->normalizeOptionalText($data['correction_reason'])
                    : null;

                if ($needsCorrection && ($rawReason === null || mb_strlen($rawReason) < 10)) {
                    throw ValidationException::withMessages([
                        'correction_reason' => [
                            'A correction reason (10–255 characters) is required when updating a journal past the 7-day grace window or modifying another teacher\'s journal.',
                        ],
                    ]);
                }

                $correctionReason = $rawReason ?? $journal->correction_reason;
                $correctedBy = ($needsCorrection || $rawReason !== null) ? $actor->id : $journal->corrected_by;
                $correctedAt = ($needsCorrection || $rawReason !== null) ? now() : $journal->corrected_at;
            }

            $topic = array_key_exists('topic', $data)
                ? $this->normalizeRequiredText((string) $data['topic'])
                : $journal->topic;
            $method = $data['method'] ?? $journal->method;
            $summary = array_key_exists('summary', $data)
                ? $this->normalizeOptionalText($data['summary'])
                : $journal->summary;
            $notes = array_key_exists('notes', $data)
                ? $this->normalizeOptionalText($data['notes'])
                : $journal->notes;

            $actualStartTime = isset($data['actual_start_time']) && $data['actual_start_time'] !== ''
                ? (string) $data['actual_start_time']
                : (string) $journal->actual_start_time;
            $actualEndTime = isset($data['actual_end_time']) && $data['actual_end_time'] !== ''
                ? (string) $data['actual_end_time']
                : (string) $journal->actual_end_time;

            if ($actualEndTime <= $actualStartTime) {
                throw ValidationException::withMessages([
                    'actual_end_time' => ['The actual end time must be after the actual start time.'],
                ]);
            }

            $journal->fill([
                'teacher_employee_id' => $teacherEmployeeId,
                'actual_start_time' => $actualStartTime,
                'actual_end_time' => $actualEndTime,
                'topic' => $topic,
                'method' => $method,
                'summary' => $summary,
                'notes' => $notes,
                'correction_reason' => $correctionReason,
                'corrected_by' => $correctedBy,
                'corrected_at' => $correctedAt,
                'version' => (int) $journal->version + 1,
                'updated_by' => $actor->id,
            ]);
            $journal->save();

            return $this->serializeJournal(
                $this->loadJournalRelations($journal),
                $todayWib,
                $graceDeadline,
                $canManage,
                true,
                $selfEmployee
            );
        });
    }

    /**
     * Resolve and validate effective session context for recording a journal on (teaching_schedule_id, session_date).
     *
     * @return array{0: string, 1: ?TeachingScheduleException, 2: int, 3: string, 4: string}
     */
    private function resolveEffectiveSessionContext(
        SchoolYear $schoolYear,
        int $semesterId,
        TeachingSchedule $schedule,
        TeachingAssignment $assignment,
        string $sessionDate
    ): array {
        $rombelId = (int) $assignment->school_rombel_id;

        $holidayException = TeachingScheduleException::query()
            ->where('school_year_id', $schoolYear->id)
            ->where('semester_id', $semesterId)
            ->where('exception_type', TeachingScheduleException::TYPE_HOLIDAY)
            ->whereDate('exception_date', $sessionDate)
            ->where(function ($q) use ($rombelId) {
                $q->whereNull('school_rombel_id')
                    ->orWhere('school_rombel_id', $rombelId);
            })
            ->first();

        if ($holidayException) {
            throw ValidationException::withMessages([
                'session_date' => [
                    "Cannot record a teaching journal on {$sessionDate} because that date is marked as a holiday ({$holidayException->reason}).",
                ],
            ]);
        }

        // Check if a rescheduled exception targets this schedule and sessionDate as rescheduled_date
        $rescheduledIn = TeachingScheduleException::query()
            ->where('teaching_schedule_id', $schedule->id)
            ->where('exception_type', TeachingScheduleException::TYPE_RESCHEDULED)
            ->whereDate('rescheduled_date', $sessionDate)
            ->first();

        if ($rescheduledIn) {
            $effectiveTeacherId = $rescheduledIn->substitute_employee_id !== null
                ? (int) $rescheduledIn->substitute_employee_id
                : (int) $assignment->employee_id;

            return [
                TeachingJournal::SOURCE_RESCHEDULED,
                $rescheduledIn,
                $effectiveTeacherId,
                (int) $rescheduledIn->rescheduled_period_start,
                (int) $rescheduledIn->rescheduled_period_end,
                (string) $rescheduledIn->rescheduled_start_time,
                (string) $rescheduledIn->rescheduled_end_time,
                $rescheduledIn->rescheduled_room ?? $schedule->room,
            ];
        }

        // Check if this slot has a cancelled or rescheduled exception on sessionDate
        $slotExceptionOnDate = TeachingScheduleException::query()
            ->where('teaching_schedule_id', $schedule->id)
            ->whereIn('exception_type', [
                TeachingScheduleException::TYPE_CANCELLED,
                TeachingScheduleException::TYPE_RESCHEDULED,
            ])
            ->whereDate('exception_date', $sessionDate)
            ->first();

        if ($slotExceptionOnDate) {
            $label = $slotExceptionOnDate->exception_type === TeachingScheduleException::TYPE_CANCELLED
                ? 'cancelled'
                : 'rescheduled to '.$slotExceptionOnDate->rescheduled_date?->toDateString();

            throw ValidationException::withMessages([
                'session_date' => [
                    "Cannot record a teaching journal on {$sessionDate} because this schedule slot is {$label}.",
                ],
            ]);
        }

        $isoDay = Carbon::parse($sessionDate)->dayOfWeekIso;
        if ($isoDay !== (int) $schedule->day_of_week) {
            $dateDayName = config("teaching.days_of_week.{$isoDay}", 'Sunday');
            $slotDayName = config("teaching.days_of_week.{$schedule->day_of_week}", "Day {$schedule->day_of_week}");

            throw ValidationException::withMessages([
                'session_date' => [
                    "The session date ({$sessionDate}, {$dateDayName}) does not match the schedule slot's regular day ({$slotDayName}) or any rescheduled session.",
                ],
            ]);
        }

        return [
            TeachingJournal::SOURCE_REGULAR,
            null,
            (int) $assignment->employee_id,
            (int) $schedule->period_start,
            (int) $schedule->period_end,
            (string) $schedule->start_time,
            (string) $schedule->end_time,
            $schedule->room,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveSessionDateRange(array $filters, string $todayWib): array
    {
        $hasRange = (isset($filters['date_from']) && $filters['date_from'] !== '')
            || (isset($filters['date_to']) && $filters['date_to'] !== '');

        if ($hasRange) {
            $dateFrom = isset($filters['date_from']) && $filters['date_from'] !== ''
                ? (string) $filters['date_from']
                : (string) $filters['date_to'];
            $dateTo = isset($filters['date_to']) && $filters['date_to'] !== ''
                ? (string) $filters['date_to']
                : $dateFrom;
        } elseif (isset($filters['date']) && $filters['date'] !== '') {
            $dateFrom = (string) $filters['date'];
            $dateTo = $dateFrom;
        } else {
            $dateFrom = $todayWib;
            $dateTo = $todayWib;
        }

        try {
            $fromCarbon = Carbon::createFromFormat('Y-m-d', $dateFrom)->startOfDay();
            $toCarbon = Carbon::createFromFormat('Y-m-d', $dateTo)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'date' => ['The session date filter must use YYYY-MM-DD format.'],
            ]);
        }

        if ($toCarbon->lt($fromCarbon)) {
            throw ValidationException::withMessages([
                'date_to' => ['The end date (date_to) must be on or after the start date (date_from).'],
            ]);
        }

        if ($fromCarbon->diffInDays($toCarbon) > 13) {
            throw ValidationException::withMessages([
                'date_to' => ['The session date range cannot exceed 14 calendar days.'],
            ]);
        }

        return [$fromCarbon->toDateString(), $toCarbon->toDateString()];
    }

    private function matchesTeacherAndScopeFilter(
        int $effectiveTeacherId,
        int $assignedTeacherId,
        ?int $journalTeacherId,
        ?int $filterEmployeeId,
        string $scope,
        ?Employee $selfEmployee
    ): bool {
        if ($filterEmployeeId !== null) {
            $matchesFilter = $effectiveTeacherId === $filterEmployeeId
                || $assignedTeacherId === $filterEmployeeId
                || ($journalTeacherId !== null && $journalTeacherId === $filterEmployeeId);

            if (! $matchesFilter) {
                return false;
            }
        }

        if ($scope === 'mine') {
            if (! $selfEmployee) {
                return false;
            }

            $selfId = (int) $selfEmployee->id;

            return $effectiveTeacherId === $selfId
                || ($journalTeacherId !== null && $journalTeacherId === $selfId);
        }

        return true;
    }

    private function buildSessionItem(
        string $sessionDate,
        int $dayOfWeek,
        string $dayLabel,
        string $sourceType,
        string $sessionStatus,
        bool $isEffective,
        TeachingSchedule $schedule,
        TeachingAssignment $assignment,
        ?TeachingScheduleException $exception,
        ?TeachingJournal $journal,
        int $effectivePeriodStart,
        int $effectivePeriodEnd,
        string $effectiveStartTime,
        string $effectiveEndTime,
        ?string $effectiveRoom,
        int $effectiveTeacherId,
        ?string $effectiveTeacherName,
        ?string $effectiveTeacherNuptk,
        string $todayWib,
        string $graceDeadline,
        bool $isYearLocked,
        bool $canManage,
        bool $canCreate,
        bool $canUpdate,
        ?Employee $selfEmployee
    ): array {
        $isFutureDate = $sessionDate > $todayWib;
        $isWithinGrace = $sessionDate >= $graceDeadline && ! $isFutureDate;
        $selfId = $selfEmployee ? (int) $selfEmployee->id : null;
        $isOwnEffectiveSession = $selfId !== null && $selfId === $effectiveTeacherId;

        $canRecordSession = false;
        $canUpdateSession = false;
        $requiresCorrectionReason = false;
        $blockReason = null;

        if ($isYearLocked) {
            $blockReason = 'Academic year is locked';
        } elseif (! $isEffective) {
            $blockReason = match ($sessionStatus) {
                'holiday' => 'Holiday: '.($exception?->reason ?? 'No classes'),
                'cancelled' => 'Cancelled: '.($exception?->reason ?? 'Slot cancelled'),
                'rescheduled_out' => 'Rescheduled to '.($exception?->rescheduled_date?->toDateString() ?? 'another date'),
                default => 'Non-effective session',
            };
        } elseif ($isFutureDate) {
            $blockReason = 'Future session date (cannot record ahead of date)';
        } elseif ($journal === null) {
            if ($canManage) {
                $canRecordSession = true;
                $requiresCorrectionReason = ! $isWithinGrace;
            } elseif (! $canCreate) {
                $blockReason = 'Missing teaching.journals.create permission';
            } elseif (! $isOwnEffectiveSession) {
                $blockReason = 'Assigned to another teacher';
            } elseif (! $isWithinGrace) {
                $blockReason = "Past {$this->graceDays()}-day grace window (requires admin/operator override)";
            } else {
                $canRecordSession = true;
            }
        } else {
            $journalTeacherId = (int) $journal->teacher_employee_id;
            $isOwnJournal = $selfId !== null && $selfId === $journalTeacherId && $isOwnEffectiveSession;

            if ($canManage) {
                $canUpdateSession = true;
                $requiresCorrectionReason = ! $isWithinGrace || ($selfId === null || $selfId !== $journalTeacherId);
            } elseif (! $canUpdate) {
                $blockReason = 'Missing teaching.journals.update permission';
            } elseif (! $isOwnJournal) {
                $blockReason = 'Recorded by / assigned to another teacher';
            } elseif (! $isWithinGrace) {
                $blockReason = "Past {$this->graceDays()}-day grace window (requires admin/operator override)";
            } else {
                $canUpdateSession = true;
            }
        }

        return [
            'session_key' => $schedule->id.'_'.$sessionDate.'_'.$sourceType,
            'session_date' => $sessionDate,
            'day_of_week' => $dayOfWeek,
            'day_label' => $dayLabel,
            'source_type' => $sourceType,
            'source_type_label' => config("teaching.journal_source_types.{$sourceType}", ucfirst($sourceType)),
            'session_status' => $sessionStatus,
            'is_effective' => $isEffective,
            'has_journal' => $journal !== null,
            'teaching_schedule_id' => (int) $schedule->id,
            'teaching_assignment_id' => (int) $assignment->id,
            'teaching_schedule_exception_id' => $exception?->id ? (int) $exception->id : null,
            'school_year_id' => (int) $assignment->school_year_id,
            'school_year_name' => $assignment->schoolYear?->name,
            'is_year_locked' => $isYearLocked,
            'semester_id' => (int) $assignment->semester_id,
            'semester_name' => $assignment->semester?->name,
            'school_rombel_id' => (int) $assignment->school_rombel_id,
            'school_rombel_name' => $assignment->schoolRombel?->name,
            'subject_id' => (int) $assignment->subject_id,
            'subject_name' => $assignment->subject?->name,
            'assigned_employee_id' => (int) $assignment->employee_id,
            'assigned_employee_name' => $assignment->employee?->name,
            'effective_employee_id' => $effectiveTeacherId,
            'effective_employee_name' => $effectiveTeacherName,
            'effective_employee_nuptk' => $effectiveTeacherNuptk,
            'is_substitute' => $effectiveTeacherId !== (int) $assignment->employee_id,
            'effective_period_start' => $effectivePeriodStart,
            'effective_period_end' => $effectivePeriodEnd,
            'effective_start_time' => $effectiveStartTime,
            'effective_end_time' => $effectiveEndTime,
            'effective_room' => $effectiveRoom,
            'is_future_date' => $isFutureDate,
            'is_within_grace_window' => $isWithinGrace,
            'grace_deadline_date' => $graceDeadline,
            'can_record' => $canRecordSession,
            'can_update' => $canUpdateSession,
            'requires_correction_reason' => $requiresCorrectionReason,
            'block_reason' => $blockReason,
            'exception' => $exception ? [
                'id' => (int) $exception->id,
                'exception_type' => $exception->exception_type,
                'exception_type_label' => config("teaching.exception_types.{$exception->exception_type}", ucfirst($exception->exception_type)),
                'exception_date' => $exception->exception_date?->toDateString(),
                'rescheduled_date' => $exception->rescheduled_date?->toDateString(),
                'rescheduled_period_start' => $exception->rescheduled_period_start !== null ? (int) $exception->rescheduled_period_start : null,
                'rescheduled_period_end' => $exception->rescheduled_period_end !== null ? (int) $exception->rescheduled_period_end : null,
                'rescheduled_start_time' => $exception->rescheduled_start_time,
                'rescheduled_end_time' => $exception->rescheduled_end_time,
                'rescheduled_room' => $exception->rescheduled_room,
                'substitute_employee_id' => $exception->substitute_employee_id !== null ? (int) $exception->substitute_employee_id : null,
                'substitute_employee_name' => $exception->substituteEmployee?->name,
                'reason' => $exception->reason,
            ] : null,
            'journal' => $journal ? [
                'id' => (int) $journal->id,
                'teacher_employee_id' => (int) $journal->teacher_employee_id,
                'teacher_employee_name' => $journal->teacher?->name ?? $effectiveTeacherName,
                'actual_start_time' => $journal->actual_start_time,
                'actual_end_time' => $journal->actual_end_time,
                'topic' => $journal->topic,
                'method' => $journal->method,
                'method_label' => config("teaching.journal_methods.{$journal->method}", ucfirst($journal->method)),
                'summary' => $journal->summary,
                'notes' => $journal->notes,
                'correction_reason' => $journal->correction_reason,
                'corrected_by_name' => $journal->corrector?->name,
                'corrected_at' => $journal->corrected_at?->toIso8601String(),
                'version' => (int) $journal->version,
                'updated_at' => $journal->updated_at?->toIso8601String(),
            ] : null,
        ];
    }

    public function loadJournalRelations(TeachingJournal $journal): TeachingJournal
    {
        return $journal->load($this->journalRelationFields());
    }

    private function journalRelationFields(): array
    {
        return [
            'schoolYear:id,name,start_date,end_date,locked_at',
            'semester:id,name,name_other',
            'schoolRombel:id,name,school_year_id,school_level_id,student_major_id',
            'schoolRombel.schoolLevel:id,name',
            'subject:id,name,description',
            'assignment:id,employee_id,hours_per_week,status',
            'assignment.employee:id,name,nuptk',
            'schedule:id,teaching_assignment_id,day_of_week,period_start,period_end,start_time,end_time,room,status',
            'scheduleException:id,exception_type,exception_date,rescheduled_date,rescheduled_period_start,rescheduled_period_end,rescheduled_start_time,rescheduled_end_time,rescheduled_room,substitute_employee_id,reason',
            'scheduleException.substituteEmployee:id,name,nuptk',
            'teacher:id,name,nuptk,subject_id',
            'creator:id,name,username',
            'updater:id,name,username',
            'corrector:id,name,username',
        ];
    }

    public function serializeJournal(
        TeachingJournal $journal,
        ?string $todayWib = null,
        ?string $graceDeadline = null,
        bool $canManage = false,
        bool $canUpdateAbility = false,
        ?Employee $selfEmployee = null
    ): array {
        $today = $todayWib ?? $this->todayWib();
        $deadline = $graceDeadline ?? $this->graceDeadlineDate($today);
        $sessionDate = $journal->session_date?->toDateString();
        $isYearLocked = $journal->schoolYear?->locked_at !== null;
        $isWithinGrace = $sessionDate !== null && $sessionDate >= $deadline && $sessionDate <= $today;

        $selfId = $selfEmployee ? (int) $selfEmployee->id : null;
        $effectiveTeacherId = (int) ($journal->scheduleException?->substitute_employee_id
            ?? $journal->assignment?->employee_id
            ?? $journal->teacher_employee_id);
        $isOwnJournal = $selfId !== null
            && $selfId === (int) $journal->teacher_employee_id
            && $selfId === $effectiveTeacherId;

        $canUpdate = ! $isYearLocked && (
            $canManage || ($canUpdateAbility && $isOwnJournal && $isWithinGrace)
        );
        $requiresCorrectionReason = $canManage && (
            ! $isWithinGrace || $selfId === null || $selfId !== (int) $journal->teacher_employee_id
        );

        $isoDay = $sessionDate ? Carbon::parse($sessionDate)->dayOfWeekIso : (int) ($journal->schedule?->day_of_week ?? 1);

        return [
            'id' => (int) $journal->id,
            'school_year_id' => (int) $journal->school_year_id,
            'school_year_name' => $journal->schoolYear?->name,
            'is_year_locked' => $isYearLocked,
            'semester_id' => (int) $journal->semester_id,
            'semester_name' => $journal->semester?->name,
            'school_rombel_id' => (int) $journal->school_rombel_id,
            'school_rombel_name' => $journal->schoolRombel?->name,
            'subject_id' => (int) $journal->subject_id,
            'subject_name' => $journal->subject?->name,
            'teaching_assignment_id' => (int) $journal->teaching_assignment_id,
            'assigned_employee_id' => $journal->assignment ? (int) $journal->assignment->employee_id : null,
            'assigned_employee_name' => $journal->assignment?->employee?->name,
            'teaching_schedule_id' => (int) $journal->teaching_schedule_id,
            'schedule' => $journal->schedule ? [
                'id' => (int) $journal->schedule->id,
                'day_of_week' => (int) $journal->schedule->day_of_week,
                'day_label' => config("teaching.days_of_week.{$journal->schedule->day_of_week}", "Day {$journal->schedule->day_of_week}"),
                'period_start' => (int) $journal->schedule->period_start,
                'period_end' => (int) $journal->schedule->period_end,
                'start_time' => $journal->schedule->start_time,
                'end_time' => $journal->schedule->end_time,
                'room' => $journal->schedule->room,
            ] : null,
            'teaching_schedule_exception_id' => $journal->teaching_schedule_exception_id !== null
                ? (int) $journal->teaching_schedule_exception_id
                : null,
            'exception' => $journal->scheduleException ? [
                'id' => (int) $journal->scheduleException->id,
                'exception_type' => $journal->scheduleException->exception_type,
                'exception_date' => $journal->scheduleException->exception_date?->toDateString(),
                'rescheduled_date' => $journal->scheduleException->rescheduled_date?->toDateString(),
                'rescheduled_period_start' => $journal->scheduleException->rescheduled_period_start !== null
                    ? (int) $journal->scheduleException->rescheduled_period_start
                    : null,
                'rescheduled_period_end' => $journal->scheduleException->rescheduled_period_end !== null
                    ? (int) $journal->scheduleException->rescheduled_period_end
                    : null,
                'rescheduled_start_time' => $journal->scheduleException->rescheduled_start_time,
                'rescheduled_end_time' => $journal->scheduleException->rescheduled_end_time,
                'rescheduled_room' => $journal->scheduleException->rescheduled_room,
                'substitute_employee_id' => $journal->scheduleException->substitute_employee_id !== null
                    ? (int) $journal->scheduleException->substitute_employee_id
                    : null,
                'substitute_employee_name' => $journal->scheduleException->substituteEmployee?->name,
                'reason' => $journal->scheduleException->reason,
            ] : null,
            'teacher_employee_id' => (int) $journal->teacher_employee_id,
            'teacher_employee_name' => $journal->teacher?->name,
            'teacher_employee_nuptk' => $journal->teacher?->nuptk,
            'session_date' => $sessionDate,
            'day_of_week' => $isoDay,
            'day_label' => config("teaching.days_of_week.{$isoDay}", "Day {$isoDay}"),
            'source_type' => $journal->source_type,
            'source_type_label' => config("teaching.journal_source_types.{$journal->source_type}", ucfirst($journal->source_type)),
            'actual_start_time' => $journal->actual_start_time,
            'actual_end_time' => $journal->actual_end_time,
            'topic' => $journal->topic,
            'method' => $journal->method,
            'method_label' => config("teaching.journal_methods.{$journal->method}", ucfirst($journal->method)),
            'summary' => $journal->summary,
            'notes' => $journal->notes,
            'correction_reason' => $journal->correction_reason,
            'corrected_by' => $journal->corrected_by !== null ? (int) $journal->corrected_by : null,
            'corrected_by_name' => $journal->corrector?->name,
            'corrected_at' => $journal->corrected_at?->toIso8601String(),
            'is_within_grace_window' => $isWithinGrace,
            'can_update' => $canUpdate,
            'requires_correction_reason' => $requiresCorrectionReason,
            'version' => (int) $journal->version,
            'created_by_name' => $journal->creator?->name,
            'updated_by_name' => $journal->updater?->name,
            'created_at' => $journal->created_at?->toIso8601String(),
            'updated_at' => $journal->updated_at?->toIso8601String(),
        ];
    }

    private function validateEligibleTeacher(int $employeeId, string $field = 'teacher_employee_id'): Employee
    {
        $teacher = Employee::query()
            ->eligibleTeacher()
            ->whereKey($employeeId)
            ->first();

        if (! $teacher) {
            throw ValidationException::withMessages([
                $field => ['The selected employee must be an active teaching staff member (Pendidik).'],
            ]);
        }

        return $teacher;
    }

    private function assertSessionDateWithinSchoolYear(SchoolYear $schoolYear, string $date): void
    {
        $start = $schoolYear->start_date?->toDateString();
        $end = $schoolYear->end_date?->toDateString();

        if ($start !== null && $date < $start) {
            throw ValidationException::withMessages([
                'session_date' => ["The session date ({$date}) cannot be earlier than the school year start date ({$start})."],
            ]);
        }

        if ($end !== null && $date > $end) {
            throw ValidationException::withMessages([
                'session_date' => ["The session date ({$date}) cannot be later than the school year end date ({$end})."],
            ]);
        }
    }

    private function normalizeRequiredText(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) preg_replace('/\s+/', ' ', $value));

        return $trimmed === '' ? null : $trimmed;
    }

    private function abortConflict(array $latest, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
            'conflict' => true,
            'latest' => $latest,
        ], 409));
    }
}
