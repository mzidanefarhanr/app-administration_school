<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignStudentRombelRequest;
use App\Http\Requests\StoreStudentRombelRequest;
use App\Http\Requests\TransferStudentRombelRequest;
use App\Http\Requests\UpdateStudentRombelRequest;
use App\Http\Resources\StatusResource;
use App\Models\Student;
use App\Models\StudentRombel;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StudentRombelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $StudentRombel = StudentRombel::with([
            'student.gender',
            'student.religion',
            'student.education_level',
            'student.blood_type',
            'student.profession',
            'student.village.district.regency.province',
            'student.family_status',
            'student.last_education_school',
            'student.latest_education_school',
            'student.father_education_level',
            'student.father_profession',
            'student.mother_education_level',
            'student.mother_profession',
            'student.guardian_education_level',
            'student.guardian_profession',
            'studentNik.gender',
            'studentNik.religion',
            'studentNik.education_level',
            'studentNik.blood_type',
            'studentNik.profession',
            'studentNik.village.district.regency.province',
            'studentNik.family_status',
            'studentNik.last_education_school',
            'studentNik.latest_education_school',
            'studentNik.father_education_level',
            'studentNik.father_profession',
            'studentNik.mother_education_level',
            'studentNik.mother_profession',
            'studentNik.guardian_education_level',
            'studentNik.guardian_profession',
            'schoolRombel.schoolLevel',
            'schoolRombel.schoolYear',
            'schoolRombel.studentMajor',
            'schoolRombel.classTeach',
            'studentStatus',
            'studentEntry',
            'mutationEducationSchool.educationLevel',
            'mutationEducationSchool.district.regency.province',
            'latestStudentExitSchool.educationLevel',
            'latestStudentExitSchool.district.regency.province',
        ])->get();

        return new StatusResource(true, 'List Data StudentRombel', $StudentRombel);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentRombelRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $studentRombels = StudentRombel::with([
            'student.gender',
            'student.religion',
            'student.education_level',
            'student.blood_type',
            'student.profession',
            'student.village.district.regency.province',
            'student.family_status',
            'student.last_education_school',
            'student.latest_education_school',
            'student.father_education_level',
            'student.father_profession',
            'student.mother_education_level',
            'student.mother_profession',
            'student.guardian_education_level',
            'student.guardian_profession',
            'studentNik.gender',
            'studentNik.religion',
            'studentNik.education_level',
            'studentNik.blood_type',
            'studentNik.profession',
            'studentNik.village.district.regency.province',
            'studentNik.family_status',
            'studentNik.last_education_school',
            'studentNik.latest_education_school',
            'studentNik.father_education_level',
            'studentNik.father_profession',
            'studentNik.mother_education_level',
            'studentNik.mother_profession',
            'studentNik.guardian_education_level',
            'studentNik.guardian_profession',
            'schoolRombel.schoolLevel',
            'schoolRombel.schoolYear',
            'schoolRombel.studentMajor',
            'schoolRombel.classTeach',
            'studentStatus',
            'studentEntry',
            'mutationEducationSchool.educationLevel',
            'mutationEducationSchool.district.regency.province',
            'latestStudentExitSchool.educationLevel',
            'latestStudentExitSchool.district.regency.province',
        ])
            ->whereNull('archived_at')
            ->whereHas('schoolRombel', function ($query) use ($id) {
                $query->where('school_year_id', $id);
            })
            ->get();

        return new StatusResource(true, 'Student Rombels detail found!', $studentRombels);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StudentRombel $studentRombel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRombelRequest $request, StudentRombel $studentRombel)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StudentRombel $studentRombel)
    {
        //
    }

    /**
     * Assign an unplaced student to a rombel in the selected academic year (Subphase 03d, DEC-23).
     */
    public function assignRombel(AssignStudentRombelRequest $request, $id): JsonResponse
    {
        $this->authorize('students.manage');

        $schoolYearId = (int) $request->input('school_year_id');
        $rombelId = (int) $request->input('school_rombel_id');
        $expectedStudentVersion = (int) $request->input('expected_student_version');
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        try {
            $result = DB::transaction(function () use ($id, $schoolYearId, $rombelId, $expectedStudentVersion, $today) {
                // 1. Lock student row first (consistent locking order, including trashed to reject cleanly)
                $student = Student::withTrashed()->where('id', $id)->lockForUpdate()->firstOrFail();

                // 2. Reject if archived
                if ($student->trashed()) {
                    abort(response()->json([
                        'message' => 'Cannot assign rombel to an archived student.',
                    ], 422));
                }

                // 3. Optimistic locking on student version
                if ((int) $student->version !== $expectedStudentVersion) {
                    abort(response()->json([
                        'message' => 'Conflict: The student record has been updated by another user. Please reload the latest data.',
                        'current_version' => (int) $student->version,
                    ], 409));
                }

                // 4. Verify no active membership exists for this student in this academic year
                $existingActive = StudentRombel::where(function ($q) use ($student) {
                    $q->where('student_id', $student->id);
                    if (! empty($student->nik)) {
                        $q->orWhere(fn ($sub) => $sub->whereNull('student_id')->where('student_nik', $student->nik));
                    }
                })
                    ->whereHas('schoolRombel', fn ($q) => $q->where('school_year_id', $schoolYearId))
                    ->whereNull('deleted_at')
                    ->whereNull('archived_at')
                    ->where(fn ($q) => $q->where('student_status_id', 1)->orWhereNull('student_status_id'))
                    ->whereNull('latest_student_exit_date')
                    ->lockForUpdate()
                    ->first();

                if ($existingActive) {
                    abort(response()->json([
                        'message' => 'Conflict: Student already has an active rombel membership in this academic year.',
                        'current_rombel_id' => (int) $existingActive->school_rombel,
                    ], 409));
                }

                // 5. Create new membership
                $membership = StudentRombel::create([
                    'student_id' => $student->id,
                    'student_nik' => $student->nik,
                    'school_rombel' => $rombelId,
                    'student_status_id' => 1,
                    'latest_student_entry_date' => $today,
                    'version' => 1,
                ]);

                // 6. Increment student version
                $student->version = (int) $student->version + 1;
                $student->save();

                return [
                    'student' => $student->fresh(),
                    'membership' => $membership->load('schoolRombel'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Student successfully assigned to rombel.',
                'data' => [
                    'student_id' => $result['student']->id,
                    'school_rombel_id' => $result['membership']->school_rombel,
                    'school_rombel_name' => $result['membership']->schoolRombel?->name,
                    'student_version' => (int) $result['student']->version,
                    'membership_version' => (int) $result['membership']->version,
                ],
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (QueryException $e) {
            if ($e->getCode() == '45000' || str_contains($e->getMessage(), 'already has an active rombel')) {
                return response()->json([
                    'message' => 'Conflict: Student already has an active rombel membership in this academic year.',
                ], 409);
            }
            if (str_contains($e->getMessage(), 'archived student')) {
                return response()->json([
                    'message' => 'Cannot assign rombel to an archived student.',
                ], 422);
            }
            throw $e;
        }
    }

    /**
     * Transfer an active student to another rombel within the same academic year (Subphase 03d, DEC-23).
     */
    public function transferRombel(TransferStudentRombelRequest $request, $id): JsonResponse
    {
        $this->authorize('students.manage');

        $schoolYearId = (int) $request->input('school_year_id');
        $targetRombelId = (int) $request->input('target_rombel_id');
        $expectedStudentVersion = (int) $request->input('expected_student_version');
        $expectedMembershipVersion = (int) $request->input('expected_membership_version');
        $reason = $request->input('reason');
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        try {
            $result = DB::transaction(function () use (
                $id, $schoolYearId, $targetRombelId, $expectedStudentVersion, $expectedMembershipVersion, $reason, $today
            ) {
                // 1. Lock student row first (consistent locking order, including trashed to reject cleanly)
                $student = Student::withTrashed()->where('id', $id)->lockForUpdate()->firstOrFail();

                // 2. Reject if archived
                if ($student->trashed()) {
                    abort(response()->json([
                        'message' => 'Cannot transfer rombel for an archived student.',
                    ], 422));
                }

                // 3. Optimistic locking on student version
                if ((int) $student->version !== $expectedStudentVersion) {
                    abort(response()->json([
                        'message' => 'Conflict: The student record has been updated by another user. Please reload the latest data.',
                        'current_version' => (int) $student->version,
                    ], 409));
                }

                // 4. Find active membership in this academic year
                $activeMembership = StudentRombel::where(function ($q) use ($student) {
                    $q->where('student_id', $student->id);
                    if (! empty($student->nik)) {
                        $q->orWhere(fn ($sub) => $sub->whereNull('student_id')->where('student_nik', $student->nik));
                    }
                })
                    ->whereHas('schoolRombel', fn ($q) => $q->where('school_year_id', $schoolYearId))
                    ->whereNull('deleted_at')
                    ->whereNull('archived_at')
                    ->where(fn ($q) => $q->where('student_status_id', 1)->orWhereNull('student_status_id'))
                    ->whereNull('latest_student_exit_date')
                    ->lockForUpdate()
                    ->first();

                if (! $activeMembership) {
                    abort(response()->json([
                        'message' => 'Student has no active rombel membership in the selected academic year to transfer from.',
                    ], 422));
                }

                // 5. Reject if target rombel is identical to current rombel
                if ((int) $activeMembership->school_rombel === $targetRombelId) {
                    abort(response()->json([
                        'message' => 'Target rombel must be different from current rombel.',
                        'errors' => ['target_rombel_id' => ['Target rombel must be different from current rombel.']],
                    ], 422));
                }

                // 6. Optimistic locking on membership version
                if ((int) $activeMembership->version !== $expectedMembershipVersion) {
                    abort(response()->json([
                        'message' => 'Conflict: The student rombel membership has been modified by another operator. Please reload.',
                        'current_membership_version' => (int) $activeMembership->version,
                    ], 409));
                }

                // 7. Close old membership with latest_student_exit_date = today (DEC-23 #5)
                // Note: student_status_id is NOT changed to Mutasi Keluar!
                $activeMembership->latest_student_exit_date = $today;
                if (! empty($reason)) {
                    $activeMembership->latest_information = 'Transfer: '.mb_substr(trim($reason), 0, 240);
                }
                $activeMembership->version = (int) $activeMembership->version + 1;
                $activeMembership->save();

                // 8. Create new membership in target rombel
                $newMembership = StudentRombel::create([
                    'student_id' => $student->id,
                    'student_nik' => $student->nik,
                    'school_rombel' => $targetRombelId,
                    'student_status_id' => 1,
                    'latest_student_entry_date' => $today,
                    'version' => 1,
                ]);

                // 9. Increment student version
                $student->version = (int) $student->version + 1;
                $student->save();

                return [
                    'student' => $student->fresh(),
                    'previous_rombel' => $activeMembership->load('schoolRombel'),
                    'new_rombel' => $newMembership->load('schoolRombel'),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Student successfully transferred to new rombel.',
                'data' => [
                    'student_id' => $result['student']->id,
                    'previous_rombel_id' => $result['previous_rombel']->school_rombel,
                    'previous_rombel_name' => $result['previous_rombel']->schoolRombel?->name,
                    'new_rombel_id' => $result['new_rombel']->school_rombel,
                    'new_rombel_name' => $result['new_rombel']->schoolRombel?->name,
                    'student_version' => (int) $result['student']->version,
                    'new_membership_version' => (int) $result['new_rombel']->version,
                ],
            ], 200);
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (QueryException $e) {
            if ($e->getCode() == '45000' || str_contains($e->getMessage(), 'already has an active rombel')) {
                return response()->json([
                    'message' => 'Conflict: Student already has an active rombel membership in this academic year.',
                ], 409);
            }
            throw $e;
        }
    }
}
