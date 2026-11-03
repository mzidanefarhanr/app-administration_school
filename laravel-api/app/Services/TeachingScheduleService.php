<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TeachingSchedule;
use App\Models\TeachingScheduleException;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeachingScheduleService
{
    public function createAssignment(array $data, int $actorId): TeachingAssignment
    {
        return DB::transaction(function () use ($data, $actorId) {
            $schoolYear = $this->lockAndValidateWritableSchoolYear((int) $data['school_year_id']);
            $semester = $this->validateSemester((int) $data['semester_id']);
            $rombel = $this->validateRombelForYear((int) $data['school_rombel_id'], (int) $schoolYear->id);
            $subject = $this->validateSubject((int) $data['subject_id']);
            $teacher = $this->validateEligibleTeacher((int) $data['employee_id']);

            $this->assertUniqueAssignment(
                (int) $schoolYear->id,
                (int) $semester->id,
                (int) $rombel->id,
                (int) $subject->id,
                (int) $teacher->id
            );

            $assignment = TeachingAssignment::create([
                'school_year_id' => $schoolYear->id,
                'semester_id' => $semester->id,
                'school_rombel_id' => $rombel->id,
                'subject_id' => $subject->id,
                'employee_id' => $teacher->id,
                'hours_per_week' => isset($data['hours_per_week']) && $data['hours_per_week'] !== ''
                    ? (int) $data['hours_per_week']
                    : null,
                'status' => $data['status'] ?? TeachingAssignment::STATUS_ACTIVE,
                'notes' => $this->normalizeOptionalText($data['notes'] ?? null),
                'version' => 1,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $this->loadAssignmentRelations($assignment);
        });
    }

    public function updateAssignment(TeachingAssignment $snapshot, array $data, int $actorId): TeachingAssignment
    {
        return DB::transaction(function () use ($snapshot, $data, $actorId) {
            $currentYear = $this->lockAndValidateWritableSchoolYear((int) $snapshot->school_year_id);

            $targetYearId = isset($data['school_year_id']) ? (int) $data['school_year_id'] : (int) $snapshot->school_year_id;
            $targetYear = $targetYearId === (int) $currentYear->id
                ? $currentYear
                : $this->lockAndValidateWritableSchoolYear($targetYearId);

            $assignment = TeachingAssignment::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();

            $expectedVersion = (int) $data['expected_version'];
            if ((int) $assignment->version !== $expectedVersion) {
                $this->abortConflict(
                    $this->serializeAssignment($this->loadAssignmentRelations($assignment)),
                    'Conflict: This teaching assignment was modified by another user. Please reload the latest data.'
                );
            }

            $semesterId = isset($data['semester_id']) ? (int) $data['semester_id'] : (int) $assignment->semester_id;
            $rombelId = isset($data['school_rombel_id']) ? (int) $data['school_rombel_id'] : (int) $assignment->school_rombel_id;
            $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : (int) $assignment->subject_id;
            $employeeId = isset($data['employee_id']) ? (int) $data['employee_id'] : (int) $assignment->employee_id;
            $status = $data['status'] ?? $assignment->status;

            $this->validateSemester($semesterId);
            $this->validateRombelForYear($rombelId, (int) $targetYear->id);
            $this->validateSubject($subjectId);

            if ($employeeId !== (int) $assignment->employee_id || $status === TeachingAssignment::STATUS_ACTIVE) {
                $this->validateEligibleTeacher($employeeId);
            }

            $hasSchedules = TeachingSchedule::where('teaching_assignment_id', $assignment->id)->exists();
            if ($hasSchedules && (
                (int) $targetYear->id !== (int) $assignment->school_year_id
                || $semesterId !== (int) $assignment->semester_id
                || $rombelId !== (int) $assignment->school_rombel_id
            )) {
                throw ValidationException::withMessages([
                    'school_rombel_id' => [
                        'The school year, semester, or class cannot be changed because this assignment already has schedule slots.',
                    ],
                ]);
            }

            $this->assertUniqueAssignment(
                (int) $targetYear->id,
                $semesterId,
                $rombelId,
                $subjectId,
                $employeeId,
                (int) $assignment->id
            );

            $teacherChanged = $employeeId !== (int) $assignment->employee_id;
            $reactivating = $assignment->status !== TeachingAssignment::STATUS_ACTIVE
                && $status === TeachingAssignment::STATUS_ACTIVE;

            $assignment->fill([
                'school_year_id' => $targetYear->id,
                'semester_id' => $semesterId,
                'school_rombel_id' => $rombelId,
                'subject_id' => $subjectId,
                'employee_id' => $employeeId,
                'hours_per_week' => array_key_exists('hours_per_week', $data)
                    ? ($data['hours_per_week'] !== null && $data['hours_per_week'] !== '' ? (int) $data['hours_per_week'] : null)
                    : $assignment->hours_per_week,
                'status' => $status,
                'notes' => array_key_exists('notes', $data)
                    ? $this->normalizeOptionalText($data['notes'])
                    : $assignment->notes,
                'version' => (int) $assignment->version + 1,
                'updated_by' => $actorId,
            ]);
            $assignment->save();

            if ($status === TeachingAssignment::STATUS_ACTIVE && ($teacherChanged || $reactivating)) {
                $activeSchedules = TeachingSchedule::where('teaching_assignment_id', $assignment->id)
                    ->where('status', TeachingSchedule::STATUS_ACTIVE)
                    ->get();

                foreach ($activeSchedules as $slot) {
                    $this->assertNoScheduleCollisions(
                        $assignment,
                        (int) $slot->day_of_week,
                        (int) $slot->period_start,
                        (int) $slot->period_end,
                        (string) $slot->start_time,
                        (string) $slot->end_time,
                        $slot->room_normalized,
                        (int) $slot->id
                    );
                }
            }

            return $this->loadAssignmentRelations($assignment);
        });
    }

    public function deleteAssignment(TeachingAssignment $snapshot): void
    {
        DB::transaction(function () use ($snapshot) {
            $this->lockAndValidateWritableSchoolYear((int) $snapshot->school_year_id);

            $assignment = TeachingAssignment::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();

            $scheduleCount = TeachingSchedule::where('teaching_assignment_id', $assignment->id)->count();
            if ($scheduleCount > 0) {
                throw ValidationException::withMessages([
                    'id' => [
                        "This teaching assignment already has {$scheduleCount} schedule slot(s) and cannot be deleted. Remove its schedule slots first or set the assignment to inactive.",
                    ],
                ]);
            }

            $assignment->delete();
        });
    }

    public function createSchedule(array $data, int $actorId): TeachingSchedule
    {
        return DB::transaction(function () use ($data, $actorId) {
            $assignment = TeachingAssignment::with(['schoolRombel', 'subject', 'employee'])
                ->findOrFail((int) $data['teaching_assignment_id']);

            $this->lockAndValidateWritableSchoolYear((int) $assignment->school_year_id);

            $assignment = TeachingAssignment::with(['schoolRombel', 'subject', 'employee'])
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $dayOfWeek = (int) $data['day_of_week'];
            $periodStart = (int) $data['period_start'];
            $periodEnd = (int) $data['period_end'];
            $startTime = (string) $data['start_time'];
            $endTime = (string) $data['end_time'];
            $room = $this->normalizeOptionalText($data['room'] ?? null);
            $roomNormalized = TeachingSchedule::normalizeRoom($room);
            $status = $data['status'] ?? TeachingSchedule::STATUS_ACTIVE;

            if ($status === TeachingSchedule::STATUS_ACTIVE && $assignment->status === TeachingAssignment::STATUS_ACTIVE) {
                $this->assertNoScheduleCollisions(
                    $assignment,
                    $dayOfWeek,
                    $periodStart,
                    $periodEnd,
                    $startTime,
                    $endTime,
                    $roomNormalized
                );
            }

            $schedule = TeachingSchedule::create([
                'teaching_assignment_id' => $assignment->id,
                'day_of_week' => $dayOfWeek,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'room' => $room,
                'room_normalized' => $roomNormalized,
                'status' => $status,
                'notes' => $this->normalizeOptionalText($data['notes'] ?? null),
                'version' => 1,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $this->loadScheduleRelations($schedule);
        });
    }

    public function updateSchedule(TeachingSchedule $snapshot, array $data, int $actorId): TeachingSchedule
    {
        return DB::transaction(function () use ($snapshot, $data, $actorId) {
            $currentAssignment = TeachingAssignment::findOrFail((int) $snapshot->teaching_assignment_id);
            $this->lockAndValidateWritableSchoolYear((int) $currentAssignment->school_year_id);

            $schedule = TeachingSchedule::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();

            $expectedVersion = (int) $data['expected_version'];
            if ((int) $schedule->version !== $expectedVersion) {
                $this->abortConflict(
                    $this->serializeSchedule($this->loadScheduleRelations($schedule)),
                    'Conflict: This schedule slot was modified by another user. Please reload the latest data.'
                );
            }

            $targetAssignmentId = isset($data['teaching_assignment_id'])
                ? (int) $data['teaching_assignment_id']
                : (int) $schedule->teaching_assignment_id;

            $assignment = TeachingAssignment::with(['schoolRombel', 'subject', 'employee'])
                ->whereKey($targetAssignmentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $assignment->school_year_id !== (int) $currentAssignment->school_year_id) {
                $this->lockAndValidateWritableSchoolYear((int) $assignment->school_year_id);
            }

            $dayOfWeek = isset($data['day_of_week']) ? (int) $data['day_of_week'] : (int) $schedule->day_of_week;
            $periodStart = isset($data['period_start']) ? (int) $data['period_start'] : (int) $schedule->period_start;
            $periodEnd = isset($data['period_end']) ? (int) $data['period_end'] : (int) $schedule->period_end;
            $startTime = isset($data['start_time']) ? (string) $data['start_time'] : (string) $schedule->start_time;
            $endTime = isset($data['end_time']) ? (string) $data['end_time'] : (string) $schedule->end_time;
            $room = array_key_exists('room', $data)
                ? $this->normalizeOptionalText($data['room'])
                : $schedule->room;
            $roomNormalized = TeachingSchedule::normalizeRoom($room);
            $status = $data['status'] ?? $schedule->status;

            $hasExceptions = TeachingScheduleException::where('teaching_schedule_id', $schedule->id)->exists();
            if ($hasExceptions && (
                $targetAssignmentId !== (int) $schedule->teaching_assignment_id
                || $dayOfWeek !== (int) $schedule->day_of_week
            )) {
                throw ValidationException::withMessages([
                    'day_of_week' => [
                        'The teaching assignment or day of week cannot be changed because this schedule slot already has calendar exceptions.',
                    ],
                ]);
            }

            if ($status === TeachingSchedule::STATUS_ACTIVE && $assignment->status === TeachingAssignment::STATUS_ACTIVE) {
                $this->assertNoScheduleCollisions(
                    $assignment,
                    $dayOfWeek,
                    $periodStart,
                    $periodEnd,
                    $startTime,
                    $endTime,
                    $roomNormalized,
                    (int) $schedule->id
                );
            }

            $schedule->fill([
                'teaching_assignment_id' => $assignment->id,
                'day_of_week' => $dayOfWeek,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'room' => $room,
                'room_normalized' => $roomNormalized,
                'status' => $status,
                'notes' => array_key_exists('notes', $data)
                    ? $this->normalizeOptionalText($data['notes'])
                    : $schedule->notes,
                'version' => (int) $schedule->version + 1,
                'updated_by' => $actorId,
            ]);
            $schedule->save();

            return $this->loadScheduleRelations($schedule);
        });
    }

    public function deleteSchedule(TeachingSchedule $snapshot): void
    {
        DB::transaction(function () use ($snapshot) {
            $assignment = TeachingAssignment::findOrFail((int) $snapshot->teaching_assignment_id);
            $this->lockAndValidateWritableSchoolYear((int) $assignment->school_year_id);

            $schedule = TeachingSchedule::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();

            $exceptionCount = TeachingScheduleException::where('teaching_schedule_id', $schedule->id)->count();
            if ($exceptionCount > 0) {
                throw ValidationException::withMessages([
                    'id' => [
                        "This schedule slot is referenced by {$exceptionCount} calendar exception(s) and cannot be deleted. Remove the exceptions first or set the slot to inactive.",
                    ],
                ]);
            }

            $schedule->delete();
        });
    }

    public function createException(array $data, int $actorId): TeachingScheduleException
    {
        return DB::transaction(function () use ($data, $actorId) {
            $schoolYear = $this->lockAndValidateWritableSchoolYear((int) $data['school_year_id']);
            $semester = $this->validateSemester((int) $data['semester_id']);

            $normalized = $this->validateAndPrepareExceptionPayload(
                $schoolYear,
                (int) $semester->id,
                $data
            );

            $exception = TeachingScheduleException::create(array_merge($normalized, [
                'version' => 1,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]));

            return $this->loadExceptionRelations($exception);
        });
    }

    public function updateException(TeachingScheduleException $snapshot, array $data, int $actorId): TeachingScheduleException
    {
        return DB::transaction(function () use ($snapshot, $data, $actorId) {
            $currentYear = $this->lockAndValidateWritableSchoolYear((int) $snapshot->school_year_id);

            $targetYearId = isset($data['school_year_id']) ? (int) $data['school_year_id'] : (int) $snapshot->school_year_id;
            $targetYear = $targetYearId === (int) $currentYear->id
                ? $currentYear
                : $this->lockAndValidateWritableSchoolYear($targetYearId);

            $exception = TeachingScheduleException::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();

            $expectedVersion = (int) $data['expected_version'];
            if ((int) $exception->version !== $expectedVersion) {
                $this->abortConflict(
                    $this->serializeException($this->loadExceptionRelations($exception)),
                    'Conflict: This schedule exception was modified by another user. Please reload the latest data.'
                );
            }

            $semesterId = isset($data['semester_id']) ? (int) $data['semester_id'] : (int) $exception->semester_id;
            $this->validateSemester($semesterId);

            $mergedInput = [
                'exception_type' => $data['exception_type'] ?? $exception->exception_type,
                'exception_date' => $data['exception_date'] ?? $exception->exception_date?->toDateString(),
                'school_rombel_id' => array_key_exists('school_rombel_id', $data)
                    ? $data['school_rombel_id']
                    : $exception->school_rombel_id,
                'teaching_schedule_id' => array_key_exists('teaching_schedule_id', $data)
                    ? $data['teaching_schedule_id']
                    : $exception->teaching_schedule_id,
                'rescheduled_date' => array_key_exists('rescheduled_date', $data)
                    ? $data['rescheduled_date']
                    : $exception->rescheduled_date?->toDateString(),
                'rescheduled_period_start' => array_key_exists('rescheduled_period_start', $data)
                    ? $data['rescheduled_period_start']
                    : $exception->rescheduled_period_start,
                'rescheduled_period_end' => array_key_exists('rescheduled_period_end', $data)
                    ? $data['rescheduled_period_end']
                    : $exception->rescheduled_period_end,
                'rescheduled_start_time' => array_key_exists('rescheduled_start_time', $data)
                    ? $data['rescheduled_start_time']
                    : $exception->rescheduled_start_time,
                'rescheduled_end_time' => array_key_exists('rescheduled_end_time', $data)
                    ? $data['rescheduled_end_time']
                    : $exception->rescheduled_end_time,
                'rescheduled_room' => array_key_exists('rescheduled_room', $data)
                    ? $data['rescheduled_room']
                    : $exception->rescheduled_room,
                'substitute_employee_id' => array_key_exists('substitute_employee_id', $data)
                    ? $data['substitute_employee_id']
                    : $exception->substitute_employee_id,
                'reason' => $data['reason'] ?? $exception->reason,
            ];

            $normalized = $this->validateAndPrepareExceptionPayload(
                $targetYear,
                $semesterId,
                $mergedInput,
                (int) $exception->id
            );

            $exception->fill(array_merge($normalized, [
                'version' => (int) $exception->version + 1,
                'updated_by' => $actorId,
            ]));
            $exception->save();

            return $this->loadExceptionRelations($exception);
        });
    }

    public function deleteException(TeachingScheduleException $snapshot): void
    {
        DB::transaction(function () use ($snapshot) {
            $this->lockAndValidateWritableSchoolYear((int) $snapshot->school_year_id);

            $exception = TeachingScheduleException::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            $exception->delete();
        });
    }

    /**
     * Check teacher, class/rombel, and room collisions on the same period + day_of_week (DEC-39 D4).
     */
    public function assertNoScheduleCollisions(
        TeachingAssignment $assignment,
        int $dayOfWeek,
        int $periodStart,
        int $periodEnd,
        string $startTime,
        string $endTime,
        ?string $roomNormalized,
        ?int $ignoreScheduleId = null
    ): void {
        $candidates = TeachingSchedule::query()
            ->with(['assignment.schoolRombel', 'assignment.subject', 'assignment.employee'])
            ->where('day_of_week', $dayOfWeek)
            ->where('status', TeachingSchedule::STATUS_ACTIVE)
            ->when($ignoreScheduleId !== null, fn ($q) => $q->whereKeyNot($ignoreScheduleId))
            ->where(function ($q) use ($startTime, $endTime, $periodStart, $periodEnd) {
                $q->where(function ($timeQ) use ($startTime, $endTime) {
                    $timeQ->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                })->orWhere(function ($periodQ) use ($periodStart, $periodEnd) {
                    $periodQ->where('period_start', '<=', $periodEnd)
                        ->where('period_end', '>=', $periodStart);
                });
            })
            ->whereHas('assignment', function ($q) use ($assignment) {
                $q->where('school_year_id', $assignment->school_year_id)
                    ->where('semester_id', $assignment->semester_id)
                    ->where('status', TeachingAssignment::STATUS_ACTIVE);
            })
            ->get();

        $dayLabel = config("teaching.days_of_week.{$dayOfWeek}", "Day {$dayOfWeek}");

        foreach ($candidates as $existing) {
            $existingAssignment = $existing->assignment;
            if (! $existingAssignment) {
                continue;
            }

            $existingClass = $existingAssignment->schoolRombel?->name ?? "#{$existingAssignment->school_rombel_id}";
            $existingSubject = $existingAssignment->subject?->name ?? "#{$existingAssignment->subject_id}";
            $existingTeacher = $existingAssignment->employee?->name ?? "#{$existingAssignment->employee_id}";
            $slotWindow = "Period {$existing->period_start}–{$existing->period_end} ({$existing->start_time}–{$existing->end_time})";

            if ((int) $existingAssignment->employee_id === (int) $assignment->employee_id) {
                throw ValidationException::withMessages([
                    'teaching_assignment_id' => [
                        "Teacher collision: {$existingTeacher} is already scheduled for {$existingSubject} in class {$existingClass} on {$dayLabel}, {$slotWindow}.",
                    ],
                ]);
            }

            if ((int) $existingAssignment->school_rombel_id === (int) $assignment->school_rombel_id) {
                throw ValidationException::withMessages([
                    'start_time' => [
                        "Class collision: Class {$existingClass} already has {$existingSubject} ({$existingTeacher}) on {$dayLabel}, {$slotWindow}.",
                    ],
                ]);
            }

            if ($roomNormalized !== null && $existing->room_normalized === $roomNormalized) {
                throw ValidationException::withMessages([
                    'room' => [
                        "Room collision: Room {$existing->room} is already occupied by class {$existingClass} ({$existingSubject}) on {$dayLabel}, {$slotWindow}.",
                    ],
                ]);
            }
        }
    }

    private function validateAndPrepareExceptionPayload(
        SchoolYear $schoolYear,
        int $semesterId,
        array $data,
        ?int $ignoreExceptionId = null
    ): array {
        $type = (string) $data['exception_type'];
        $exceptionDate = (string) $data['exception_date'];
        $reason = trim((string) preg_replace('/\s+/', ' ', (string) $data['reason']));

        $this->assertDateWithinSchoolYear($schoolYear, $exceptionDate, 'exception_date');

        if ($type === TeachingScheduleException::TYPE_HOLIDAY) {
            $rombelId = isset($data['school_rombel_id']) && $data['school_rombel_id'] !== ''
                ? (int) $data['school_rombel_id']
                : null;

            if ($rombelId !== null) {
                $this->validateRombelForYear($rombelId, (int) $schoolYear->id);
            }

            $duplicateHoliday = TeachingScheduleException::query()
                ->where('school_year_id', $schoolYear->id)
                ->where('semester_id', $semesterId)
                ->where('exception_type', TeachingScheduleException::TYPE_HOLIDAY)
                ->whereDate('exception_date', $exceptionDate)
                ->when(
                    $rombelId === null,
                    fn ($q) => $q->whereNull('school_rombel_id'),
                    fn ($q) => $q->where('school_rombel_id', $rombelId)
                )
                ->when($ignoreExceptionId !== null, fn ($q) => $q->whereKeyNot($ignoreExceptionId))
                ->exists();

            if ($duplicateHoliday) {
                throw ValidationException::withMessages([
                    'exception_date' => [
                        'A holiday exception for this date and scope already exists.',
                    ],
                ]);
            }

            $rescheduledTargetingHoliday = TeachingScheduleException::query()
                ->where('school_year_id', $schoolYear->id)
                ->where('semester_id', $semesterId)
                ->where('exception_type', TeachingScheduleException::TYPE_RESCHEDULED)
                ->whereDate('rescheduled_date', $exceptionDate)
                ->when(
                    $rombelId !== null,
                    fn ($q) => $q->where('school_rombel_id', $rombelId)
                )
                ->when($ignoreExceptionId !== null, fn ($q) => $q->whereKeyNot($ignoreExceptionId))
                ->exists();

            if ($rescheduledTargetingHoliday) {
                throw ValidationException::withMessages([
                    'exception_date' => [
                        "Cannot mark {$exceptionDate} as a holiday because a rescheduled session already targets this date.",
                    ],
                ]);
            }

            return [
                'school_year_id' => $schoolYear->id,
                'semester_id' => $semesterId,
                'exception_type' => TeachingScheduleException::TYPE_HOLIDAY,
                'exception_date' => $exceptionDate,
                'school_rombel_id' => $rombelId,
                'teaching_schedule_id' => null,
                'rescheduled_date' => null,
                'rescheduled_period_start' => null,
                'rescheduled_period_end' => null,
                'rescheduled_start_time' => null,
                'rescheduled_end_time' => null,
                'rescheduled_room' => null,
                'rescheduled_room_normalized' => null,
                'substitute_employee_id' => null,
                'reason' => $reason,
            ];
        }

        $scheduleId = isset($data['teaching_schedule_id']) && $data['teaching_schedule_id'] !== ''
            ? (int) $data['teaching_schedule_id']
            : null;

        if ($scheduleId === null) {
            throw ValidationException::withMessages([
                'teaching_schedule_id' => ['A teaching schedule slot is required for cancelled or rescheduled exceptions.'],
            ]);
        }

        $schedule = TeachingSchedule::with(['assignment.schoolRombel', 'assignment.subject', 'assignment.employee'])
            ->find($scheduleId);

        if (! $schedule || ! $schedule->assignment) {
            throw ValidationException::withMessages([
                'teaching_schedule_id' => ['The selected teaching schedule slot was not found.'],
            ]);
        }

        $assignment = $schedule->assignment;
        if ((int) $assignment->school_year_id !== (int) $schoolYear->id || (int) $assignment->semester_id !== $semesterId) {
            throw ValidationException::withMessages([
                'teaching_schedule_id' => ['The selected schedule slot does not belong to the chosen school year and semester.'],
            ]);
        }

        $isoDay = Carbon::parse($exceptionDate)->dayOfWeekIso;
        if ($isoDay !== (int) $schedule->day_of_week) {
            $dateDayName = config("teaching.days_of_week.{$isoDay}", 'Sunday');
            $slotDayName = config("teaching.days_of_week.{$schedule->day_of_week}", "Day {$schedule->day_of_week}");
            throw ValidationException::withMessages([
                'exception_date' => [
                    "The exception date ({$exceptionDate}, {$dateDayName}) does not match the schedule slot's day of week ({$slotDayName}).",
                ],
            ]);
        }

        $duplicateSlotException = TeachingScheduleException::query()
            ->where('teaching_schedule_id', $schedule->id)
            ->whereDate('exception_date', $exceptionDate)
            ->when($ignoreExceptionId !== null, fn ($q) => $q->whereKeyNot($ignoreExceptionId))
            ->exists();

        if ($duplicateSlotException) {
            throw ValidationException::withMessages([
                'exception_date' => [
                    'An exception for this schedule slot on the selected date already exists.',
                ],
            ]);
        }

        $rombelId = (int) $assignment->school_rombel_id;

        if ($type === TeachingScheduleException::TYPE_CANCELLED) {
            return [
                'school_year_id' => $schoolYear->id,
                'semester_id' => $semesterId,
                'exception_type' => TeachingScheduleException::TYPE_CANCELLED,
                'exception_date' => $exceptionDate,
                'school_rombel_id' => $rombelId,
                'teaching_schedule_id' => $schedule->id,
                'rescheduled_date' => null,
                'rescheduled_period_start' => null,
                'rescheduled_period_end' => null,
                'rescheduled_start_time' => null,
                'rescheduled_end_time' => null,
                'rescheduled_room' => null,
                'rescheduled_room_normalized' => null,
                'substitute_employee_id' => null,
                'reason' => $reason,
            ];
        }

        // TYPE_RESCHEDULED
        $rescheduledDate = (string) ($data['rescheduled_date'] ?? '');
        $rescheduledPeriodStart = (int) ($data['rescheduled_period_start'] ?? 0);
        $rescheduledPeriodEnd = (int) ($data['rescheduled_period_end'] ?? 0);
        $rescheduledStartTime = (string) ($data['rescheduled_start_time'] ?? '');
        $rescheduledEndTime = (string) ($data['rescheduled_end_time'] ?? '');
        $rescheduledRoom = $this->normalizeOptionalText($data['rescheduled_room'] ?? null);
        $rescheduledRoomNormalized = TeachingSchedule::normalizeRoom($rescheduledRoom);
        $substituteEmployeeId = isset($data['substitute_employee_id']) && $data['substitute_employee_id'] !== ''
            ? (int) $data['substitute_employee_id']
            : null;

        $this->assertDateWithinSchoolYear($schoolYear, $rescheduledDate, 'rescheduled_date');

        $targetIsoDay = Carbon::parse($rescheduledDate)->dayOfWeekIso;
        if (! array_key_exists($targetIsoDay, config('teaching.days_of_week', []))) {
            throw ValidationException::withMessages([
                'rescheduled_date' => ['The rescheduled date must fall on an active school day (Monday to Saturday).'],
            ]);
        }

        if ($substituteEmployeeId !== null) {
            $this->validateEligibleTeacher($substituteEmployeeId, 'substitute_employee_id');
        }

        if (
            $rescheduledDate === $exceptionDate
            && $rescheduledStartTime === (string) $schedule->start_time
            && $rescheduledEndTime === (string) $schedule->end_time
            && $substituteEmployeeId === null
            && $rescheduledRoomNormalized === $schedule->room_normalized
        ) {
            throw ValidationException::withMessages([
                'rescheduled_date' => [
                    'A rescheduled slot must change the date, time, room, or substitute teacher.',
                ],
            ]);
        }

        $this->assertNoRescheduleCollisions(
            $schoolYear,
            $semesterId,
            $schedule,
            $exceptionDate,
            $rescheduledDate,
            $targetIsoDay,
            $rescheduledPeriodStart,
            $rescheduledPeriodEnd,
            $rescheduledStartTime,
            $rescheduledEndTime,
            $rescheduledRoom,
            $rescheduledRoomNormalized,
            $substituteEmployeeId,
            $ignoreExceptionId
        );

        return [
            'school_year_id' => $schoolYear->id,
            'semester_id' => $semesterId,
            'exception_type' => TeachingScheduleException::TYPE_RESCHEDULED,
            'exception_date' => $exceptionDate,
            'school_rombel_id' => $rombelId,
            'teaching_schedule_id' => $schedule->id,
            'rescheduled_date' => $rescheduledDate,
            'rescheduled_period_start' => $rescheduledPeriodStart,
            'rescheduled_period_end' => $rescheduledPeriodEnd,
            'rescheduled_start_time' => $rescheduledStartTime,
            'rescheduled_end_time' => $rescheduledEndTime,
            'rescheduled_room' => $rescheduledRoom,
            'rescheduled_room_normalized' => $rescheduledRoomNormalized,
            'substitute_employee_id' => $substituteEmployeeId,
            'reason' => $reason,
        ];
    }

    private function assertNoRescheduleCollisions(
        SchoolYear $schoolYear,
        int $semesterId,
        TeachingSchedule $sourceSchedule,
        string $exceptionDate,
        string $rescheduledDate,
        int $targetIsoDay,
        int $rescheduledPeriodStart,
        int $rescheduledPeriodEnd,
        string $rescheduledStartTime,
        string $rescheduledEndTime,
        ?string $rescheduledRoom,
        ?string $rescheduledRoomNormalized,
        ?int $substituteEmployeeId,
        ?int $ignoreExceptionId = null
    ): void {
        $rombelId = (int) $sourceSchedule->assignment->school_rombel_id;
        $effectiveTeacherId = $substituteEmployeeId !== null
            ? $substituteEmployeeId
            : (int) $sourceSchedule->assignment->employee_id;

        // 1. Reject if target date is a holiday for the whole school or this class
        $isTargetHoliday = TeachingScheduleException::query()
            ->where('school_year_id', $schoolYear->id)
            ->where('semester_id', $semesterId)
            ->where('exception_type', TeachingScheduleException::TYPE_HOLIDAY)
            ->whereDate('exception_date', $rescheduledDate)
            ->where(function ($q) use ($rombelId) {
                $q->whereNull('school_rombel_id')
                    ->orWhere('school_rombel_id', $rombelId);
            })
            ->when($ignoreExceptionId !== null, fn ($q) => $q->whereKeyNot($ignoreExceptionId))
            ->exists();

        if ($isTargetHoliday) {
            throw ValidationException::withMessages([
                'rescheduled_date' => [
                    "Cannot reschedule onto {$rescheduledDate} because that date is marked as a holiday.",
                ],
            ]);
        }

        // Collect schedule IDs that are cancelled or rescheduled on the target date
        $inactiveSlotIdsOnTargetDate = TeachingScheduleException::query()
            ->where('school_year_id', $schoolYear->id)
            ->where('semester_id', $semesterId)
            ->whereIn('exception_type', [
                TeachingScheduleException::TYPE_CANCELLED,
                TeachingScheduleException::TYPE_RESCHEDULED,
            ])
            ->whereDate('exception_date', $rescheduledDate)
            ->when($ignoreExceptionId !== null, fn ($q) => $q->whereKeyNot($ignoreExceptionId))
            ->pluck('teaching_schedule_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($rescheduledDate === $exceptionDate) {
            $inactiveSlotIdsOnTargetDate[] = (int) $sourceSchedule->id;
        }

        // Collect class IDs that have a class-specific holiday on the target date
        $holidayRombelIdsOnTargetDate = TeachingScheduleException::query()
            ->where('school_year_id', $schoolYear->id)
            ->where('semester_id', $semesterId)
            ->where('exception_type', TeachingScheduleException::TYPE_HOLIDAY)
            ->whereDate('exception_date', $rescheduledDate)
            ->whereNotNull('school_rombel_id')
            ->when($ignoreExceptionId !== null, fn ($q) => $q->whereKeyNot($ignoreExceptionId))
            ->pluck('school_rombel_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // 2. Check against regular active schedules on target day of week
        $regularCandidates = TeachingSchedule::query()
            ->with(['assignment.schoolRombel', 'assignment.subject', 'assignment.employee'])
            ->where('day_of_week', $targetIsoDay)
            ->where('status', TeachingSchedule::STATUS_ACTIVE)
            ->when(! empty($inactiveSlotIdsOnTargetDate), fn ($q) => $q->whereNotIn('id', $inactiveSlotIdsOnTargetDate))
            ->where(function ($q) use ($rescheduledStartTime, $rescheduledEndTime, $rescheduledPeriodStart, $rescheduledPeriodEnd) {
                $q->where(function ($timeQ) use ($rescheduledStartTime, $rescheduledEndTime) {
                    $timeQ->where('start_time', '<', $rescheduledEndTime)
                        ->where('end_time', '>', $rescheduledStartTime);
                })->orWhere(function ($periodQ) use ($rescheduledPeriodStart, $rescheduledPeriodEnd) {
                    $periodQ->where('period_start', '<=', $rescheduledPeriodEnd)
                        ->where('period_end', '>=', $rescheduledPeriodStart);
                });
            })
            ->whereHas('assignment', function ($q) use ($schoolYear, $semesterId, $holidayRombelIdsOnTargetDate) {
                $q->where('school_year_id', $schoolYear->id)
                    ->where('semester_id', $semesterId)
                    ->where('status', TeachingAssignment::STATUS_ACTIVE)
                    ->when(! empty($holidayRombelIdsOnTargetDate), fn ($rq) => $rq->whereNotIn('school_rombel_id', $holidayRombelIdsOnTargetDate));
            })
            ->get();

        foreach ($regularCandidates as $existing) {
            $existingAssignment = $existing->assignment;
            if (! $existingAssignment) {
                continue;
            }

            $existingClass = $existingAssignment->schoolRombel?->name ?? "#{$existingAssignment->school_rombel_id}";
            $existingSubject = $existingAssignment->subject?->name ?? "#{$existingAssignment->subject_id}";
            $existingTeacher = $existingAssignment->employee?->name ?? "#{$existingAssignment->employee_id}";
            $slotWindow = "Period {$existing->period_start}–{$existing->period_end} ({$existing->start_time}–{$existing->end_time})";

            if ((int) $existingAssignment->employee_id === $effectiveTeacherId) {
                throw ValidationException::withMessages([
                    'rescheduled_start_time' => [
                        "Reschedule teacher collision: {$existingTeacher} already has a regular schedule ({$existingSubject} in {$existingClass}) on {$rescheduledDate}, {$slotWindow}.",
                    ],
                ]);
            }

            if ((int) $existingAssignment->school_rombel_id === $rombelId) {
                throw ValidationException::withMessages([
                    'rescheduled_start_time' => [
                        "Reschedule class collision: Class {$existingClass} already has a regular schedule ({$existingSubject} with {$existingTeacher}) on {$rescheduledDate}, {$slotWindow}.",
                    ],
                ]);
            }

            if ($rescheduledRoomNormalized !== null && $existing->room_normalized === $rescheduledRoomNormalized) {
                throw ValidationException::withMessages([
                    'rescheduled_room' => [
                        "Reschedule room collision: Room {$rescheduledRoom} is already occupied by class {$existingClass} ({$existingSubject}) on {$rescheduledDate}, {$slotWindow}.",
                    ],
                ]);
            }
        }

        // 3. Check against other rescheduled exceptions targeting the same date
        $rescheduledCandidates = TeachingScheduleException::query()
            ->with(['schedule.assignment.schoolRombel', 'schedule.assignment.subject', 'schedule.assignment.employee', 'substituteEmployee'])
            ->where('school_year_id', $schoolYear->id)
            ->where('semester_id', $semesterId)
            ->where('exception_type', TeachingScheduleException::TYPE_RESCHEDULED)
            ->whereDate('rescheduled_date', $rescheduledDate)
            ->when($ignoreExceptionId !== null, fn ($q) => $q->whereKeyNot($ignoreExceptionId))
            ->where(function ($q) use ($rescheduledStartTime, $rescheduledEndTime, $rescheduledPeriodStart, $rescheduledPeriodEnd) {
                $q->where(function ($timeQ) use ($rescheduledStartTime, $rescheduledEndTime) {
                    $timeQ->where('rescheduled_start_time', '<', $rescheduledEndTime)
                        ->where('rescheduled_end_time', '>', $rescheduledStartTime);
                })->orWhere(function ($periodQ) use ($rescheduledPeriodStart, $rescheduledPeriodEnd) {
                    $periodQ->where('rescheduled_period_start', '<=', $rescheduledPeriodEnd)
                        ->where('rescheduled_period_end', '>=', $rescheduledPeriodStart);
                });
            })
            ->get();

        foreach ($rescheduledCandidates as $otherExc) {
            $otherAssignment = $otherExc->schedule?->assignment;
            if (! $otherAssignment) {
                continue;
            }

            $otherEffectiveTeacherId = $otherExc->substitute_employee_id !== null
                ? (int) $otherExc->substitute_employee_id
                : (int) $otherAssignment->employee_id;
            $otherRombelId = (int) $otherAssignment->school_rombel_id;
            $otherClass = $otherAssignment->schoolRombel?->name ?? "#{$otherRombelId}";
            $otherSubject = $otherAssignment->subject?->name ?? "#{$otherAssignment->subject_id}";
            $otherTeacher = $otherExc->substituteEmployee?->name
                ?? $otherAssignment->employee?->name
                ?? "#{$otherEffectiveTeacherId}";
            $slotWindow = "Period {$otherExc->rescheduled_period_start}–{$otherExc->rescheduled_period_end} ({$otherExc->rescheduled_start_time}–{$otherExc->rescheduled_end_time})";

            if ($otherEffectiveTeacherId === $effectiveTeacherId) {
                throw ValidationException::withMessages([
                    'rescheduled_start_time' => [
                        "Reschedule teacher collision: {$otherTeacher} is already assigned to another rescheduled session ({$otherSubject} in {$otherClass}) on {$rescheduledDate}, {$slotWindow}.",
                    ],
                ]);
            }

            if ($otherRombelId === $rombelId) {
                throw ValidationException::withMessages([
                    'rescheduled_start_time' => [
                        "Reschedule class collision: Class {$otherClass} already has another rescheduled session ({$otherSubject}) on {$rescheduledDate}, {$slotWindow}.",
                    ],
                ]);
            }

            if ($rescheduledRoomNormalized !== null && $otherExc->rescheduled_room_normalized === $rescheduledRoomNormalized) {
                throw ValidationException::withMessages([
                    'rescheduled_room' => [
                        "Reschedule room collision: Room {$rescheduledRoom} is already used by another rescheduled session ({$otherClass} - {$otherSubject}) on {$rescheduledDate}, {$slotWindow}.",
                    ],
                ]);
            }
        }
    }

    public function lockAndValidateWritableSchoolYear(int $schoolYearId): SchoolYear
    {
        $schoolYear = SchoolYear::withTrashed()
            ->whereKey($schoolYearId)
            ->lockForUpdate()
            ->first();

        if (! $schoolYear || $schoolYear->trashed()) {
            throw ValidationException::withMessages([
                'school_year_id' => ['The selected academic year is not available.'],
            ]);
        }

        if ($schoolYear->locked_at !== null) {
            throw ValidationException::withMessages([
                'school_year_id' => [
                    'The academic year is locked. Unlock it before modifying teaching assignments, schedules, or exceptions.',
                ],
            ]);
        }

        return $schoolYear;
    }

    private function validateSemester(int $semesterId): Semester
    {
        $semester = Semester::find($semesterId);
        if (! $semester) {
            throw ValidationException::withMessages([
                'semester_id' => ['The selected semester is not available.'],
            ]);
        }

        return $semester;
    }

    private function validateRombelForYear(int $rombelId, int $schoolYearId): SchoolRombel
    {
        $rombel = SchoolRombel::find($rombelId);
        if (! $rombel) {
            throw ValidationException::withMessages([
                'school_rombel_id' => ['The selected class is not available.'],
            ]);
        }

        if ((int) $rombel->school_year_id !== $schoolYearId) {
            throw ValidationException::withMessages([
                'school_rombel_id' => ['The selected class does not belong to the chosen academic year.'],
            ]);
        }

        return $rombel;
    }

    private function validateSubject(int $subjectId): Subject
    {
        $subject = Subject::find($subjectId);
        if (! $subject) {
            throw ValidationException::withMessages([
                'subject_id' => ['The selected subject is not available.'],
            ]);
        }

        return $subject;
    }

    private function validateEligibleTeacher(int $employeeId, string $field = 'employee_id'): Employee
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

    private function assertUniqueAssignment(
        int $schoolYearId,
        int $semesterId,
        int $rombelId,
        int $subjectId,
        int $employeeId,
        ?int $ignoreId = null
    ): void {
        $exists = TeachingAssignment::query()
            ->where('school_year_id', $schoolYearId)
            ->where('semester_id', $semesterId)
            ->where('school_rombel_id', $rombelId)
            ->where('subject_id', $subjectId)
            ->where('employee_id', $employeeId)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'employee_id' => [
                    'A teaching assignment for this teacher, subject, class, and academic period already exists.',
                ],
            ]);
        }
    }

    private function assertDateWithinSchoolYear(SchoolYear $schoolYear, string $date, string $field): void
    {
        $start = $schoolYear->start_date?->toDateString();
        $end = $schoolYear->end_date?->toDateString();

        if ($start !== null && $date < $start) {
            throw ValidationException::withMessages([
                $field => ["The date ({$date}) cannot be earlier than the school year start date ({$start})."],
            ]);
        }

        if ($end !== null && $date > $end) {
            throw ValidationException::withMessages([
                $field => ["The date ({$date}) cannot be later than the school year end date ({$end})."],
            ]);
        }
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) preg_replace('/\s+/', ' ', $value));

        return $trimmed === '' ? null : $trimmed;
    }

    public function loadAssignmentRelations(TeachingAssignment $assignment): TeachingAssignment
    {
        return $assignment->load([
            'schoolYear:id,name,start_date,end_date,locked_at',
            'semester:id,name,name_other',
            'schoolRombel:id,name,school_year_id,school_level_id,student_major_id',
            'schoolRombel.schoolLevel:id,name',
            'schoolRombel.studentMajor:id,name',
            'subject:id,name,description',
            'employee:id,name,nuptk,subject_id,employee_type_id,exit_date',
            'employee.subject:id,name',
        ])->loadCount([
            'schedules',
            'schedules as active_schedules_count' => fn ($q) => $q->where('status', TeachingSchedule::STATUS_ACTIVE),
        ]);
    }

    public function loadScheduleRelations(TeachingSchedule $schedule): TeachingSchedule
    {
        return $schedule->load([
            'assignment.schoolYear:id,name,start_date,end_date,locked_at',
            'assignment.semester:id,name,name_other',
            'assignment.schoolRombel:id,name,school_year_id,school_level_id,student_major_id',
            'assignment.schoolRombel.schoolLevel:id,name',
            'assignment.subject:id,name,description',
            'assignment.employee:id,name,nuptk,subject_id',
        ])->loadCount('exceptions');
    }

    public function loadExceptionRelations(TeachingScheduleException $exception): TeachingScheduleException
    {
        return $exception->load([
            'schoolYear:id,name,start_date,end_date,locked_at',
            'semester:id,name,name_other',
            'schoolRombel:id,name,school_year_id',
            'schedule.assignment.schoolRombel:id,name',
            'schedule.assignment.subject:id,name',
            'schedule.assignment.employee:id,name,nuptk',
            'substituteEmployee:id,name,nuptk',
        ]);
    }

    public function serializeAssignment(TeachingAssignment $assignment): array
    {
        return [
            'id' => (int) $assignment->id,
            'school_year_id' => (int) $assignment->school_year_id,
            'school_year' => $assignment->schoolYear ? [
                'id' => (int) $assignment->schoolYear->id,
                'name' => $assignment->schoolYear->name,
                'start_date' => $assignment->schoolYear->start_date?->toDateString(),
                'end_date' => $assignment->schoolYear->end_date?->toDateString(),
                'is_locked' => $assignment->schoolYear->locked_at !== null,
            ] : null,
            'semester_id' => (int) $assignment->semester_id,
            'semester' => $assignment->semester ? [
                'id' => (int) $assignment->semester->id,
                'name' => $assignment->semester->name,
                'name_other' => $assignment->semester->name_other,
            ] : null,
            'school_rombel_id' => (int) $assignment->school_rombel_id,
            'school_rombel' => $assignment->schoolRombel ? [
                'id' => (int) $assignment->schoolRombel->id,
                'name' => $assignment->schoolRombel->name,
                'school_level' => $assignment->schoolRombel->schoolLevel?->name,
                'student_major' => $assignment->schoolRombel->studentMajor?->name,
            ] : null,
            'subject_id' => (int) $assignment->subject_id,
            'subject' => $assignment->subject ? [
                'id' => (int) $assignment->subject->id,
                'name' => $assignment->subject->name,
                'description' => $assignment->subject->description,
            ] : null,
            'employee_id' => (int) $assignment->employee_id,
            'employee' => $assignment->employee ? [
                'id' => (int) $assignment->employee->id,
                'name' => $assignment->employee->name,
                'nuptk' => $assignment->employee->nuptk,
                'primary_subject_id' => $assignment->employee->subject_id ? (int) $assignment->employee->subject_id : null,
                'primary_subject_name' => $assignment->employee->subject?->name,
            ] : null,
            'hours_per_week' => $assignment->hours_per_week !== null ? (int) $assignment->hours_per_week : null,
            'status' => $assignment->status,
            'notes' => $assignment->notes,
            'schedules_count' => (int) ($assignment->schedules_count ?? 0),
            'active_schedules_count' => (int) ($assignment->active_schedules_count ?? 0),
            'version' => (int) $assignment->version,
            'created_at' => $assignment->created_at?->toIso8601String(),
            'updated_at' => $assignment->updated_at?->toIso8601String(),
        ];
    }

    public function serializeSchedule(TeachingSchedule $schedule): array
    {
        $assignment = $schedule->assignment;
        $dayLabel = config("teaching.days_of_week.{$schedule->day_of_week}", "Day {$schedule->day_of_week}");

        return [
            'id' => (int) $schedule->id,
            'teaching_assignment_id' => (int) $schedule->teaching_assignment_id,
            'day_of_week' => (int) $schedule->day_of_week,
            'day_label' => $dayLabel,
            'period_start' => (int) $schedule->period_start,
            'period_end' => (int) $schedule->period_end,
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'room' => $schedule->room,
            'status' => $schedule->status,
            'effective_active' => $schedule->status === TeachingSchedule::STATUS_ACTIVE
                && $assignment?->status === TeachingAssignment::STATUS_ACTIVE,
            'notes' => $schedule->notes,
            'exceptions_count' => (int) ($schedule->exceptions_count ?? 0),
            'version' => (int) $schedule->version,
            'assignment' => $assignment ? [
                'id' => (int) $assignment->id,
                'status' => $assignment->status,
                'hours_per_week' => $assignment->hours_per_week !== null ? (int) $assignment->hours_per_week : null,
                'school_year_id' => (int) $assignment->school_year_id,
                'school_year_name' => $assignment->schoolYear?->name,
                'is_year_locked' => $assignment->schoolYear?->locked_at !== null,
                'semester_id' => (int) $assignment->semester_id,
                'semester_name' => $assignment->semester?->name,
                'school_rombel_id' => (int) $assignment->school_rombel_id,
                'school_rombel_name' => $assignment->schoolRombel?->name,
                'subject_id' => (int) $assignment->subject_id,
                'subject_name' => $assignment->subject?->name,
                'employee_id' => (int) $assignment->employee_id,
                'employee_name' => $assignment->employee?->name,
                'employee_nuptk' => $assignment->employee?->nuptk,
            ] : null,
            'created_at' => $schedule->created_at?->toIso8601String(),
            'updated_at' => $schedule->updated_at?->toIso8601String(),
        ];
    }

    public function serializeException(TeachingScheduleException $exception): array
    {
        $schedule = $exception->schedule;
        $assignment = $schedule?->assignment;

        return [
            'id' => (int) $exception->id,
            'school_year_id' => (int) $exception->school_year_id,
            'school_year_name' => $exception->schoolYear?->name,
            'is_year_locked' => $exception->schoolYear?->locked_at !== null,
            'semester_id' => (int) $exception->semester_id,
            'semester_name' => $exception->semester?->name,
            'exception_type' => $exception->exception_type,
            'exception_type_label' => config("teaching.exception_types.{$exception->exception_type}", ucfirst($exception->exception_type)),
            'exception_date' => $exception->exception_date?->toDateString(),
            'school_rombel_id' => $exception->school_rombel_id !== null ? (int) $exception->school_rombel_id : null,
            'school_rombel_name' => $exception->schoolRombel?->name ?? $assignment?->schoolRombel?->name,
            'teaching_schedule_id' => $exception->teaching_schedule_id !== null ? (int) $exception->teaching_schedule_id : null,
            'schedule' => $schedule ? [
                'id' => (int) $schedule->id,
                'day_of_week' => (int) $schedule->day_of_week,
                'day_label' => config("teaching.days_of_week.{$schedule->day_of_week}", "Day {$schedule->day_of_week}"),
                'period_start' => (int) $schedule->period_start,
                'period_end' => (int) $schedule->period_end,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'room' => $schedule->room,
                'school_rombel_name' => $assignment?->schoolRombel?->name,
                'subject_name' => $assignment?->subject?->name,
                'employee_name' => $assignment?->employee?->name,
            ] : null,
            'rescheduled_date' => $exception->rescheduled_date?->toDateString(),
            'rescheduled_period_start' => $exception->rescheduled_period_start !== null ? (int) $exception->rescheduled_period_start : null,
            'rescheduled_period_end' => $exception->rescheduled_period_end !== null ? (int) $exception->rescheduled_period_end : null,
            'rescheduled_start_time' => $exception->rescheduled_start_time,
            'rescheduled_end_time' => $exception->rescheduled_end_time,
            'rescheduled_room' => $exception->rescheduled_room,
            'substitute_employee_id' => $exception->substitute_employee_id !== null ? (int) $exception->substitute_employee_id : null,
            'substitute_employee_name' => $exception->substituteEmployee?->name,
            'reason' => $exception->reason,
            'version' => (int) $exception->version,
            'created_at' => $exception->created_at?->toIso8601String(),
            'updated_at' => $exception->updated_at?->toIso8601String(),
        ];
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
