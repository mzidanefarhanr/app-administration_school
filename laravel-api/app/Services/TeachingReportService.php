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
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeachingReportService
{
    public function __construct(
        private readonly TeachingSessionJournalService $journalService
    ) {}

    /**
     * Compute teaching journal compliance report and missing journal drilldown (DEC-41 D1–D7).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function missingJournalsReport(array $filters, User $actor): array
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

        $todayWib = $this->journalService->todayWib();
        $graceDays = $this->journalService->graceDays();
        $graceDeadline = $this->journalService->graceDeadlineDate($todayWib);

        [$dateFrom, $dateTo] = $this->resolveReportDateRange($filters, $todayWib, $schoolYear);

        $selfEmployee = $this->journalService->resolveSelfEmployee($actor);
        $canManage = $actor->hasAbility('teaching.manage');
        $isLeadership = $actor->hasRole('kepala-sekolah') || $actor->hasRole('wakil-kesiswaan');
        $canViewAll = $canManage || $isLeadership;

        $requestedEmployeeId = isset($filters['employee_id']) && $filters['employee_id'] !== ''
            ? (int) $filters['employee_id']
            : null;

        $scope = (string) ($filters['scope'] ?? ($canViewAll ? 'all' : 'mine'));
        if (! in_array($scope, ['all', 'mine'], true)) {
            $scope = 'all';
        }

        $filterEmployeeId = $requestedEmployeeId;

        // RBAC Enforcement (DEC-41 D6):
        // Teachers without manage/leadership are strictly restricted to their own data.
        if (! $canViewAll) {
            if ($requestedEmployeeId !== null && ($selfEmployee === null || $requestedEmployeeId !== (int) $selfEmployee->id)) {
                abort(403, 'Forbidden: You can only view your own teaching compliance reports.');
            }

            $scope = 'mine';
            $filterEmployeeId = $selfEmployee ? (int) $selfEmployee->id : -1;
        } elseif ($scope === 'mine' && $selfEmployee !== null && $filterEmployeeId === null) {
            $filterEmployeeId = (int) $selfEmployee->id;
        }

        $statusFilter = (string) ($filters['status'] ?? 'all');
        if (! in_array($statusFilter, ['all', 'missing', 'missing_grace', 'missing_overdue', 'recorded'], true)) {
            $statusFilter = 'all';
        }

        $rombelId = isset($filters['school_rombel_id']) && $filters['school_rombel_id'] !== ''
            ? (int) $filters['school_rombel_id']
            : null;
        $subjectId = isset($filters['subject_id']) && $filters['subject_id'] !== ''
            ? (int) $filters['subject_id']
            : null;

        $evaluated = $this->evaluateSessions(
            $schoolYear,
            $semester,
            $dateFrom,
            $dateTo,
            $todayWib,
            $graceDeadline,
            $rombelId,
            $subjectId,
            $filterEmployeeId,
            $selfEmployee,
            $canManage
        );

        $effectiveSessions = $evaluated['effective_sessions'];
        $excludedSlotsCount = $evaluated['excluded_slots_count'];

        // Aggregate by teacher (DEC-41 D3: teachers with 0 expected sessions are excluded)
        $byTeacher = $this->aggregateByTeacher($effectiveSessions);

        // Compute summary metrics (DEC-41 D3 & D4)
        $summary = $this->computeSummary($byTeacher, $excludedSlotsCount);

        // Filter drilldown sessions by status filter
        $filteredDrilldown = array_values(array_filter($effectiveSessions, function (array $session) use ($statusFilter) {
            return match ($statusFilter) {
                'missing' => $session['is_missing'] === true,
                'missing_grace' => $session['is_missing'] === true && $session['is_within_grace_window'] === true,
                'missing_overdue' => $session['is_missing'] === true && $session['is_overdue'] === true,
                'recorded' => $session['has_journal'] === true,
                default => true,
            };
        }));

        // Sort drilldown by date DESC, period ASC, rombel ASC
        usort($filteredDrilldown, function (array $a, array $b) {
            return [$b['session_date'], $a['period_start'], $a['school_rombel_name'] ?? '']
                <=> [$a['session_date'], $b['period_start'], $b['school_rombel_name'] ?? ''];
        });

        // Pagination for drilldown
        $perPage = (int) ($filters['per_page'] ?? 20);
        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 20;
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $totalDrilldown = count($filteredDrilldown);
        $lastPage = max(1, (int) ceil($totalDrilldown / $perPage));
        $offset = ($page - 1) * $perPage;
        $paginatedDrilldown = array_slice($filteredDrilldown, $offset, $perPage);

        return [
            'data' => [
                'summary' => $summary,
                'by_teacher' => $byTeacher,
                'drilldown' => [
                    'data' => $paginatedDrilldown,
                    'meta' => [
                        'current_page' => $page,
                        'last_page' => $lastPage,
                        'per_page' => $perPage,
                        'total' => $totalDrilldown,
                    ],
                ],
            ],
            'meta' => [
                'school_year_id' => $schoolYearId,
                'school_year_name' => $schoolYear->name,
                'is_year_locked' => $schoolYear->locked_at !== null,
                'semester_id' => $semesterId,
                'semester_name' => $semester->name,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'today_wib' => $todayWib,
                'grace_days' => $graceDays,
                'grace_deadline_date' => $graceDeadline,
                'scope' => $scope,
                'status_filter' => $statusFilter,
                'can_view_all' => $canViewAll,
                'self_employee' => $this->journalService->serializeSelfEmployee($selfEmployee),
            ],
        ];
    }

    /**
     * Stream CSV export for missing teaching journals report (DEC-41 D7).
     *
     * @param  array<string, mixed>  $filters
     */
    public function exportMissingJournalsCsv(array $filters, User $actor): StreamedResponse
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

        $todayWib = $this->journalService->todayWib();
        $graceDeadline = $this->journalService->graceDeadlineDate($todayWib);

        [$dateFrom, $dateTo] = $this->resolveReportDateRange($filters, $todayWib, $schoolYear);

        $selfEmployee = $this->journalService->resolveSelfEmployee($actor);
        $canManage = $actor->hasAbility('teaching.manage');
        $isLeadership = $actor->hasRole('kepala-sekolah') || $actor->hasRole('wakil-kesiswaan');
        $canViewAll = $canManage || $isLeadership;

        $requestedEmployeeId = isset($filters['employee_id']) && $filters['employee_id'] !== ''
            ? (int) $filters['employee_id']
            : null;

        $scope = (string) ($filters['scope'] ?? ($canViewAll ? 'all' : 'mine'));
        if (! in_array($scope, ['all', 'mine'], true)) {
            $scope = 'all';
        }

        $filterEmployeeId = $requestedEmployeeId;

        if (! $canViewAll) {
            if ($requestedEmployeeId !== null && ($selfEmployee === null || $requestedEmployeeId !== (int) $selfEmployee->id)) {
                abort(403, 'Forbidden: You can only export your own teaching compliance reports.');
            }

            $scope = 'mine';
            $filterEmployeeId = $selfEmployee ? (int) $selfEmployee->id : -1;
        } elseif ($scope === 'mine' && $selfEmployee !== null && $filterEmployeeId === null) {
            $filterEmployeeId = (int) $selfEmployee->id;
        }

        $statusFilter = (string) ($filters['status'] ?? 'all');
        if (! in_array($statusFilter, ['all', 'missing', 'missing_grace', 'missing_overdue', 'recorded'], true)) {
            $statusFilter = 'all';
        }

        $rombelId = isset($filters['school_rombel_id']) && $filters['school_rombel_id'] !== ''
            ? (int) $filters['school_rombel_id']
            : null;
        $subjectId = isset($filters['subject_id']) && $filters['subject_id'] !== ''
            ? (int) $filters['subject_id']
            : null;

        $evaluated = $this->evaluateSessions(
            $schoolYear,
            $semester,
            $dateFrom,
            $dateTo,
            $todayWib,
            $graceDeadline,
            $rombelId,
            $subjectId,
            $filterEmployeeId,
            $selfEmployee,
            $canManage
        );

        $filteredDrilldown = array_values(array_filter($evaluated['effective_sessions'], function (array $session) use ($statusFilter) {
            return match ($statusFilter) {
                'missing' => $session['is_missing'] === true,
                'missing_grace' => $session['is_missing'] === true && $session['is_within_grace_window'] === true,
                'missing_overdue' => $session['is_missing'] === true && $session['is_overdue'] === true,
                'recorded' => $session['has_journal'] === true,
                default => true,
            };
        }));

        usort($filteredDrilldown, function (array $a, array $b) {
            return [$b['session_date'], $a['period_start'], $a['school_rombel_name'] ?? '']
                <=> [$a['session_date'], $b['period_start'], $b['school_rombel_name'] ?? ''];
        });

        $timestamp = now(config('teaching.timezone', 'Asia/Jakarta'))->format('Ymd_His');
        $filename = "teaching-missing-journals-{$timestamp}.csv";

        return response()->streamDownload(function () use ($filteredDrilldown) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // CSV Header Row (DEC-41 D7)
            fputcsv($handle, [
                'Session Date',
                'Day',
                'Period',
                'Time',
                'Class',
                'Subject',
                'Assigned Teacher',
                'Effective Teacher',
                'Teacher NUPTK',
                'Source',
                'Session Status',
                'Journal Status',
                'Deadline Status',
                'Journal Topic',
                'Journal Method',
            ]);

            foreach ($filteredDrilldown as $session) {
                $deadlineStatus = $session['has_journal']
                    ? 'Recorded'
                    : ($session['is_within_grace_window'] ? 'Within Grace Period' : 'Overdue');

                fputcsv($handle, [
                    $session['session_date'],
                    $session['day_label'],
                    $session['period_label'],
                    $session['time_label'],
                    $session['school_rombel_name'],
                    $session['subject_name'],
                    $session['assigned_teacher_name'],
                    $session['teacher_name'],
                    $session['teacher_nuptk'] ? "'".$session['teacher_nuptk'] : '-',
                    ucfirst($session['source_type']),
                    'Effective',
                    $session['has_journal'] ? 'Recorded' : 'Missing',
                    $deadlineStatus,
                    $session['journal_topic'] ?? '-',
                    $session['journal_method'] ?? '-',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Resolve date range with max 90 days constraint (DEC-41 D5).
     *
     * @return array{0: string, 1: string}
     */
    private function resolveReportDateRange(array $filters, string $todayWib, SchoolYear $schoolYear): array
    {
        $hasRange = (isset($filters['date_from']) && $filters['date_from'] !== '')
            || (isset($filters['date_to']) && $filters['date_to'] !== '');

        if ($hasRange) {
            $dateTo = isset($filters['date_to']) && $filters['date_to'] !== ''
                ? (string) $filters['date_to']
                : $todayWib;
            $dateFrom = isset($filters['date_from']) && $filters['date_from'] !== ''
                ? (string) $filters['date_from']
                : Carbon::parse($dateTo)->subDays(30)->toDateString();
        } else {
            $dateTo = $todayWib;
            $dateFrom = Carbon::parse($dateTo)->subDays(30)->toDateString();
        }

        try {
            $fromCarbon = Carbon::createFromFormat('Y-m-d', $dateFrom)->startOfDay();
            $toCarbon = Carbon::createFromFormat('Y-m-d', $dateTo)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'date_from' => ['The date filter must use YYYY-MM-DD format.'],
            ]);
        }

        if ($toCarbon->lt($fromCarbon)) {
            throw ValidationException::withMessages([
                'date_to' => ['The end date (date_to) must be on or after the start date (date_from).'],
            ]);
        }

        if ($fromCarbon->diffInDays($toCarbon) > 90) {
            throw ValidationException::withMessages([
                'date_to' => ['The report date range cannot exceed 90 days.'],
            ]);
        }

        return [$dateFrom, $dateTo];
    }

    /**
     * Evaluate sessions deterministically across the date range (DEC-41 D3).
     *
     * @return array{effective_sessions: array<int, array<string, mixed>>, excluded_slots_count: int}
     */
    private function evaluateSessions(
        SchoolYear $schoolYear,
        Semester $semester,
        string $dateFrom,
        string $dateTo,
        string $todayWib,
        string $graceDeadline,
        ?int $rombelId,
        ?int $subjectId,
        ?int $filterEmployeeId,
        ?Employee $selfEmployee,
        bool $canManage
    ): array {
        $schoolYearId = (int) $schoolYear->id;
        $semesterId = (int) $semester->id;
        $isYearLocked = $schoolYear->locked_at !== null;

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
            ])
            ->where('school_year_id', $schoolYearId)
            ->where('semester_id', $semesterId)
            ->whereBetween('session_date', [$dateFrom, $dateTo])
            ->when($rombelId !== null, fn ($q) => $q->where('school_rombel_id', $rombelId))
            ->when($subjectId !== null, fn ($q) => $q->where('subject_id', $subjectId))
            ->get();

        $journalsBySlotDate = [];
        foreach ($journals as $journal) {
            $key = $journal->teaching_schedule_id.'|'.$journal->session_date?->toDateString();
            $journalsBySlotDate[$key] = $journal;
        }

        $yearStart = $schoolYear->start_date?->toDateString();
        $yearEnd = $schoolYear->end_date?->toDateString();
        $activeDays = config('teaching.days_of_week', []);

        $effectiveSessions = [];
        $excludedSlotsCount = 0;

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
                    $excludedSlotsCount++;

                    continue;
                } elseif ($slotException && $slotException->exception_type === TeachingScheduleException::TYPE_CANCELLED) {
                    $excludedSlotsCount++;

                    continue;
                } elseif ($slotException && $slotException->exception_type === TeachingScheduleException::TYPE_RESCHEDULED) {
                    $excludedSlotsCount++;

                    continue;
                }

                $effectiveTeacherId = (int) $assignment->employee_id;
                $effectiveTeacher = $assignment->employee;

                if ($filterEmployeeId !== null && $effectiveTeacherId !== $filterEmployeeId) {
                    continue;
                }

                $journalKey = $schedule->id.'|'.$dateStr;
                $attachedJournal = $journalsBySlotDate[$journalKey] ?? null;

                $effectiveSessions[] = $this->buildReportSessionItem(
                    $dateStr,
                    $isoDay,
                    $dayLabel,
                    TeachingJournal::SOURCE_REGULAR,
                    $schedule,
                    $assignment,
                    null,
                    $attachedJournal,
                    (int) $schedule->period_start,
                    (int) $schedule->period_end,
                    (string) $schedule->start_time,
                    (string) $schedule->end_time,
                    $schedule->room,
                    $effectiveTeacherId,
                    $effectiveTeacher?->name,
                    $effectiveTeacher?->nuptk,
                    $todayWib,
                    $graceDeadline,
                    $isYearLocked,
                    $canManage,
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
                    $excludedSlotsCount++;

                    continue;
                }

                $effectiveTeacher = $rescheduledExc->substituteEmployee ?? $assignment->employee;
                $effectiveTeacherId = $rescheduledExc->substitute_employee_id !== null
                    ? (int) $rescheduledExc->substitute_employee_id
                    : (int) $assignment->employee_id;

                if ($filterEmployeeId !== null && $effectiveTeacherId !== $filterEmployeeId) {
                    continue;
                }

                $journalKey = $schedule->id.'|'.$dateStr;
                $attachedJournal = $journalsBySlotDate[$journalKey] ?? null;

                $effectiveSessions[] = $this->buildReportSessionItem(
                    $dateStr,
                    $isoDay,
                    $dayLabel,
                    TeachingJournal::SOURCE_RESCHEDULED,
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
                    $selfEmployee
                );
            }
        }

        return [
            'effective_sessions' => $effectiveSessions,
            'excluded_slots_count' => $excludedSlotsCount,
        ];
    }

    /**
     * Build normalized session representation for report and drilldown.
     *
     * @return array<string, mixed>
     */
    private function buildReportSessionItem(
        string $sessionDate,
        int $dayOfWeek,
        string $dayLabel,
        string $sourceType,
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
        ?Employee $selfEmployee
    ): array {
        $hasJournal = $journal !== null;
        $isMissing = ! $hasJournal;
        $isFutureDate = $sessionDate > $todayWib;
        $isWithinGrace = $sessionDate >= $graceDeadline && ! $isFutureDate;
        $isOverdue = $sessionDate < $graceDeadline;

        $selfId = $selfEmployee ? (int) $selfEmployee->id : null;
        $isOwnSession = $selfId !== null && $selfId === $effectiveTeacherId;

        $canRecord = ! $isYearLocked && ! $isFutureDate && (
            $canManage || ($isOwnSession && $isWithinGrace)
        );

        $statusBadge = $hasJournal
            ? 'recorded'
            : ($isWithinGrace ? 'missing_grace' : 'missing_overdue');

        $periodLabel = $effectivePeriodStart === $effectivePeriodEnd
            ? "Jam {$effectivePeriodStart}"
            : "Jam {$effectivePeriodStart}-{$effectivePeriodEnd}";

        $timeLabel = "{$effectiveStartTime} - {$effectiveEndTime}";

        return [
            'session_key' => $schedule->id.'_'.$sessionDate.'_'.$sourceType,
            'session_date' => $sessionDate,
            'day_of_week' => $dayOfWeek,
            'day_label' => $dayLabel,
            'period_start' => $effectivePeriodStart,
            'period_end' => $effectivePeriodEnd,
            'period_label' => $periodLabel,
            'start_time' => $effectiveStartTime,
            'end_time' => $effectiveEndTime,
            'time_label' => $timeLabel,
            'room' => $effectiveRoom,
            'source_type' => $sourceType,
            'source_type_label' => ucfirst($sourceType),
            'teaching_schedule_id' => (int) $schedule->id,
            'teaching_assignment_id' => (int) $assignment->id,
            'teaching_schedule_exception_id' => $exception?->id ? (int) $exception->id : null,
            'school_rombel_id' => (int) $assignment->school_rombel_id,
            'school_rombel_name' => $assignment->schoolRombel?->name,
            'subject_id' => (int) $assignment->subject_id,
            'subject_name' => $assignment->subject?->name,
            'assigned_teacher_id' => (int) $assignment->employee_id,
            'assigned_teacher_name' => $assignment->employee?->name,
            'teacher_employee_id' => $effectiveTeacherId,
            'teacher_name' => $effectiveTeacherName,
            'teacher_nuptk' => $effectiveTeacherNuptk,
            'is_substitute' => $effectiveTeacherId !== (int) $assignment->employee_id,
            'has_journal' => $hasJournal,
            'journal_id' => $journal?->id ? (int) $journal->id : null,
            'journal_topic' => $journal?->topic,
            'journal_method' => $journal?->method,
            'is_missing' => $isMissing,
            'is_within_grace_window' => $isWithinGrace,
            'is_overdue' => $isOverdue,
            'grace_deadline_date' => $graceDeadline,
            'status_badge' => $statusBadge,
            'can_record' => $canRecord,
        ];
    }

    /**
     * Aggregate compliance metrics per teacher (DEC-41 D3: expected > 0 only).
     *
     * @param  array<int, array<string, mixed>>  $sessions
     * @return array<int, array<string, mixed>>
     */
    private function aggregateByTeacher(array $sessions): array
    {
        $byTeacherMap = [];

        foreach ($sessions as $session) {
            $teacherId = (int) $session['teacher_employee_id'];

            if (! isset($byTeacherMap[$teacherId])) {
                $byTeacherMap[$teacherId] = [
                    'employee_id' => $teacherId,
                    'employee_name' => $session['teacher_name'] ?? 'Unknown Teacher',
                    'employee_nuptk' => $session['teacher_nuptk'],
                    'primary_subject_name' => $session['subject_name'] ?? null,
                    'expected_sessions' => 0,
                    'recorded_journals' => 0,
                    'missing_journals' => 0,
                    'missing_within_grace' => 0,
                    'missing_overdue' => 0,
                    'compliance_percentage' => 100.0,
                ];
            }

            $byTeacherMap[$teacherId]['expected_sessions']++;

            if ($session['has_journal']) {
                $byTeacherMap[$teacherId]['recorded_journals']++;
            } else {
                $byTeacherMap[$teacherId]['missing_journals']++;
                if ($session['is_within_grace_window']) {
                    $byTeacherMap[$teacherId]['missing_within_grace']++;
                } else {
                    $byTeacherMap[$teacherId]['missing_overdue']++;
                }
            }
        }

        // Calculate compliance percentage for each teacher
        foreach ($byTeacherMap as &$teacher) {
            $expected = $teacher['expected_sessions'];
            $recorded = $teacher['recorded_journals'];
            $teacher['compliance_percentage'] = $expected > 0
                ? round(($recorded / $expected) * 100, 1)
                : 100.0;
        }
        unset($teacher);

        $teacherList = array_values($byTeacherMap);

        // Sort by most missing journals first, then lowest compliance, then name ASC
        usort($teacherList, function (array $a, array $b) {
            return [$b['missing_journals'], $a['compliance_percentage'], $a['employee_name']]
                <=> [$a['missing_journals'], $b['compliance_percentage'], $b['employee_name']];
        });

        return $teacherList;
    }

    /**
     * Compute school-wide KPI summary metrics (DEC-41 D3 & D4).
     *
     * @param  array<int, array<string, mixed>>  $byTeacher
     * @return array<string, mixed>
     */
    private function computeSummary(array $byTeacher, int $excludedSlotsCount): array
    {
        $totalExpected = 0;
        $totalRecorded = 0;
        $totalMissing = 0;
        $totalMissingGrace = 0;
        $totalMissingOverdue = 0;

        foreach ($byTeacher as $t) {
            $totalExpected += $t['expected_sessions'];
            $totalRecorded += $t['recorded_journals'];
            $totalMissing += $t['missing_journals'];
            $totalMissingGrace += $t['missing_within_grace'];
            $totalMissingOverdue += $t['missing_overdue'];
        }

        $overallCompliance = $totalExpected > 0
            ? round(($totalRecorded / $totalExpected) * 100, 1)
            : 100.0;

        return [
            'total_teachers_with_sessions' => count($byTeacher),
            'total_expected_sessions' => $totalExpected,
            'total_recorded_journals' => $totalRecorded,
            'total_missing_journals' => $totalMissing,
            'total_missing_within_grace' => $totalMissingGrace,
            'total_missing_overdue' => $totalMissingOverdue,
            'overall_compliance_percentage' => $overallCompliance,
            'total_excluded_slots' => $excludedSlotsCount,
        ];
    }
}
