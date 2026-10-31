<?php

namespace App\Services\Academic;

use App\Models\AcademicSetting;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentProgressionBatch;
use App\Models\StudentRombel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Progresi akhir tahun ajaran (Subfase 06b, DEC-30).
 *
 * Preview tidak mengubah siswa/membership; commit menerapkan keputusan secara
 * atomik dengan token terikat pembuat, batas 500 keputusan, dan replay idempoten.
 */
class StudentProgressionService
{
    public const MAX_BATCH_SIZE = 500;

    public const TOKEN_TTL_MINUTES = 30;

    public const DECISIONS = ['promote', 'retain', 'graduate', 'skip'];

    public const GRADUATED_STATUS_ID = 6;

    /**
     * @return array<string, mixed>
     */
    public function options(int $sourceYearId, int $targetYearId): array
    {
        [$source, $target] = $this->resolveYears($sourceYearId, $targetYearId);

        $activeYearId = AcademicSetting::find(AcademicSetting::SINGLETON_ID)?->active_school_year_id;

        return [
            'max_batch_size' => self::MAX_BATCH_SIZE,
            'source_school_year' => $this->serializeYear($source, $activeYearId),
            'target_school_year' => $this->serializeYear($target, $activeYearId),
            'source_classes' => $this->serializeClasses($source->id),
            'target_classes' => $this->serializeClasses($target->id),
        ];
    }

    /**
     * @param  array<int, int>  $rombelIds
     * @return array<string, mixed>
     */
    public function roster(int $sourceYearId, ?int $targetYearId, array $rombelIds, ?string $search): array
    {
        $source = SchoolYear::query()->whereNull('deleted_at')->find($sourceYearId);

        if ($source === null) {
            throw ValidationException::withMessages([
                'source_school_year_id' => ['The selected source academic year is not available.'],
            ]);
        }

        $target = null;
        if ($targetYearId !== null) {
            $target = SchoolYear::query()->whereNull('deleted_at')->find($targetYearId);

            if ($target === null) {
                throw ValidationException::withMessages([
                    'target_school_year_id' => ['The selected target academic year is not available.'],
                ]);
            }
        }

        $query = Student::query()
            ->whereNull('students.deleted_at')
            ->select([
                'students.id',
                'students.name',
                'students.nis',
                'students.nisn',
                'students.nik',
                'students.version',
            ])
            ->whereExists($this->activeMembershipExists($sourceYearId, $rombelIds));

        $search = trim((string) $search);
        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('students.name', 'like', "%{$search}%")
                    ->orWhere('students.nis', 'like', "%{$search}%")
                    ->orWhere('students.nisn', 'like', "%{$search}%");
            });
        }

        $total = (clone $query)->count();

        $students = $query
            ->orderBy('students.name')
            ->orderBy('students.id')
            ->limit(self::MAX_BATCH_SIZE + 1)
            ->get();

        $truncated = $students->count() > self::MAX_BATCH_SIZE;
        $students = $students->take(self::MAX_BATCH_SIZE)->values();

        $studentIds = $students->pluck('id')->all();
        $activeMap = $this->activeMembershipMap($studentIds, $sourceYearId);
        $targetMap = $target === null ? [] : $this->targetMembershipMap($studentIds, $target->id);
        $ambiguousLegacy = $this->ambiguousLegacyCount($sourceYearId);

        $items = $students->map(function (Student $student) use ($activeMap, $targetMap) {
            $memberships = $activeMap[$student->id] ?? collect();
            $membership = $memberships->count() === 1 ? $memberships->first() : null;
            $hasTargetMembership = isset($targetMap[$student->id]);
            $eligible = $membership !== null && ! $hasTargetMembership;

            $reason = null;
            if ($memberships->count() > 1) {
                $reason = 'multiple_active_memberships';
            } elseif ($memberships->count() === 0) {
                $reason = 'no_active_membership';
            } elseif ($hasTargetMembership) {
                $reason = 'target_membership_exists';
            }

            $rombel = $membership?->schoolRombel;
            $level = $rombel?->schoolLevel;
            $major = $rombel?->studentMajor;

            return [
                'student_id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'student_version' => (int) $student->version,
                'membership_id' => $membership?->id,
                'membership_version' => $membership === null ? null : (int) $membership->version,
                'active_membership_count' => $memberships->count(),
                'source_rombel' => $rombel === null ? null : [
                    'id' => $rombel->id,
                    'name' => $rombel->name,
                    'school_level_id' => $rombel->school_level_id,
                    'school_level_name' => $level?->name,
                    'student_major_id' => $rombel->student_major_id,
                    'student_major_name' => $major?->name,
                ],
                'has_target_membership' => $hasTargetMembership,
                'eligible' => $eligible,
                'ineligible_reason' => $reason,
            ];
        })->values()->all();

        return [
            'max_batch_size' => self::MAX_BATCH_SIZE,
            'total' => $total,
            'truncated' => $truncated,
            'ambiguous_legacy_count' => $ambiguousLegacy,
            'items' => $items,
        ];
    }

    /**
     * Validasi dan simpan batch preview tanpa mengubah data siswa/membership.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function preview(array $data, User $actor): array
    {
        $sourceYearId = (int) $data['source_school_year_id'];
        $targetYearId = (int) $data['target_school_year_id'];
        $graduationDate = $data['graduation_date'] ?? null;
        $decisions = $this->normalizeDecisions($data['decisions']);

        [$source, $target] = $this->resolveYears($sourceYearId, $targetYearId);
        $this->assertGlobalRules($decisions, $source, $target, $graduationDate);

        $evaluation = $this->evaluate($decisions, $source, $target, $graduationDate);

        if ($evaluation['has_conflicts']) {
            return [
                'valid' => false,
                'summary' => $evaluation['summary'],
                'items' => $evaluation['items'],
            ];
        }

        $token = bin2hex(random_bytes(32));

        $summary = $evaluation['summary'];
        $summary['graduation_date'] = $graduationDate;

        $batch = StudentProgressionBatch::create([
            'token_hash' => hash('sha256', $token),
            'source_school_year_id' => $source->id,
            'target_school_year_id' => $target->id,
            'status' => StudentProgressionBatch::STATUS_PREVIEW,
            'decisions' => $decisions,
            'summary' => $summary,
            'created_by' => $actor->id,
            'expires_at' => Carbon::now()->addMinutes(self::TOKEN_TTL_MINUTES),
        ]);

        return [
            'valid' => true,
            'batch_id' => $batch->id,
            'token' => $token,
            'expires_at' => $batch->expires_at->toIso8601String(),
            'summary' => $evaluation['summary'],
            'items' => $evaluation['items'],
        ];
    }

    /**
     * Terapkan batch secara atomik; replay token yang sama mengembalikan hasil.
     *
     * @return array<string, mixed>
     */
    public function commit(string $token, User $actor): array
    {
        $batch = StudentProgressionBatch::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($batch === null) {
            abort(response()->json([
                'message' => 'The progression token is invalid or has expired. Create a new preview.',
            ], 422));
        }

        if ((int) $batch->created_by !== (int) $actor->id) {
            abort(response()->json([
                'message' => 'This progression preview belongs to another account.',
            ], 403));
        }

        if ($batch->isExpired() && ! $batch->isCommitted()) {
            abort(response()->json([
                'message' => 'The progression token has expired. Create a new preview.',
            ], 422));
        }

        if ($batch->isCommitted()) {
            return $this->serializeCommitResult($batch, true);
        }

        return DB::transaction(function () use ($batch, $actor) {
            $lockedBatch = StudentProgressionBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($lockedBatch->isCommitted()) {
                return $this->serializeCommitResult($lockedBatch, true);
            }

            if ($lockedBatch->isExpired()) {
                abort(response()->json([
                    'message' => 'The progression token has expired. Create a new preview.',
                ], 422));
            }

            $decisions = $this->normalizeDecisions($lockedBatch->decisions);
            $sourceYearId = (int) $lockedBatch->source_school_year_id;
            $targetYearId = (int) $lockedBatch->target_school_year_id;

            $yearIds = [$sourceYearId, $targetYearId];
            sort($yearIds);
            $years = SchoolYear::withTrashed()
                ->whereIn('id', $yearIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $source = $years->get($sourceYearId);
            $target = $years->get($targetYearId);

            if ($source === null || $target === null) {
                abort(response()->json([
                    'message' => 'The selected academic years are no longer available.',
                ], 422));
            }

            $this->assertYearsUsable($source, $target);

            $graduationDate = $lockedBatch->summary['graduation_date'] ?? null;
            $this->assertGlobalRules($decisions, $source, $target, $graduationDate);

            $studentIds = collect($decisions)->pluck('student_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $membershipIds = collect($decisions)->pluck('membership_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

            $students = Student::withTrashed()
                ->whereIn('id', $studentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $memberships = StudentRombel::query()
                ->with([
                    'schoolRombel' => fn ($query) => $query->withTrashed(),
                    'schoolRombel.schoolLevel',
                    'schoolRombel.studentMajor',
                ])
                ->whereIn('id', $membershipIds)
                ->whereNull('student_rombels.deleted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $targetClassIds = collect($decisions)
                ->filter(fn ($decision) => $decision['decision'] === 'promote' && ! empty($decision['target_rombel_id']))
                ->pluck('target_rombel_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $targetClasses = SchoolRombel::query()
                ->with(['schoolLevel', 'studentMajor'])
                ->whereIn('id', $targetClassIds)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $evaluation = $this->evaluate(
                $decisions,
                $source,
                $target,
                $graduationDate,
                $students,
                $memberships,
                $targetClasses
            );

            if ($evaluation['has_conflicts']) {
                abort(response()->json([
                    'message' => 'Conflict: The progression data changed after the preview. Reload and preview again.',
                    'data' => [
                        'summary' => $evaluation['summary'],
                        'items' => $evaluation['items'],
                    ],
                ], 409));
            }

            $resultItems = [];
            $applyOrder = collect($decisions)->sortBy('student_id')->values();

            foreach ($applyOrder as $decision) {
                $student = $students->get((int) $decision['student_id']);
                $membership = $memberships->get((int) $decision['membership_id']);

                $result = [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'decision' => $decision['decision'],
                    'source_rombel_id' => $membership?->school_rombel,
                    'source_rombel_name' => $membership?->schoolRombel?->name,
                    'membership_id' => $membership?->id,
                    'target_rombel_id' => null,
                    'target_rombel_name' => null,
                    'new_membership_id' => null,
                    'student_version' => (int) $student->version,
                    'membership_version' => $membership === null ? null : (int) $membership->version,
                ];

                if ($decision['decision'] === 'promote') {
                    $targetRombelId = (int) $decision['target_rombel_id'];
                    $targetClass = $targetClasses->get($targetRombelId);

                    $newMembership = StudentRombel::create([
                        'student_id' => $student->id,
                        'student_nik' => $student->nik,
                        'school_rombel' => $targetRombelId,
                        'student_status_id' => 1,
                        'latest_student_entry_date' => $target->start_date?->toDateString(),
                        'version' => 1,
                    ]);

                    $student->version = (int) $student->version + 1;
                    $student->save();

                    $result['target_rombel_id'] = $targetRombelId;
                    $result['target_rombel_name'] = $targetClass?->name;
                    $result['new_membership_id'] = $newMembership->id;
                    $result['student_version'] = (int) $student->version;
                    $result['membership_version'] = 1;
                } elseif ($decision['decision'] === 'graduate') {
                    $membership->student_status_id = self::GRADUATED_STATUS_ID;
                    $membership->latest_student_exit_date = $graduationDate;
                    $membership->latest_student_graduation_date = $graduationDate;
                    $membership->latest_information = 'Progression batch #'.$lockedBatch->id;
                    $membership->version = (int) $membership->version + 1;
                    $membership->save();

                    $student->version = (int) $student->version + 1;
                    $student->save();

                    $result['student_version'] = (int) $student->version;
                    $result['membership_version'] = (int) $membership->version;
                }

                $resultItems[] = $result;
            }

            $lockedBatch->update([
                'status' => StudentProgressionBatch::STATUS_COMMITTED,
                'committed_by' => $actor->id,
                'committed_at' => Carbon::now(),
                'result' => [
                    'items' => $resultItems,
                ],
            ]);

            return $this->serializeCommitResult($lockedBatch->fresh(), false);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function batches(User $actor, ?int $sourceYearId, ?int $targetYearId, int $limit = 50): array
    {
        return StudentProgressionBatch::query()
            ->with(['sourceSchoolYear:id,name', 'targetSchoolYear:id,name', 'creator:id,name', 'committer:id,name'])
            ->where(fn ($query) => $query
                ->where('status', StudentProgressionBatch::STATUS_COMMITTED)
                ->orWhere('created_by', $actor->id))
            ->when($sourceYearId, fn ($query) => $query->where('source_school_year_id', $sourceYearId))
            ->when($targetYearId, fn ($query) => $query->where('target_school_year_id', $targetYearId))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (StudentProgressionBatch $batch) => $this->serializeBatch($batch))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function batchDetail(int $batchId, User $actor): array
    {
        $batch = StudentProgressionBatch::query()
            ->with(['sourceSchoolYear:id,name', 'targetSchoolYear:id,name', 'creator:id,name', 'committer:id,name'])
            ->find($batchId);

        if ($batch === null) {
            abort(response()->json(['message' => 'Progression batch not found!'], 404));
        }

        if (! $batch->isCommitted() && (int) $batch->created_by !== (int) $actor->id) {
            abort(response()->json([
                'message' => 'A preview batch can only be viewed by its creator.',
            ], 403));
        }

        $data = $this->serializeBatch($batch);
        $data['items'] = $batch->isCommitted()
            ? ($batch->result['items'] ?? [])
            : collect($batch->decisions ?? [])
                ->map(fn ($decision) => [
                    'student_id' => (int) $decision['student_id'],
                    'decision' => $decision['decision'],
                    'target_rombel_id' => $decision['target_rombel_id'] ?? null,
                ])
                ->values()
                ->all();

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $decisions
     * @return array{items: array<int, array<string, mixed>>, has_conflicts: bool, summary: array<string, mixed>}
     */
    private function evaluate(
        array $decisions,
        SchoolYear $source,
        SchoolYear $target,
        ?string $graduationDate,
        ?Collection $students = null,
        ?Collection $memberships = null,
        ?Collection $targetClasses = null
    ): array {
        $studentIds = collect($decisions)->pluck('student_id')->map(fn ($id) => (int) $id)->all();
        $membershipIds = collect($decisions)->pluck('membership_id')->map(fn ($id) => (int) $id)->all();

        $students ??= Student::withTrashed()->whereIn('id', $studentIds)->get()->keyBy('id');
        $memberships ??= StudentRombel::query()
            ->with([
                'schoolRombel' => fn ($query) => $query->withTrashed(),
                'schoolRombel.schoolLevel',
                'schoolRombel.studentMajor',
            ])
            ->whereIn('id', $membershipIds)
            ->whereNull('student_rombels.deleted_at')
            ->get()
            ->keyBy('id');

        $targetClassIds = collect($decisions)
            ->filter(fn ($decision) => $decision['decision'] === 'promote' && ! empty($decision['target_rombel_id']))
            ->pluck('target_rombel_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $targetClasses ??= SchoolRombel::query()
            ->with(['schoolLevel', 'studentMajor'])
            ->whereIn('id', $targetClassIds)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        $activeMap = $this->activeMembershipMap($studentIds, $source->id);
        $targetMap = $this->targetMembershipMap($studentIds, $target->id);

        $legacyOwner = $this->legacyOwnerMap($memberships->values());

        $items = [];
        $hasConflicts = false;
        $summary = [
            'total' => count($decisions),
            'ready' => 0,
            'conflicts' => 0,
            'warnings' => 0,
            'by_decision' => [
                'promote' => 0,
                'retain' => 0,
                'graduate' => 0,
                'skip' => 0,
            ],
        ];

        foreach (array_values($decisions) as $index => $decision) {
            $studentId = (int) $decision['student_id'];
            $membershipId = (int) $decision['membership_id'];
            $student = $students->get($studentId);
            $membership = $memberships->get($membershipId);
            $conflicts = [];
            $warnings = [];

            $summary['by_decision'][$decision['decision']]++;

            if ($student === null || $student->trashed()) {
                $conflicts[] = $this->conflict('student_unavailable', 'The student is not available.');
            } elseif ((int) $student->version !== (int) $decision['expected_student_version']) {
                $conflicts[] = $this->conflict(
                    'stale_student_version',
                    'The student record has changed since the preview was created.'
                );
            }

            if ($membership === null) {
                $conflicts[] = $this->conflict('membership_unavailable', 'The class membership is not available.');
            } else {
                $rombel = $membership->schoolRombel;

                if ($rombel === null || $rombel->trashed() || (int) $rombel->school_year_id !== (int) $source->id) {
                    $conflicts[] = $this->conflict(
                        'membership_not_in_source_year',
                        'The class membership does not belong to the source academic year.'
                    );
                }

                if (! $this->membershipIsActive($membership)) {
                    $conflicts[] = $this->conflict(
                        'membership_not_active',
                        'The class membership is no longer active in the source academic year.'
                    );
                }

                $ownerId = $membership->student_id !== null
                    ? (int) $membership->student_id
                    : ($legacyOwner[$membershipId] ?? null);

                if ($ownerId !== $studentId) {
                    $conflicts[] = $this->conflict(
                        'membership_owner_mismatch',
                        'The class membership does not belong to this student.'
                    );
                }

                $activeMemberships = $activeMap[$studentId] ?? collect();
                if ($activeMemberships->count() !== 1 || (int) $activeMemberships->first()->id !== $membershipId) {
                    $conflicts[] = $this->conflict(
                        'membership_not_single_active',
                        'The student does not have exactly one active membership matching this decision.'
                    );
                }

                if ((int) $membership->version !== (int) $decision['expected_membership_version']) {
                    $conflicts[] = $this->conflict(
                        'stale_membership_version',
                        'The class membership has changed since the preview was created.'
                    );
                }
            }

            if (isset($targetMap[$studentId])) {
                $conflicts[] = $this->conflict(
                    'target_membership_exists',
                    'The student already has a membership in the target academic year.'
                );
            }

            $targetClass = null;
            if ($decision['decision'] === 'promote') {
                $targetRombelId = (int) $decision['target_rombel_id'];

                $targetClass = $targetClasses->get($targetRombelId);

                if ($targetClass === null || (int) $targetClass->school_year_id !== (int) $target->id) {
                    $conflicts[] = $this->conflict(
                        'target_class_unavailable',
                        'The selected target class is not available in the target academic year.'
                    );
                    $targetClass = null;
                } elseif ($membership !== null && $membership->schoolRombel !== null) {
                    $sourceRombel = $membership->schoolRombel;

                    if ((int) $sourceRombel->school_level_id !== (int) $targetClass->school_level_id) {
                        $warnings[] = $this->conflict(
                            'level_mismatch',
                            'The source and target classes are in different school levels.'
                        );
                    }

                    if ((int) ($sourceRombel->student_major_id ?? 0) !== (int) ($targetClass->student_major_id ?? 0)) {
                        $warnings[] = $this->conflict(
                            'major_mismatch',
                            'The source and target classes are in different student majors.'
                        );
                    }
                }
            }

            $ready = $conflicts === [];
            if ($ready) {
                $summary['ready']++;
            } else {
                $summary['conflicts']++;
                $hasConflicts = true;
            }
            $summary['warnings'] += count($warnings);

            $sourceRombel = $membership?->schoolRombel;

            $items[] = [
                'student_id' => $studentId,
                'student_name' => $student?->name,
                'student_version' => $student === null ? null : (int) $student->version,
                'membership_id' => $membershipId,
                'membership_version' => $membership === null ? null : (int) $membership->version,
                'decision' => $decision['decision'],
                'source_rombel' => $sourceRombel === null ? null : [
                    'id' => $sourceRombel->id,
                    'name' => $sourceRombel->name,
                    'school_level_id' => $sourceRombel->school_level_id,
                    'school_level_name' => $sourceRombel->schoolLevel?->name,
                    'student_major_id' => $sourceRombel->student_major_id,
                    'student_major_name' => $sourceRombel->studentMajor?->name,
                ],
                'target_rombel_id' => $decision['decision'] === 'promote' ? (int) $decision['target_rombel_id'] : null,
                'target_rombel' => $targetClass === null ? null : [
                    'id' => $targetClass->id,
                    'name' => $targetClass->name,
                    'school_level_id' => $targetClass->school_level_id,
                    'school_level_name' => $targetClass->schoolLevel?->name,
                    'student_major_id' => $targetClass->student_major_id,
                    'student_major_name' => $targetClass->studentMajor?->name,
                ],
                'ready' => $ready,
                'conflicts' => $conflicts,
                'warnings' => $warnings,
            ];
        }

        return [
            'items' => $items,
            'has_conflicts' => $hasConflicts,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $decisions
     */
    private function assertGlobalRules(array $decisions, SchoolYear $source, SchoolYear $target, ?string $graduationDate): void
    {
        if ((int) $source->id === (int) $target->id) {
            throw ValidationException::withMessages([
                'target_school_year_id' => ['The target academic year must be different from the source academic year.'],
            ]);
        }

        $hasPromote = $this->hasDecision($decisions, 'promote');
        $hasGraduate = $this->hasDecision($decisions, 'graduate');

        if ($hasPromote && $target->start_date === null) {
            throw ValidationException::withMessages([
                'target_school_year_id' => ['The target academic year needs a start date before students can be promoted.'],
            ]);
        }

        if ($hasPromote && $target->locked_at !== null) {
            throw ValidationException::withMessages([
                'target_school_year_id' => ['The target academic year is locked. Unlock it before promoting students.'],
            ]);
        }

        if ($hasGraduate && $source->locked_at !== null) {
            throw ValidationException::withMessages([
                'source_school_year_id' => ['The source academic year is locked. Unlock it before recording graduation.'],
            ]);
        }

        if ($hasGraduate && ($graduationDate === null || $graduationDate === '')) {
            throw ValidationException::withMessages([
                'graduation_date' => ['A graduation date is required when at least one student graduates.'],
            ]);
        }
    }

    private function assertYearsUsable(SchoolYear $source, SchoolYear $target): void
    {
        if ($source->trashed() || $target->trashed()) {
            abort(response()->json([
                'message' => 'The selected academic years are no longer available.',
            ], 422));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $decisions
     */
    private function hasDecision(array $decisions, string $decision): bool
    {
        foreach ($decisions as $row) {
            if (($row['decision'] ?? null) === $decision) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDecisions(array $rows): array
    {
        return collect($rows)
            ->map(fn ($row) => [
                'student_id' => (int) $row['student_id'],
                'membership_id' => (int) $row['membership_id'],
                'expected_student_version' => (int) $row['expected_student_version'],
                'expected_membership_version' => (int) $row['expected_membership_version'],
                'decision' => (string) $row['decision'],
                'target_rombel_id' => isset($row['target_rombel_id']) && $row['target_rombel_id'] !== null
                    ? (int) $row['target_rombel_id']
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{0: SchoolYear, 1: SchoolYear}
     */
    private function resolveYears(int $sourceYearId, int $targetYearId): array
    {
        $source = SchoolYear::query()->whereNull('deleted_at')->find($sourceYearId);
        $target = SchoolYear::query()->whereNull('deleted_at')->find($targetYearId);

        if ($source === null) {
            throw ValidationException::withMessages([
                'source_school_year_id' => ['The selected source academic year is not available.'],
            ]);
        }

        if ($target === null) {
            throw ValidationException::withMessages([
                'target_school_year_id' => ['The selected target academic year is not available.'],
            ]);
        }

        return [$source, $target];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeYear(SchoolYear $year, ?int $activeYearId): array
    {
        return [
            'id' => $year->id,
            'name' => $year->name,
            'start_date' => $year->start_date?->toDateString(),
            'end_date' => $year->end_date?->toDateString(),
            'is_locked' => $year->locked_at !== null,
            'is_active' => $activeYearId !== null && (int) $activeYearId === (int) $year->id,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeClasses(int $yearId): array
    {
        return SchoolRombel::query()
            ->with(['schoolLevel:id,name', 'studentMajor:id,name'])
            ->withCount([
                'studentRombels as active_members_count' => fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->whereNull('archived_at')
                    ->where(fn ($inner) => $inner
                        ->where('student_status_id', 1)
                        ->orWhereNull('student_status_id'))
                    ->whereNull('latest_student_exit_date'),
            ])
            ->where('school_year_id', $yearId)
            ->orderBy('name')
            ->get()
            ->map(fn (SchoolRombel $rombel) => [
                'id' => $rombel->id,
                'name' => $rombel->name,
                'version' => (int) $rombel->version,
                'school_level_id' => $rombel->school_level_id,
                'school_level_name' => $rombel->schoolLevel?->name,
                'student_major_id' => $rombel->student_major_id,
                'student_major_name' => $rombel->studentMajor?->name,
                'active_members_count' => (int) ($rombel->active_members_count ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $batch
     * @return array<string, mixed>
     */
    private function serializeBatch(StudentProgressionBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'status' => $batch->status,
            'source_school_year' => $batch->sourceSchoolYear === null ? null : [
                'id' => $batch->sourceSchoolYear->id,
                'name' => $batch->sourceSchoolYear->name,
            ],
            'target_school_year' => $batch->targetSchoolYear === null ? null : [
                'id' => $batch->targetSchoolYear->id,
                'name' => $batch->targetSchoolYear->name,
            ],
            'summary' => $batch->summary,
            'created_by' => $batch->creator === null ? null : [
                'id' => $batch->creator->id,
                'name' => $batch->creator->name,
            ],
            'committed_by' => $batch->committer === null ? null : [
                'id' => $batch->committer->id,
                'name' => $batch->committer->name,
            ],
            'expires_at' => $batch->expires_at?->toIso8601String(),
            'committed_at' => $batch->committed_at?->toIso8601String(),
            'created_at' => $batch->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCommitResult(StudentProgressionBatch $batch, bool $replayed): array
    {
        return [
            'batch_id' => $batch->id,
            'status' => $batch->status,
            'replayed' => $replayed,
            'committed_at' => $batch->committed_at?->toIso8601String(),
            'summary' => $batch->summary,
            'items' => $batch->result['items'] ?? [],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function conflict(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }

    /**
     * Subquery EXISTS untuk siswa dengan keanggotaan aktif pada tahun sumber.
     *
     * @param  array<int, int>  $rombelIds
     */
    private function activeMembershipExists(int $yearId, array $rombelIds): \Closure
    {
        return function ($query) use ($yearId, $rombelIds) {
            $query->selectRaw('1')
                ->from('student_rombels as sr')
                ->join('school_rombels as rb', 'rb.id', '=', 'sr.school_rombel')
                ->where('rb.school_year_id', $yearId)
                ->whereNull('rb.deleted_at')
                ->whereNull('sr.deleted_at')
                ->whereNull('sr.archived_at')
                ->where(function ($q) {
                    $q->where('sr.student_status_id', 1)->orWhereNull('sr.student_status_id');
                })
                ->whereNull('sr.latest_student_exit_date');

            if ($rombelIds !== []) {
                $query->whereIn('sr.school_rombel', $rombelIds);
            }

            $query->where(function ($q) {
                $q->whereColumn('sr.student_id', 'students.id')
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('sr.student_id')
                            ->whereNotNull('students.nik')
                            ->whereColumn('sr.student_nik', 'students.nik');
                    });
            });
        };
    }

    /**
     * Keanggotaan aktif per siswa (student_id primer; NIK hanya bila unik).
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, Collection<int, StudentRombel>>
     */
    private function activeMembershipMap(array $studentIds, int $sourceYearId): array
    {
        if ($studentIds === []) {
            return [];
        }

        $students = Student::withTrashed()
            ->whereIn('id', $studentIds)
            ->get(['id', 'nik']);

        $uniqueNikMap = $students
            ->whereNotNull('nik')
            ->groupBy('nik')
            ->filter(fn (Collection $group) => $group->count() === 1)
            ->map(fn (Collection $group) => (int) $group->first()->id);

        $niks = $students->pluck('nik')->filter()->unique()->values()->all();

        $rows = StudentRombel::query()
            ->with([
                'schoolRombel' => fn ($query) => $query->withTrashed(),
                'schoolRombel.schoolLevel',
                'schoolRombel.studentMajor',
            ])
            ->whereNull('student_rombels.deleted_at')
            ->whereNull('student_rombels.archived_at')
            ->where(function ($query) {
                $query->where('student_rombels.student_status_id', 1)
                    ->orWhereNull('student_rombels.student_status_id');
            })
            ->whereNull('student_rombels.latest_student_exit_date')
            ->whereHas('schoolRombel', fn ($query) => $query
                ->where('school_year_id', $sourceYearId)
                ->whereNull('deleted_at'))
            ->where(function ($query) use ($studentIds, $niks) {
                $query->whereIn('student_rombels.student_id', $studentIds)
                    ->orWhere(function ($legacy) use ($niks) {
                        $legacy->whereNull('student_rombels.student_id')
                            ->whereIn('student_rombels.student_nik', $niks);
                    });
            })
            ->orderBy('student_rombels.id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $studentId = $row->student_id !== null
                ? (int) $row->student_id
                : ($row->student_nik !== null ? (int) ($uniqueNikMap[$row->student_nik] ?? 0) : 0);

            if ($studentId > 0) {
                $map[$studentId] ??= collect();
                $map[$studentId]->push($row);
            }
        }

        return $map;
    }

    /**
     * Membership apa pun (aktif/tertutup) di tahun tujuan per siswa.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, true>
     */
    private function targetMembershipMap(array $studentIds, int $yearId): array
    {
        if ($studentIds === []) {
            return [];
        }

        $students = Student::withTrashed()
            ->whereIn('id', $studentIds)
            ->get(['id', 'nik']);

        $uniqueNikMap = $students
            ->whereNotNull('nik')
            ->groupBy('nik')
            ->filter(fn (Collection $group) => $group->count() === 1)
            ->map(fn (Collection $group) => (int) $group->first()->id);

        $niks = $students->pluck('nik')->filter()->unique()->values()->all();

        $rows = StudentRombel::query()
            ->whereNull('student_rombels.deleted_at')
            ->whereHas('schoolRombel', fn ($query) => $query
                ->where('school_year_id', $yearId)
                ->whereNull('deleted_at'))
            ->where(function ($query) use ($studentIds, $niks) {
                $query->whereIn('student_rombels.student_id', $studentIds)
                    ->orWhere(function ($legacy) use ($niks) {
                        $legacy->whereNull('student_rombels.student_id')
                            ->whereIn('student_rombels.student_nik', $niks);
                    });
            })
            ->get(['id', 'student_id', 'student_nik']);

        $map = [];
        foreach ($rows as $row) {
            $studentId = $row->student_id !== null
                ? (int) $row->student_id
                : ($row->student_nik !== null ? (int) ($uniqueNikMap[$row->student_nik] ?? 0) : 0);

            if ($studentId > 0) {
                $map[$studentId] = true;
            }
        }

        return $map;
    }

    /**
     * Hitung baris legacy pada tahun sumber yang tidak dapat diatribusikan ke satu siswa.
     */
    private function ambiguousLegacyCount(int $sourceYearId): int
    {
        $rows = StudentRombel::query()
            ->whereNull('student_rombels.student_id')
            ->whereHas('schoolRombel', fn ($query) => $query
                ->where('school_year_id', $sourceYearId)
                ->whereNull('deleted_at'))
            ->get(['id', 'student_nik']);

        if ($rows->isEmpty()) {
            return 0;
        }

        $nikCounts = Student::query()
            ->whereIn('nik', $rows->pluck('student_nik')->filter()->unique()->values()->all())
            ->get(['id', 'nik'])
            ->groupBy('nik')
            ->map(fn (Collection $group) => $group->count());

        return $rows->filter(function (StudentRombel $row) use ($nikCounts) {
            return $row->student_nik === null || ($nikCounts[$row->student_nik] ?? 0) !== 1;
        })->count();
    }

    /**
     * Peta membership legacy -> student id hanya bila NIK menunjuk tepat satu siswa.
     *
     * @param  Collection<int, StudentRombel>  $memberships
     * @return array<int, int>
     */
    private function legacyOwnerMap(Collection $memberships): array
    {
        $legacy = $memberships->filter(fn (StudentRombel $row) => $row->student_id === null && $row->student_nik !== null);

        if ($legacy->isEmpty()) {
            return [];
        }

        $counts = Student::query()
            ->whereIn('nik', $legacy->pluck('student_nik')->unique()->values()->all())
            ->get(['id', 'nik'])
            ->groupBy('nik')
            ->map(fn (Collection $group) => $group->count());

        $ids = Student::query()
            ->whereIn('nik', $legacy->pluck('student_nik')->unique()->values()->all())
            ->get(['id', 'nik'])
            ->groupBy('nik');

        $map = [];
        foreach ($legacy as $row) {
            if (($counts[$row->student_nik] ?? 0) === 1) {
                $map[(int) $row->id] = (int) $ids[$row->student_nik]->first()->id;
            }
        }

        return $map;
    }

    private function membershipIsActive(StudentRombel $membership): bool
    {
        return $membership->deleted_at === null
            && $membership->archived_at === null
            && ($membership->student_status_id === null || (int) $membership->student_status_id === 1)
            && $membership->latest_student_exit_date === null;
    }
}
