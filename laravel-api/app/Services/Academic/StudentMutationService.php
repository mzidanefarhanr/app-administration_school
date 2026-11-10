<?php

namespace App\Services\Academic;

use App\Models\AcademicSetting;
use App\Models\EducationSchool;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEntry;
use App\Models\StudentLockedCorrection;
use App\Models\StudentMutation;
use App\Models\StudentProgressionBatch;
use App\Models\StudentRombel;
use App\Models\StudentStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mutasi masuk/keluar, pembatalan kompensatif, dan rekap per tahun (06c, DEC-31).
 *
 * Preview tidak ada: setiap operasi tunggal divalidasi dan diterapkan dalam satu
 * transaksi dengan urutan lock tahun → siswa → membership → kelas → log mutasi.
 */
class StudentMutationService
{
    public const ENTRY_MUTATION_IN = 2;

    public const STATUS_ACTIVE = 1;

    public const STATUS_MUTATION_OUT = 2;

    public const STATUS_GRADUATED = 6;

    public const MAX_SEARCH_RESULTS = 20;

    public const PER_PAGE = 10;

    public const CANCEL_IN_ARCHIVE_REASON = 'Cancelled mutation-in';

    /**
     * @return array<string, mixed>
     */
    public function options(?int $schoolYearId): array
    {
        $years = SchoolYear::query()
            ->whereNull('deleted_at')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $activeYearId = AcademicSetting::find(AcademicSetting::SINGLETON_ID)?->active_school_year_id;

        $selectedYear = null;
        if ($schoolYearId !== null) {
            $selectedYear = $years->firstWhere('id', $schoolYearId);
            if ($selectedYear === null) {
                throw ValidationException::withMessages([
                    'school_year_id' => ['The selected academic year is not available.'],
                ]);
            }
        } else {
            $selectedYear = $years->firstWhere('id', $activeYearId) ?? $years->first();
        }

        return [
            'today' => $this->today(),
            'active_school_year_id' => $activeYearId !== null ? (int) $activeYearId : null,
            'max_search_results' => self::MAX_SEARCH_RESULTS,
            'selected_year_id' => $selectedYear?->id,
            'years' => $years
                ->map(fn (SchoolYear $year) => $this->serializeYear($year, $activeYearId))
                ->values()
                ->all(),
            'classes' => $selectedYear === null ? [] : $this->serializeClasses($selectedYear->id),
            'references' => [
                'entry_mutation_in_id' => self::ENTRY_MUTATION_IN,
                'status_active_id' => self::STATUS_ACTIVE,
                'status_mutation_out_id' => self::STATUS_MUTATION_OUT,
                'status_graduated_id' => self::STATUS_GRADUATED,
                'entries' => StudentEntry::query()->orderBy('id')->get(['id', 'name'])->toArray(),
                'statuses' => StudentStatus::query()->orderBy('id')->get(['id', 'name'])->toArray(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function schools(?string $search): array
    {
        $term = trim((string) $search);

        $query = EducationSchool::query()->whereNull('deleted_at');

        if ($term !== '') {
            $query->where(function ($sub) use ($term) {
                $sub->where('npsn', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%");
            });
        }

        return $query
            ->orderBy('name')
            ->orderBy('npsn')
            ->limit(self::MAX_SEARCH_RESULTS)
            ->get(['id', 'npsn', 'name', 'status_education', 'education_level_id'])
            ->map(fn (EducationSchool $school) => [
                'npsn' => $school->npsn,
                'name' => $school->name,
                'status_education' => $school->status_education,
                'education_level_id' => $school->education_level_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{school_year_id: int, type?: string|null, status?: string|null, search?: string|null, page?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function history(array $filters): array
    {
        $year = $this->findYearForRead((int) $filters['school_year_id']);
        $activeYearId = AcademicSetting::find(AcademicSetting::SINGLETON_ID)?->active_school_year_id;

        $query = StudentMutation::query()
            ->where('school_year_id', $year->id)
            ->with([
                'student:id,name,nis,version',
                'schoolRombel:id,name',
                'studentRombel:id,student_status_id,archived_at,deleted_at,version',
                'creator:id,name',
                'canceller:id,name',
                'school:id,npsn,name,deleted_at',
            ]);

        $type = $filters['type'] ?? null;
        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->whereHas('student', function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? self::PER_PAGE);
        if (! in_array($perPage, [10, 20, 25, 50, 100], true)) {
            $perPage = self::PER_PAGE;
        }

        $paginator = $query
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return [
            'year' => $this->serializeYear($year, $activeYearId),
            'items' => collect($paginator->items())
                ->map(fn (StudentMutation $mutation) => $this->serializeMutation($mutation))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'filters' => [
                'type' => $type,
                'status' => $status,
                'search' => $search,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recap(int $schoolYearId): array
    {
        $year = $this->findYearForRead($schoolYearId);
        $this->assertYearHasDates($year);

        $start = $this->dateString($year->start_date);
        $end = $this->dateString($year->end_date);

        $mutationIn = StudentMutation::query()
            ->where('school_year_id', $year->id)
            ->where('type', StudentMutation::TYPE_IN)
            ->where('status', StudentMutation::STATUS_ACTIVE)
            ->whereBetween('effective_date', [$start, $end])
            ->count();

        $mutationOut = StudentMutation::query()
            ->where('school_year_id', $year->id)
            ->where('type', StudentMutation::TYPE_OUT)
            ->where('status', StudentMutation::STATUS_ACTIVE)
            ->whereBetween('effective_date', [$start, $end])
            ->count();

        $graduated = StudentRombel::query()
            ->whereNull('deleted_at')
            ->where('student_status_id', self::STATUS_GRADUATED)
            ->whereBetween('latest_student_graduation_date', [$start, $end])
            ->distinct()
            ->count('student_id');

        $retained = 0;
        $seen = [];

        $batches = StudentProgressionBatch::query()
            ->where('source_school_year_id', $year->id)
            ->where('status', StudentProgressionBatch::STATUS_COMMITTED)
            ->orderByDesc('committed_at')
            ->orderByDesc('id')
            ->get(['id', 'decisions']);

        foreach ($batches as $batch) {
            foreach ((array) $batch->decisions as $decision) {
                $studentId = (int) ($decision['student_id'] ?? 0);
                if ($studentId < 1 || isset($seen[$studentId])) {
                    continue;
                }

                $seen[$studentId] = true;
                if (($decision['decision'] ?? null) === 'retain') {
                    $retained++;
                }
            }
        }

        $activeYearId = AcademicSetting::find(AcademicSetting::SINGLETON_ID)?->active_school_year_id;

        return [
            'year' => $this->serializeYear($year, $activeYearId),
            'period' => ['start_date' => $start, 'end_date' => $end],
            'mutation_in' => $mutationIn,
            'mutation_out' => $mutationOut,
            'graduated' => $graduated,
            'retained' => $retained,
            'note' => 'Year-level recap. Semester recap is not available because semesters have no dates.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function timeline(int $studentId): array
    {
        $student = Student::withTrashed()->whereKey($studentId)->first();
        if ($student === null) {
            $this->notFound('Student not found.');
        }

        $mutations = StudentMutation::query()
            ->where('student_id', $student->id)
            ->with(['schoolRombel:id,name', 'studentRombel:id,student_status_id,archived_at,deleted_at,version', 'creator:id,name', 'canceller:id,name', 'school:id,npsn,name,deleted_at', 'schoolYear:id,name'])
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get();

        $corrections = StudentLockedCorrection::query()
            ->where('student_id', $student->id)
            ->with(['creator:id,name', 'schoolYear:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return [
            'student' => $this->serializeStudent($student),
            'mutations' => $mutations
                ->map(fn (StudentMutation $mutation) => $this->serializeMutation($mutation))
                ->values()
                ->all(),
            'corrections' => $corrections
                ->map(fn (StudentLockedCorrection $correction) => $this->serializeCorrection($correction))
                ->values()
                ->all(),
        ];
    }

    /**
     * Catat mutasi masuk: buat membership baru tanpa mengubah biodata siswa.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutationIn(int $studentId, array $data, User $actor): array
    {
        $effectiveDate = (string) $data['effective_date'];
        $schoolYearId = (int) $data['school_year_id'];
        $rombelId = (int) $data['school_rombel_id'];
        $schoolNpsn = trim((string) $data['school_npsn']);
        $reason = trim((string) $data['reason']);
        $expectedStudentVersion = (int) $data['expected_student_version'];

        try {
            return DB::transaction(function () use (
                $studentId,
                $schoolYearId,
                $rombelId,
                $effectiveDate,
                $schoolNpsn,
                $reason,
                $expectedStudentVersion,
                $actor
            ) {
                $year = $this->lockYear($schoolYearId, 'school_year_id');
                $this->assertYearUnlocked($year, 'The selected academic year is locked. Unlock it before recording mutations.');
                $this->assertYearHasDates($year);

                $student = Student::withTrashed()->whereKey($studentId)->lockForUpdate()->first();
                if ($student === null) {
                    $this->notFound('Student not found.');
                }

                if ($student->trashed()) {
                    throw ValidationException::withMessages([
                        'student_id' => ['Cannot record a mutation-in for an archived student.'],
                    ]);
                }

                $this->assertVersions($student, $expectedStudentVersion);

                $memberships = $this->lockStudentYearMemberships($student, (int) $year->id);
                $this->assertNoActiveMembership($memberships);

                $this->assertEffectiveDate($year, $effectiveDate);
                $latest = $memberships
                    ->sortByDesc(fn (StudentRombel $membership) => sprintf(
                        '%s-%020d',
                        $this->dateString($membership->latest_student_entry_date) ?? '0000-01-01',
                        (int) $membership->id
                    ))
                    ->first();

                if ($latest !== null) {
                    $exitDate = $this->dateString($latest->latest_student_exit_date);
                    if ($exitDate === null) {
                        $this->conflict(
                            'student_id',
                            'The latest class membership in this academic year is not closed with an exit date.',
                            ['student_version' => (int) $student->version]
                        );
                    }

                    if ($exitDate > $effectiveDate) {
                        $this->conflict(
                            'student_id',
                            'The latest class membership exits after the requested effective date.',
                            ['student_version' => (int) $student->version]
                        );
                    }
                }

                $targetClass = SchoolRombel::whereKey($rombelId)
                    ->where('school_year_id', $year->id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if ($targetClass === null) {
                    throw ValidationException::withMessages([
                        'school_rombel_id' => ['The selected class is not available in the selected academic year.'],
                    ]);
                }

                $this->assertSchoolNpsn($schoolNpsn);

                $membership = StudentRombel::create([
                    'version' => 1,
                    'student_id' => $student->id,
                    'student_nik' => $student->nik,
                    'school_rombel' => $targetClass->id,
                    'student_status_id' => self::STATUS_ACTIVE,
                    'student_entry_id' => self::ENTRY_MUTATION_IN,
                    'latest_student_entry_date' => $effectiveDate,
                    'mutation_education_school_id' => $schoolNpsn,
                ]);

                $mutation = StudentMutation::create([
                    'version' => 1,
                    'type' => StudentMutation::TYPE_IN,
                    'school_year_id' => $year->id,
                    'student_id' => $student->id,
                    'student_rombel_id' => $membership->id,
                    'school_rombel_id' => $targetClass->id,
                    'effective_date' => $effectiveDate,
                    'school_npsn' => $schoolNpsn,
                    'reason' => $reason,
                    'status' => StudentMutation::STATUS_ACTIVE,
                    'created_by' => $actor->id,
                ]);

                $student->version = (int) $student->version + 1;
                $student->save();

                $membership->load('schoolRombel:id,name');
                $mutation->load(['student:id,name,nis,version', 'schoolRombel:id,name', 'studentRombel:id,student_status_id,archived_at,deleted_at,version', 'school:id,npsn,name,deleted_at', 'creator:id,name']);

                return [
                    'student_version' => (int) $student->version,
                    'membership' => $this->serializeMembership($membership),
                    'mutation' => $this->serializeMutation($mutation),
                ];
            });
        } catch (\DomainException $e) {
            $this->conflict('student_id', $this->translateMembershipDomainMessage($e->getMessage()));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == '45000' || str_contains($e->getMessage(), 'already has an active rombel')
                || str_contains($e->getMessage(), 'archived student')) {
                $this->conflict('student_id', $this->translateMembershipDomainMessage($e->getMessage()));
            }

            throw $e;
        }
    }

    /**
     * Catat mutasi keluar: hanya menutup satu membership terpilih.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutationOut(int $studentId, array $data, User $actor): array
    {
        $membershipId = (int) $data['student_rombel_id'];
        $effectiveDate = (string) $data['effective_date'];
        $schoolNpsn = trim((string) $data['school_npsn']);
        $reason = trim((string) $data['reason']);
        $expectedStudentVersion = (int) $data['expected_student_version'];
        $expectedMembershipVersion = (int) $data['expected_membership_version'];

        $probe = StudentRombel::query()->with('schoolRombel:id,school_year_id')->whereKey($membershipId)->first();
        if ($probe === null || $probe->schoolRombel === null) {
            throw ValidationException::withMessages([
                'student_rombel_id' => ['The selected class membership is not available.'],
            ]);
        }

        $schoolYearId = (int) $probe->schoolRombel->school_year_id;

        try {
            return DB::transaction(function () use (
                $studentId,
                $membershipId,
                $schoolYearId,
                $effectiveDate,
                $schoolNpsn,
                $reason,
                $expectedStudentVersion,
                $expectedMembershipVersion,
                $actor
            ) {
                $year = $this->lockYear($schoolYearId, 'student_rombel_id');
                $this->assertYearUnlocked($year, 'The selected academic year is locked. Unlock it before recording mutations.');
                $this->assertYearHasDates($year);

                $student = Student::withTrashed()->whereKey($studentId)->lockForUpdate()->first();
                if ($student === null) {
                    $this->notFound('Student not found.');
                }

                if ($student->trashed()) {
                    throw ValidationException::withMessages([
                        'student_id' => ['Cannot record a mutation-out for an archived student.'],
                    ]);
                }

                $membership = StudentRombel::whereKey($membershipId)->lockForUpdate()->first();
                if ($membership === null || ! $this->membershipBelongsToStudent($membership, $student)) {
                    throw ValidationException::withMessages([
                        'student_rombel_id' => ['The selected class membership does not belong to this student.'],
                    ]);
                }

                $class = SchoolRombel::whereKey($membership->school_rombel)->lockForUpdate()->first();
                if ($class === null || $class->deleted_at !== null || (int) $class->school_year_id !== (int) $year->id) {
                    throw ValidationException::withMessages([
                        'student_rombel_id' => ['The selected class membership is not available in the selected academic year.'],
                    ]);
                }

                $this->assertVersions($student, $expectedStudentVersion, $membership, $expectedMembershipVersion);

                if ($membership->archived_at !== null || $membership->deleted_at !== null || ! $this->membershipIsActive($membership)) {
                    $this->conflict(
                        'student_rombel_id',
                        'The selected class membership is not active.',
                        ['membership_version' => (int) $membership->version]
                    );
                }

                $this->assertEffectiveDate($year, $effectiveDate, $this->dateString($membership->latest_student_entry_date));
                $this->assertSchoolNpsn($schoolNpsn);

                $activeOut = StudentMutation::query()
                    ->where('student_rombel_id', $membership->id)
                    ->where('type', StudentMutation::TYPE_OUT)
                    ->where('status', StudentMutation::STATUS_ACTIVE)
                    ->lockForUpdate()
                    ->exists();

                if ($activeOut) {
                    $this->conflict('student_rombel_id', 'An active mutation-out already exists for this class membership.');
                }

                $membership->student_status_id = self::STATUS_MUTATION_OUT;
                $membership->latest_student_exit_date = $effectiveDate;
                $membership->latest_student_exit_school_id = $schoolNpsn;
                $membership->version = (int) $membership->version + 1;
                $membership->save();

                $mutation = StudentMutation::create([
                    'version' => 1,
                    'type' => StudentMutation::TYPE_OUT,
                    'school_year_id' => $year->id,
                    'student_id' => $student->id,
                    'student_rombel_id' => $membership->id,
                    'school_rombel_id' => $class->id,
                    'effective_date' => $effectiveDate,
                    'school_npsn' => $schoolNpsn,
                    'reason' => $reason,
                    'status' => StudentMutation::STATUS_ACTIVE,
                    'created_by' => $actor->id,
                ]);

                $student->version = (int) $student->version + 1;
                $student->save();

                $membership->load('schoolRombel:id,name');
                $mutation->load(['student:id,name,nis,version', 'schoolRombel:id,name', 'studentRombel:id,student_status_id,archived_at,deleted_at,version', 'school:id,npsn,name,deleted_at', 'creator:id,name']);

                return [
                    'student_version' => (int) $student->version,
                    'membership' => $this->serializeMembership($membership),
                    'mutation' => $this->serializeMutation($mutation),
                ];
            });
        } catch (\DomainException $e) {
            $this->conflict('student_rombel_id', $this->translateMembershipDomainMessage($e->getMessage()));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == '45000' || str_contains($e->getMessage(), 'already has an active rombel')
                || str_contains($e->getMessage(), 'archived student')) {
                $this->conflict('student_rombel_id', $this->translateMembershipDomainMessage($e->getMessage()));
            }

            throw $e;
        }
    }

    /**
     * Batalkan mutasi secara kompensatif (bukan menghapus log).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function cancel(int $studentId, int $mutationId, array $data, User $actor): array
    {
        $probe = StudentMutation::query()->whereKey($mutationId)->first();
        if ($probe === null || (int) $probe->student_id !== $studentId) {
            $this->notFound('Mutation not found for this student.');
        }

        return DB::transaction(function () use ($studentId, $probe, $data, $actor) {
            $year = $this->lockYear((int) $probe->school_year_id, 'mutation_id');

            if ($year->locked_at !== null) {
                throw ValidationException::withMessages([
                    'mutation_id' => ['The selected academic year is locked. Locked mutations must be corrected through locked corrections.'],
                ]);
            }

            $student = Student::withTrashed()->whereKey($studentId)->lockForUpdate()->first();
            if ($student === null) {
                $this->notFound('Student not found.');
            }

            $hasCorrections = StudentLockedCorrection::query()
                ->where('mutation_id', $probe->id)
                ->exists();

            if ($hasCorrections && ! $actor->can('students.correct-locked')) {
                abort(response()->json([
                    'message' => 'This mutation was corrected in a locked period and can only be cancelled by a user with the locked correction ability.',
                ], 403));
            }

            $result = $this->cancelWithinTransaction($student, $probe, $data, $actor);

            return [
                'student_version' => (int) $result['student']->version,
                'membership' => $this->serializeMembership($result['membership']),
                'mutation' => $this->serializeMutation($result['mutation']),
                'consequence' => $result['consequence'],
            ];
        });
    }

    /**
     * Inti pembatalan kompensatif; pemanggil harus sudah membuka transaksi dan
     * mengunci baris tahun/siswa sebelum memanggil metode ini.
     *
     * @param  array<string, mixed>  $data
     * @return array{student: Student, membership: StudentRombel, mutation: StudentMutation, before: array<string, mixed>, after: array<string, mixed>, consequence: string}
     */
    public function cancelWithinTransaction(Student $student, StudentMutation $mutation, array $data, User $actor): array
    {
        $membershipId = (int) ($mutation->student_rombel_id ?? 0);
        $membership = $membershipId > 0
            ? StudentRombel::whereKey($membershipId)->lockForUpdate()->first()
            : null;

        if ($membership === null) {
            $this->conflict('mutation_id', 'The class membership linked to this mutation is missing.');
        }

        if (! $this->membershipBelongsToStudent($membership, $student)) {
            $this->conflict('mutation_id', 'The class membership linked to this mutation does not belong to this student.');
        }

        $class = SchoolRombel::whereKey($mutation->school_rombel_id)->lockForUpdate()->first();
        if ($class === null || $class->deleted_at !== null) {
            $this->conflict('mutation_id', 'The class linked to this mutation is not available.');
        }

        $lockedMutation = StudentMutation::whereKey($mutation->id)->lockForUpdate()->first();
        if ($lockedMutation === null) {
            $this->notFound('Mutation not found.');
        }

        if ($lockedMutation->status === StudentMutation::STATUS_CANCELLED) {
            $this->conflict('expected_mutation_version', 'This mutation has already been cancelled.');
        }

        $this->assertVersions(
            $student,
            (int) $data['expected_student_version'],
            $membership,
            (int) $data['expected_membership_version'],
            $lockedMutation,
            (int) $data['expected_mutation_version']
        );

        if ($lockedMutation->type === StudentMutation::TYPE_OUT && $student->trashed()) {
            throw ValidationException::withMessages([
                'student_id' => ['Cannot reopen a class membership for an archived student.'],
            ]);
        }

        $before = [
            'mutation' => $this->mutationSnapshot($lockedMutation),
            'membership' => $this->membershipSnapshot($membership),
        ];

        if ($lockedMutation->type === StudentMutation::TYPE_IN) {
            $consequence = 'archived';
            $this->assertMembershipMatchesMutationIn($membership, $lockedMutation);

            $activeOut = StudentMutation::query()
                ->where('student_rombel_id', $membership->id)
                ->where('type', StudentMutation::TYPE_OUT)
                ->where('status', StudentMutation::STATUS_ACTIVE)
                ->whereKeyNot($lockedMutation->id)
                ->lockForUpdate()
                ->exists();

            if ($activeOut) {
                $this->conflict('mutation_id', 'This class membership already has an active mutation-out.');
            }

            $membership->archived_at = Carbon::now('Asia/Jakarta');
            $membership->archive_reason = self::CANCEL_IN_ARCHIVE_REASON;
            $membership->version = (int) $membership->version + 1;
            $membership->save();
        } else {
            $consequence = 'restored';
            $this->assertMembershipMatchesMutationOut($membership, $lockedMutation);

            $otherActive = StudentRombel::query()
                ->whereKeyNot($membership->id)
                ->whereHas('schoolRombel', fn ($q) => $q->where('school_year_id', $lockedMutation->school_year_id))
                ->whereNull('deleted_at')
                ->whereNull('archived_at')
                ->where(fn ($q) => $q->where('student_status_id', self::STATUS_ACTIVE)->orWhereNull('student_status_id'))
                ->whereNull('latest_student_exit_date')
                ->where(function ($query) use ($student, $membership) {
                    $query->where('student_id', $student->id);
                    if (! empty($membership->student_nik)) {
                        $query->orWhere(fn ($sub) => $sub->whereNull('student_id')->where('student_nik', $membership->student_nik));
                    }
                })
                ->lockForUpdate()
                ->exists();

            if ($otherActive) {
                $this->conflict('student_rombel_id', 'Another active class membership exists in this academic year.');
            }

            $membership->student_status_id = self::STATUS_ACTIVE;
            $membership->latest_student_exit_date = null;
            $membership->latest_student_exit_school_id = null;
            $membership->latest_student_graduation_date = null;
            $membership->version = (int) $membership->version + 1;
            $membership->save();
        }

        $lockedMutation->status = StudentMutation::STATUS_CANCELLED;
        $lockedMutation->cancelled_at = Carbon::now('Asia/Jakarta');
        $lockedMutation->cancelled_by = $actor->id;
        $lockedMutation->cancel_reason = trim((string) $data['reason']);
        $lockedMutation->version = (int) $lockedMutation->version + 1;
        $lockedMutation->save();

        $student->version = (int) $student->version + 1;
        $student->save();

        $after = [
            'mutation' => $this->mutationSnapshot($lockedMutation->fresh()),
            'membership' => $this->membershipSnapshot($membership->fresh()),
        ];

        return [
            'student' => $student->fresh(),
            'membership' => $membership->fresh(),
            'mutation' => $lockedMutation->fresh(),
            'before' => $before,
            'after' => $after,
            'consequence' => $consequence,
        ];
    }

    /**
     * @return array{id: int, name: string, nis: string|null, version: int}
     */
    public function serializeStudent(Student $student): array
    {
        return [
            'id' => (int) $student->id,
            'name' => $student->name,
            'nis' => $student->nis,
            'version' => (int) $student->version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeMutation(StudentMutation $mutation): array
    {
        $mutation->loadMissing([
            'student:id,name,nis,version',
            'schoolRombel:id,name',
            'studentRombel:id,student_status_id,archived_at,deleted_at,version',
            'creator:id,name',
            'canceller:id,name',
            'school:id,npsn,name,deleted_at',
        ]);

        return [
            'id' => (int) $mutation->id,
            'type' => $mutation->type,
            'effective_date' => $this->dateString($mutation->effective_date),
            'status' => $mutation->status,
            'reason' => $mutation->reason,
            'version' => (int) $mutation->version,
            'school_npsn' => $mutation->school_npsn,
            'school_name' => $mutation->school?->name,
            'student' => $mutation->student === null ? null : $this->serializeStudent($mutation->student),
            'class' => $mutation->schoolRombel === null ? null : [
                'id' => (int) $mutation->schoolRombel->id,
                'name' => $mutation->schoolRombel->name,
            ],
            'membership' => $mutation->studentRombel === null ? null : [
                'id' => (int) $mutation->studentRombel->id,
                'student_status_id' => $mutation->studentRombel->student_status_id === null ? null : (int) $mutation->studentRombel->student_status_id,
                'archived_at' => $mutation->studentRombel->archived_at === null ? null : (string) $mutation->studentRombel->archived_at,
                'deleted_at' => $mutation->studentRombel->deleted_at === null ? null : (string) $mutation->studentRombel->deleted_at,
                'version' => (int) $mutation->studentRombel->version,
            ],
            'year_id' => (int) $mutation->school_year_id,
            'created_by' => $mutation->creator === null ? null : [
                'id' => (int) $mutation->creator->id,
                'name' => $mutation->creator->name,
            ],
            'created_at' => $mutation->created_at?->toIso8601String(),
            'cancelled_at' => $mutation->cancelled_at?->toIso8601String(),
            'cancelled_by' => $mutation->canceller === null ? null : [
                'id' => (int) $mutation->canceller->id,
                'name' => $mutation->canceller->name,
            ],
            'cancel_reason' => $mutation->cancel_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeMembership(StudentRombel $membership): array
    {
        $membership->loadMissing('schoolRombel:id,name');

        return [
            'id' => (int) $membership->id,
            'school_rombel_id' => (int) $membership->school_rombel,
            'school_rombel_name' => $membership->schoolRombel?->name,
            'student_status_id' => $membership->student_status_id === null ? null : (int) $membership->student_status_id,
            'student_entry_id' => $membership->student_entry_id === null ? null : (int) $membership->student_entry_id,
            'latest_student_entry_date' => $this->dateString($membership->latest_student_entry_date),
            'mutation_education_school_id' => $membership->mutation_education_school_id,
            'latest_student_exit_date' => $this->dateString($membership->latest_student_exit_date),
            'latest_student_exit_school_id' => $membership->latest_student_exit_school_id,
            'latest_student_graduation_date' => $this->dateString($membership->latest_student_graduation_date),
            'archived_at' => $membership->archived_at === null ? null : (string) $membership->archived_at,
            'version' => (int) $membership->version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCorrection(StudentLockedCorrection $correction): array
    {
        $correction->loadMissing(['creator:id,name', 'schoolYear:id,name']);

        return [
            'id' => (int) $correction->id,
            'action' => $correction->action,
            'target_type' => $correction->target_type,
            'target_id' => (int) $correction->target_id,
            'mutation_id' => $correction->mutation_id === null ? null : (int) $correction->mutation_id,
            'student_rombel_id' => $correction->student_rombel_id === null ? null : (int) $correction->student_rombel_id,
            'before' => $correction->before,
            'after' => $correction->after,
            'reason' => $correction->reason,
            'approval_reference' => $correction->approval_reference,
            'year_id' => (int) $correction->school_year_id,
            'year_name' => $correction->schoolYear?->name,
            'created_by' => $correction->creator === null ? null : [
                'id' => (int) $correction->creator->id,
                'name' => $correction->creator->name,
            ],
            'created_at' => $correction->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mutationSnapshot(StudentMutation $mutation): array
    {
        return [
            'id' => (int) $mutation->id,
            'type' => $mutation->type,
            'status' => $mutation->status,
            'effective_date' => $this->dateString($mutation->effective_date),
            'school_npsn' => $mutation->school_npsn,
            'version' => (int) $mutation->version,
            'student_rombel_id' => $mutation->student_rombel_id === null ? null : (int) $mutation->student_rombel_id,
            'school_rombel_id' => (int) $mutation->school_rombel_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function membershipSnapshot(StudentRombel $membership): array
    {
        return [
            'id' => (int) $membership->id,
            'school_rombel_id' => (int) $membership->school_rombel,
            'student_status_id' => $membership->student_status_id === null ? null : (int) $membership->student_status_id,
            'student_entry_id' => $membership->student_entry_id === null ? null : (int) $membership->student_entry_id,
            'latest_student_entry_date' => $this->dateString($membership->latest_student_entry_date),
            'mutation_education_school_id' => $membership->mutation_education_school_id,
            'latest_student_exit_date' => $this->dateString($membership->latest_student_exit_date),
            'latest_student_exit_school_id' => $membership->latest_student_exit_school_id,
            'latest_student_graduation_date' => $this->dateString($membership->latest_student_graduation_date),
            'archived_at' => $membership->archived_at === null ? null : (string) $membership->archived_at,
            'version' => (int) $membership->version,
        ];
    }

    public function assertVersions(
        Student $student,
        ?int $expectedStudentVersion,
        ?StudentRombel $membership = null,
        ?int $expectedMembershipVersion = null,
        ?StudentMutation $mutation = null,
        ?int $expectedMutationVersion = null
    ): void {
        $errors = [];

        if ($expectedStudentVersion !== null && (int) $student->version !== $expectedStudentVersion) {
            $errors['expected_student_version'][] = 'The student record has been updated by another user. Reload the latest data.';
        }

        if ($membership !== null && $expectedMembershipVersion !== null && (int) $membership->version !== $expectedMembershipVersion) {
            $errors['expected_membership_version'][] = 'The class membership has been updated by another user. Reload the latest data.';
        }

        if ($mutation !== null && $expectedMutationVersion !== null && (int) $mutation->version !== $expectedMutationVersion) {
            $errors['expected_mutation_version'][] = 'The mutation log has been updated by another user. Reload the latest data.';
        }

        if ($errors === []) {
            return;
        }

        $versions = ['student_version' => (int) $student->version];
        if ($membership !== null) {
            $versions['membership_version'] = (int) $membership->version;
        }
        if ($mutation !== null) {
            $versions['mutation_version'] = (int) $mutation->version;
        }

        abort(response()->json([
            'message' => 'Conflict: The data changed since it was loaded. Reload and try again.',
            'errors' => $errors,
            'current_versions' => $versions,
        ], 409));
    }

    public function assertEffectiveDate(SchoolYear $year, string $effectiveDate, ?string $earliestDate = null): void
    {
        $this->assertYearHasDates($year);

        $start = $this->dateString($year->start_date);
        $end = $this->dateString($year->end_date);

        if ($effectiveDate > $this->today()) {
            throw ValidationException::withMessages([
                'effective_date' => ['The effective date cannot be in the future.'],
            ]);
        }

        if ($effectiveDate < $start || $effectiveDate > $end) {
            throw ValidationException::withMessages([
                'effective_date' => ['The effective date must be within the academic year period.'],
            ]);
        }

        if ($earliestDate !== null && $effectiveDate < $earliestDate) {
            throw ValidationException::withMessages([
                'effective_date' => ['The effective date cannot be earlier than the class membership entry date.'],
            ]);
        }
    }

    public function assertYearHasDates(SchoolYear $year): void
    {
        if ($this->dateString($year->start_date) === null || $this->dateString($year->end_date) === null) {
            throw ValidationException::withMessages([
                'school_year_id' => ['The selected academic year has no start or end date. Set the period dates first.'],
            ]);
        }
    }

    public function assertSchoolNpsn(string $npsn): EducationSchool
    {
        $school = EducationSchool::query()
            ->where('npsn', $npsn)
            ->whereNull('deleted_at')
            ->first();

        if ($school === null) {
            throw ValidationException::withMessages([
                'school_npsn' => ['The selected school is not available.'],
            ]);
        }

        return $school;
    }

    /**
     * @return EloquentCollection<int, StudentRombel>
     */
    public function lockStudentYearMemberships(Student $student, int $schoolYearId): EloquentCollection
    {
        return StudentRombel::query()
            ->where(function ($query) use ($student) {
                $query->where('student_id', $student->id);
                if (! empty($student->nik)) {
                    $query->orWhere(fn ($sub) => $sub->whereNull('student_id')->where('student_nik', $student->nik));
                }
            })
            ->whereHas('schoolRombel', fn ($query) => $query->where('school_year_id', $schoolYearId))
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  EloquentCollection<int, StudentRombel>  $memberships
     */
    public function assertNoActiveMembership(EloquentCollection $memberships): void
    {
        $active = $memberships->first(fn (StudentRombel $membership) => $this->membershipIsActive($membership));

        if ($active !== null) {
            $this->conflict(
                'student_id',
                'The student already has an active class membership in this academic year.',
                ['membership_version' => (int) $active->version]
            );
        }
    }

    public function membershipIsActive(StudentRombel $membership): bool
    {
        return $membership->deleted_at === null
            && $membership->archived_at === null
            && ($membership->student_status_id === null || (int) $membership->student_status_id === self::STATUS_ACTIVE)
            && $this->dateString($membership->latest_student_exit_date) === null;
    }

    public function membershipBelongsToStudent(StudentRombel $membership, Student $student): bool
    {
        if ($membership->student_id !== null) {
            return (int) $membership->student_id === (int) $student->id;
        }

        if (empty($membership->student_nik) || empty($student->nik) || $membership->student_nik !== $student->nik) {
            return false;
        }

        return Student::query()->where('nik', $student->nik)->count() === 1;
    }

    public function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }

    public function today(): string
    {
        return Carbon::now('Asia/Jakarta')->toDateString();
    }

    public function assertMembershipMatchesMutationIn(StudentRombel $membership, StudentMutation $mutation): void
    {
        $mismatch = $membership->archived_at !== null
            || $membership->deleted_at !== null
            || (int) $membership->version !== 1
            || (int) $membership->school_rombel !== (int) $mutation->school_rombel_id
            || ! $this->membershipIsActive($membership)
            || (int) $membership->student_entry_id !== self::ENTRY_MUTATION_IN
            || $this->dateString($membership->latest_student_entry_date) !== $this->dateString($mutation->effective_date)
            || (string) $membership->mutation_education_school_id !== (string) $mutation->school_npsn;

        if ($mismatch) {
            $this->conflict(
                'expected_membership_version',
                'The class membership no longer matches the mutation-in state.',
                ['membership_version' => (int) $membership->version]
            );
        }
    }

    public function assertMembershipMatchesMutationOut(StudentRombel $membership, StudentMutation $mutation): void
    {
        $mismatch = $membership->archived_at !== null
            || $membership->deleted_at !== null
            || (int) $membership->student_status_id !== self::STATUS_MUTATION_OUT
            || $this->dateString($membership->latest_student_exit_date) !== $this->dateString($mutation->effective_date)
            || (string) $membership->latest_student_exit_school_id !== (string) $mutation->school_npsn;

        if ($mismatch) {
            $this->conflict(
                'expected_membership_version',
                'The class membership no longer matches the mutation-out state.',
                ['membership_version' => (int) $membership->version]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeYear(SchoolYear $year, ?int $activeYearId): array
    {
        $start = $this->dateString($year->start_date);
        $end = $this->dateString($year->end_date);
        $locked = $year->locked_at !== null;

        return [
            'id' => (int) $year->id,
            'name' => $year->name,
            'start_date' => $start,
            'end_date' => $end,
            'is_locked' => $locked,
            'is_active' => $activeYearId !== null && (int) $activeYearId === (int) $year->id,
            'has_dates' => $start !== null && $end !== null,
            'can_mutate' => ! $locked && $start !== null && $end !== null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeClasses(int $schoolYearId): array
    {
        return SchoolRombel::query()
            ->where('school_year_id', $schoolYearId)
            ->whereNull('deleted_at')
            ->with(['schoolLevel:id,name', 'studentMajor:id,name'])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (SchoolRombel $class) => [
                'id' => (int) $class->id,
                'name' => $class->name,
                'school_level_id' => $class->school_level_id,
                'school_level_name' => $class->schoolLevel?->name,
                'student_major_id' => $class->student_major_id,
                'student_major_name' => $class->studentMajor?->name,
            ])
            ->values()
            ->all();
    }

    private function findYearForRead(int $schoolYearId): SchoolYear
    {
        $year = SchoolYear::query()->whereKey($schoolYearId)->whereNull('deleted_at')->first();

        if ($year === null) {
            throw ValidationException::withMessages([
                'school_year_id' => ['The selected academic year is not available.'],
            ]);
        }

        return $year;
    }

    private function lockYear(int $schoolYearId, string $field): SchoolYear
    {
        $year = SchoolYear::query()
            ->whereKey($schoolYearId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if ($year === null) {
            throw ValidationException::withMessages([
                $field => ['The selected academic year is not available.'],
            ]);
        }

        return $year;
    }

    private function assertYearUnlocked(SchoolYear $year, string $message): void
    {
        if ($year->locked_at !== null) {
            throw ValidationException::withMessages([
                'school_year_id' => [$message],
            ]);
        }
    }

    private function translateMembershipDomainMessage(string $message): string
    {
        $message = str_replace(['Rombel', 'rombel'], ['Class', 'class'], $message);

        return $message === '' ? 'The class membership state conflicts with this mutation.' : $message;
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
