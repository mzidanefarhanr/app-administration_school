<?php

namespace App\Services\Academic;

use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentLockedCorrection;
use App\Models\StudentMutation;
use App\Models\StudentRombel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Koreksi periode terkunci (06c, DEC-31).
 *
 * Tidak ada edit/hapus: setiap tindakan menulis satu baris immutable
 * student_locked_corrections dengan snapshot before/after terbatas.
 */
class StudentLockedCorrectionService
{
    public function __construct(private readonly StudentMutationService $mutations) {}

    /**
     * @return array<string, mixed>
     */
    public function index(int $studentId): array
    {
        $student = Student::withTrashed()->whereKey($studentId)->first();
        if ($student === null) {
            abort(response()->json(['message' => 'Student not found.'], 404));
        }

        $items = StudentLockedCorrection::query()
            ->where('student_id', $student->id)
            ->with(['creator:id,name', 'schoolYear:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return [
            'student' => $this->mutations->serializeStudent($student),
            'items' => $items
                ->map(fn (StudentLockedCorrection $correction) => $this->mutations->serializeCorrection($correction))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function store(int $studentId, array $data, User $actor): array
    {
        $action = (string) $data['action'];
        $reason = trim((string) $data['reason']);
        $approvalReference = trim((string) ($data['approval_reference'] ?? ''));

        return DB::transaction(function () use ($studentId, $action, $reason, $approvalReference, $data, $actor) {
            $year = SchoolYear::query()
                ->whereKey((int) $data['school_year_id'])
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($year === null) {
                throw ValidationException::withMessages([
                    'school_year_id' => ['The selected academic year is not available.'],
                ]);
            }

            if ($year->locked_at === null) {
                throw ValidationException::withMessages([
                    'school_year_id' => ['Locked corrections are only available for a locked academic year.'],
                ]);
            }

            $this->mutations->assertYearHasDates($year);

            $student = Student::withTrashed()->whereKey($studentId)->lockForUpdate()->first();
            if ($student === null) {
                abort(response()->json(['message' => 'Student not found.'], 404));
            }

            $this->mutations->assertVersions($student, (int) $data['expected_student_version']);

            return match ($action) {
                StudentLockedCorrection::ACTION_MUTATION_IN,
                StudentLockedCorrection::ACTION_MUTATION_OUT => $this->correctMutation($student, $year, $data, $actor, $action, $reason, $approvalReference),
                StudentLockedCorrection::ACTION_MUTATION_CANCEL => $this->correctMutationCancel($student, $year, $data, $actor, $reason, $approvalReference),
                StudentLockedCorrection::ACTION_MEMBERSHIP_EXIT => $this->correctMembershipExit($student, $year, $data, $actor, $reason, $approvalReference),
                StudentLockedCorrection::ACTION_MEMBERSHIP_REOPEN => $this->correctMembershipReopen($student, $year, $data, $actor, $reason, $approvalReference),
            };
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function correctMutation(
        Student $student,
        SchoolYear $year,
        array $data,
        User $actor,
        string $action,
        string $reason,
        string $approvalReference
    ): array {
        $mutation = $this->lockMutation((int) ($data['mutation_id'] ?? 0), $student, $year);
        $expectedType = $action === StudentLockedCorrection::ACTION_MUTATION_IN
            ? StudentMutation::TYPE_IN
            : StudentMutation::TYPE_OUT;

        if ($mutation->type !== $expectedType) {
            throw ValidationException::withMessages([
                'mutation_id' => ['The mutation does not match the selected action.'],
            ]);
        }

        if ($mutation->status !== StudentMutation::STATUS_ACTIVE) {
            $this->conflict('mutation_id', 'Only active mutations can be corrected.');
        }

        [$membership, $class] = $this->lockMembershipForMutation($mutation, $student, $year);

        $this->mutations->assertVersions(
            $student,
            (int) $data['expected_student_version'],
            $membership,
            (int) $data['expected_membership_version'],
            $mutation,
            (int) $data['expected_mutation_version']
        );

        if ($expectedType === StudentMutation::TYPE_IN) {
            $inconsistent = $membership->archived_at !== null
                || $membership->deleted_at !== null
                || (int) $membership->school_rombel !== (int) $mutation->school_rombel_id
                || (int) $membership->student_entry_id !== StudentMutationService::ENTRY_MUTATION_IN
                || $this->mutations->dateString($membership->latest_student_entry_date) !== $this->mutations->dateString($mutation->effective_date)
                || (string) $membership->mutation_education_school_id !== (string) $mutation->school_npsn;

            if ($inconsistent) {
                $this->conflict('mutation_id', 'The class membership no longer matches the mutation-in state.');
            }
        } else {
            $this->mutations->assertMembershipMatchesMutationOut($membership, $mutation);
        }

        $oldDate = (string) $this->mutations->dateString($mutation->effective_date);
        $oldNpsn = (string) $mutation->school_npsn;
        $newDate = trim((string) ($data['effective_date'] ?? ''));
        $newDate = $newDate === '' ? $oldDate : $newDate;
        $newNpsn = trim((string) ($data['school_npsn'] ?? ''));
        $newNpsn = $newNpsn === '' ? $oldNpsn : $newNpsn;

        if ($newDate === $oldDate && $newNpsn === $oldNpsn) {
            throw ValidationException::withMessages([
                'effective_date' => ['Provide a new effective date or school to correct this mutation.'],
            ]);
        }

        $entryDate = $this->mutations->dateString($membership->latest_student_entry_date);
        $this->mutations->assertEffectiveDate(
            $year,
            $newDate,
            $expectedType === StudentMutation::TYPE_OUT ? $entryDate : null
        );

        if ($expectedType === StudentMutation::TYPE_IN) {
            $exitDate = $this->mutations->dateString($membership->latest_student_exit_date);
            if ($exitDate !== null && $newDate > $exitDate) {
                throw ValidationException::withMessages([
                    'effective_date' => ['The entry date cannot be later than the class membership exit date.'],
                ]);
            }
        }

        $this->mutations->assertSchoolNpsn($newNpsn);

        $before = [
            'mutation' => $this->mutations->mutationSnapshot($mutation),
            'membership' => $this->mutations->membershipSnapshot($membership),
        ];

        if ($expectedType === StudentMutation::TYPE_IN) {
            $membership->latest_student_entry_date = $newDate;
            $membership->mutation_education_school_id = $newNpsn;
        } else {
            $membership->latest_student_exit_date = $newDate;
            $membership->latest_student_exit_school_id = $newNpsn;
        }

        $membership->version = (int) $membership->version + 1;
        $membership->save();

        $mutation->effective_date = $newDate;
        $mutation->school_npsn = $newNpsn;
        $mutation->version = (int) $mutation->version + 1;
        $mutation->save();

        $student->version = (int) $student->version + 1;
        $student->save();

        $after = [
            'mutation' => $this->mutations->mutationSnapshot($mutation->fresh()),
            'membership' => $this->mutations->membershipSnapshot($membership->fresh()),
        ];

        $correction = $this->record(
            $student,
            $year,
            $action,
            StudentLockedCorrection::TARGET_MUTATION,
            (int) $mutation->id,
            $mutation,
            $membership,
            $before,
            $after,
            $reason,
            $approvalReference,
            $actor
        );

        return $this->result($student, $membership, $mutation, $correction);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function correctMutationCancel(
        Student $student,
        SchoolYear $year,
        array $data,
        User $actor,
        string $reason,
        string $approvalReference
    ): array {
        $mutation = $this->lockMutation((int) ($data['mutation_id'] ?? 0), $student, $year);

        if ($mutation->status !== StudentMutation::STATUS_ACTIVE) {
            $this->conflict('mutation_id', 'Only active mutations can be cancelled.');
        }

        $result = $this->mutations->cancelWithinTransaction($student, $mutation, [
            'expected_student_version' => (int) $data['expected_student_version'],
            'expected_membership_version' => (int) $data['expected_membership_version'],
            'expected_mutation_version' => (int) $data['expected_mutation_version'],
            'reason' => $reason,
        ], $actor);

        $correction = $this->record(
            $result['student'],
            $year,
            StudentLockedCorrection::ACTION_MUTATION_CANCEL,
            StudentLockedCorrection::TARGET_MUTATION,
            (int) $result['mutation']->id,
            $result['mutation'],
            $result['membership'],
            $result['before'],
            $result['after'],
            $reason,
            $approvalReference,
            $actor
        );

        return $this->result($result['student'], $result['membership'], $result['mutation'], $correction);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function correctMembershipExit(
        Student $student,
        SchoolYear $year,
        array $data,
        User $actor,
        string $reason,
        string $approvalReference
    ): array {
        [$membership, $class] = $this->lockMembershipTarget((int) ($data['student_rombel_id'] ?? 0), $student, $year);

        $this->mutations->assertVersions(
            $student,
            (int) $data['expected_student_version'],
            $membership,
            (int) $data['expected_membership_version']
        );

        if ($membership->archived_at !== null || $membership->deleted_at !== null || ! $this->mutations->membershipIsActive($membership)) {
            $this->conflict('student_rombel_id', 'Only an active class membership can be exited.');
        }

        $this->assertNoActiveMutation($membership);

        $targetStatus = (int) ($data['target_status_id'] ?? 0);
        if (! in_array($targetStatus, [StudentMutationService::STATUS_MUTATION_OUT, StudentMutationService::STATUS_GRADUATED], true)) {
            throw ValidationException::withMessages([
                'target_status_id' => ['The target status must be Mutation Out or Graduated.'],
            ]);
        }

        $effectiveDate = (string) $data['effective_date'];
        $this->mutations->assertEffectiveDate(
            $year,
            $effectiveDate,
            $this->mutations->dateString($membership->latest_student_entry_date)
        );

        $schoolNpsn = trim((string) ($data['school_npsn'] ?? ''));
        if ($schoolNpsn !== '') {
            $this->mutations->assertSchoolNpsn($schoolNpsn);
        }

        $before = ['membership' => $this->mutations->membershipSnapshot($membership)];

        $membership->student_status_id = $targetStatus;
        $membership->latest_student_exit_date = $effectiveDate;
        $membership->latest_student_exit_school_id = $schoolNpsn !== '' ? $schoolNpsn : null;
        $membership->latest_student_graduation_date = $targetStatus === StudentMutationService::STATUS_GRADUATED
            ? $effectiveDate
            : null;
        $membership->version = (int) $membership->version + 1;
        $membership->save();

        $student->version = (int) $student->version + 1;
        $student->save();

        $after = ['membership' => $this->mutations->membershipSnapshot($membership->fresh())];

        $correction = $this->record(
            $student,
            $year,
            StudentLockedCorrection::ACTION_MEMBERSHIP_EXIT,
            StudentLockedCorrection::TARGET_MEMBERSHIP,
            (int) $membership->id,
            null,
            $membership,
            $before,
            $after,
            $reason,
            $approvalReference,
            $actor
        );

        return $this->result($student, $membership, null, $correction);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function correctMembershipReopen(
        Student $student,
        SchoolYear $year,
        array $data,
        User $actor,
        string $reason,
        string $approvalReference
    ): array {
        [$membership, $class] = $this->lockMembershipTarget((int) ($data['student_rombel_id'] ?? 0), $student, $year);

        $this->mutations->assertVersions(
            $student,
            (int) $data['expected_student_version'],
            $membership,
            (int) $data['expected_membership_version']
        );

        if ($student->trashed()) {
            throw ValidationException::withMessages([
                'student_id' => ['Cannot reopen a class membership for an archived student.'],
            ]);
        }

        if ($membership->archived_at !== null || $membership->deleted_at !== null
            || ! in_array((int) $membership->student_status_id, [StudentMutationService::STATUS_MUTATION_OUT, StudentMutationService::STATUS_GRADUATED], true)) {
            $this->conflict('student_rombel_id', 'Only an exited or graduated class membership can be reopened.');
        }

        $this->assertNoActiveMutation($membership);
        $this->assertNoOtherActiveMembership($membership, $student, $year);

        $before = ['membership' => $this->mutations->membershipSnapshot($membership)];

        $membership->student_status_id = StudentMutationService::STATUS_ACTIVE;
        $membership->latest_student_exit_date = null;
        $membership->latest_student_exit_school_id = null;
        $membership->latest_student_graduation_date = null;
        $membership->version = (int) $membership->version + 1;
        $membership->save();

        $student->version = (int) $student->version + 1;
        $student->save();

        $after = ['membership' => $this->mutations->membershipSnapshot($membership->fresh())];

        $correction = $this->record(
            $student,
            $year,
            StudentLockedCorrection::ACTION_MEMBERSHIP_REOPEN,
            StudentLockedCorrection::TARGET_MEMBERSHIP,
            (int) $membership->id,
            null,
            $membership,
            $before,
            $after,
            $reason,
            $approvalReference,
            $actor
        );

        return $this->result($student, $membership, null, $correction);
    }

    private function lockMutation(int $mutationId, Student $student, SchoolYear $year): StudentMutation
    {
        $mutation = StudentMutation::query()->whereKey($mutationId)->lockForUpdate()->first();

        if ($mutation === null || (int) $mutation->student_id !== (int) $student->id) {
            abort(response()->json(['message' => 'Mutation not found for this student.'], 404));
        }

        if ((int) $mutation->school_year_id !== (int) $year->id) {
            throw ValidationException::withMessages([
                'mutation_id' => ['The mutation does not belong to the selected academic year.'],
            ]);
        }

        return $mutation;
    }

    /**
     * @return array{0: StudentRombel, 1: SchoolRombel}
     */
    private function lockMembershipForMutation(StudentMutation $mutation, Student $student, SchoolYear $year): array
    {
        $membershipId = (int) ($mutation->student_rombel_id ?? 0);
        if ($membershipId < 1) {
            $this->conflict('mutation_id', 'The class membership linked to this mutation is missing.');
        }

        return $this->lockMembershipTarget($membershipId, $student, $year);
    }

    /**
     * @return array{0: StudentRombel, 1: SchoolRombel}
     */
    private function lockMembershipTarget(int $membershipId, Student $student, SchoolYear $year): array
    {
        $membership = StudentRombel::query()->whereKey($membershipId)->lockForUpdate()->first();

        if ($membership === null || ! $this->mutations->membershipBelongsToStudent($membership, $student)) {
            throw ValidationException::withMessages([
                'student_rombel_id' => ['The selected class membership does not belong to this student.'],
            ]);
        }

        $class = SchoolRombel::query()->whereKey($membership->school_rombel)->lockForUpdate()->first();

        if ($class === null || $class->deleted_at !== null || (int) $class->school_year_id !== (int) $year->id) {
            throw ValidationException::withMessages([
                'student_rombel_id' => ['The selected class membership is not available in the selected academic year.'],
            ]);
        }

        return [$membership, $class];
    }

    private function assertNoActiveMutation(StudentRombel $membership): void
    {
        $exists = StudentMutation::query()
            ->where('student_rombel_id', $membership->id)
            ->where('status', StudentMutation::STATUS_ACTIVE)
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            $this->conflict('student_rombel_id', 'Cancel the active mutation for this class membership first.');
        }
    }

    private function assertNoOtherActiveMembership(StudentRombel $membership, Student $student, SchoolYear $year): void
    {
        $exists = StudentRombel::query()
            ->whereKeyNot($membership->id)
            ->whereHas('schoolRombel', fn ($query) => $query->where('school_year_id', $year->id))
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->where(fn ($query) => $query->where('student_status_id', StudentMutationService::STATUS_ACTIVE)->orWhereNull('student_status_id'))
            ->whereNull('latest_student_exit_date')
            ->where(function ($query) use ($student, $membership) {
                $query->where('student_id', $student->id);
                if (! empty($membership->student_nik)) {
                    $query->orWhere(fn ($sub) => $sub->whereNull('student_id')->where('student_nik', $membership->student_nik));
                }
            })
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            $this->conflict('student_rombel_id', 'Another active class membership exists in this academic year.');
        }
    }

    private function record(
        Student $student,
        SchoolYear $year,
        string $action,
        string $targetType,
        int $targetId,
        ?StudentMutation $mutation,
        ?StudentRombel $membership,
        array $before,
        array $after,
        string $reason,
        string $approvalReference,
        User $actor
    ): StudentLockedCorrection {
        return StudentLockedCorrection::create([
            'school_year_id' => $year->id,
            'student_id' => $student->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'mutation_id' => $mutation?->id,
            'student_rombel_id' => $membership?->id,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'approval_reference' => $approvalReference !== '' ? $approvalReference : null,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function result(Student $student, ?StudentRombel $membership, ?StudentMutation $mutation, StudentLockedCorrection $correction): array
    {
        $student = $student->fresh();
        $membership = $membership?->fresh();
        $mutation = $mutation?->fresh();

        return [
            'student_version' => (int) $student->version,
            'membership' => $membership === null ? null : $this->mutations->serializeMembership($membership),
            'mutation' => $mutation === null ? null : $this->mutations->serializeMutation($mutation),
            'correction' => $this->mutations->serializeCorrection($correction),
        ];
    }

    private function conflict(string $field, string $message): never
    {
        abort(response()->json([
            'message' => 'Conflict: '.$message,
            'errors' => [$field => [$message]],
        ], 409));
    }
}
