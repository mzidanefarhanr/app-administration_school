<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StatusResource;
use App\Models\Student;
use App\Models\StudentRombel;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse|StatusResource
    {
        $this->authorize('viewAny', Student::class);

        $query = Student::with([
            'gender',
            'religion',
            'education_level',
            'blood_type',
            'profession',
            'village.district.regency.province',
            'family_status',
            'last_education_school',
            'latest_education_school',
            'father_education_level',
            'father_profession',
            'mother_education_level',
            'mother_profession',
            'guardian_education_level',
            'guardian_profession',
        ]);

        if ($request->boolean('only_archived')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('with_archived')) {
            $query->withTrashed();
        }

        $students = $query->get();

        return new StatusResource(true, 'List Data Student', $students);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $student = Student::withTrashed()->with([
            'gender',
            'religion',
            'education_level',
            'blood_type',
            'profession',
            'village.district.regency.province',
            'family_status',
            'last_education_school',
            'latest_education_school',
            'father_education_level',
            'father_profession',
            'mother_education_level',
            'mother_profession',
            'guardian_education_level',
            'guardian_profession',
            'studentRombels' => function ($q) {
                $q->whereNull('deleted_at')
                    ->whereNull('archived_at')
                    ->with('schoolRombel.schoolYear');
            },
        ])->findOrFail($id);

        $this->authorize('view', $student);

        return response()->json([
            'success' => true,
            'message' => 'Student details found.',
            'data' => $student,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $this->authorize('create', Student::class);

        $validated = $request->validated();

        // Singkirkan atribut yang ditentukan server
        unset($validated['id'], $validated['age'], $validated['version'], $validated['deleted_at']);
        $validated['version'] = 1;

        try {
            $student = DB::transaction(function () use ($validated) {
                return Student::create($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Student created successfully.',
                'data' => $student->fresh(),
            ], 201);
        } catch (QueryException $e) {
            return $this->handleUniqueConstraintViolation($e);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRequest $request, $id): JsonResponse
    {
        $expectedVersion = (int) $request->input('version');

        try {
            $updatedStudent = DB::transaction(function () use ($request, $id, $expectedVersion) {
                $student = Student::withTrashed()->where('id', $id)->lockForUpdate()->firstOrFail();

                $this->authorize('update', $student);

                if ($student->trashed()) {
                    throw ValidationException::withMessages([
                        'student' => ['Cannot update an archived student. Please restore the student record first.'],
                    ]);
                }

                // Optimistic concurrency control (DEC-21)
                if ((int) $student->version !== $expectedVersion) {
                    abort(response()->json([
                        'message' => 'Conflict: The student record has been updated by another user. Please reload the latest data.',
                        'current_version' => (int) $student->version,
                    ], 409));
                }

                $validated = $request->validated();
                unset($validated['id'], $validated['age'], $validated['version'], $validated['deleted_at']);

                $student->fill($validated);
                $student->version = $student->version + 1;
                $student->save();

                return $student;
            });

            return response()->json([
                'success' => true,
                'message' => 'Student updated successfully.',
                'data' => $updatedStudent->fresh(),
            ], 200);
        } catch (QueryException $e) {
            return $this->handleUniqueConstraintViolation($e);
        }
    }

    /**
     * Soft delete (archive) the specified resource from storage and deactivate active rombels.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $request->validate([
            'version' => ['required', 'integer'],
            'archive_reason' => ['required', 'string', 'max:255'],
        ], [
            'version.required' => 'Expected record version is required.',
            'archive_reason.required' => 'An administrative archiving reason is required.',
        ]);

        $expectedVersion = (int) $request->input('version');
        $archiveReason = (string) $request->input('archive_reason');

        DB::transaction(function () use ($id, $expectedVersion, $archiveReason) {
            $student = Student::where('id', $id)->lockForUpdate()->firstOrFail();

            $this->authorize('delete', $student);

            // Optimistic concurrency control (DEC-21)
            if ((int) $student->version !== $expectedVersion) {
                abort(response()->json([
                    'message' => 'Conflict: The student record has been updated by another user. Please reload before archiving.',
                    'current_version' => (int) $student->version,
                ], 409));
            }

            // Simpan alasan dan naikkan versi
            $student->archive_reason = $archiveReason;
            $student->version = $student->version + 1;
            $student->save();

            // Soft delete siswa
            $student->delete();

            // Nonaktifkan seluruh keanggotaan rombel aktif secara administratif (DEC-21)
            $activeRombels = StudentRombel::query()
                ->where(function ($q) use ($student) {
                    $q->where('student_id', $student->id);
                    if ($student->nik) {
                        $q->orWhere(function ($sub) use ($student) {
                            $sub->whereNull('student_id')->where('student_nik', $student->nik);
                        });
                    }
                })
                ->whereNull('deleted_at')
                ->whereNull('archived_at')
                ->where(function ($q) {
                    $q->where('student_status_id', 1)->orWhereNull('student_status_id');
                })
                ->whereNull('latest_student_exit_date')
                ->lockForUpdate()
                ->get();

            foreach ($activeRombels as $sr) {
                $sr->archived_at = now();
                $sr->archive_reason = 'Administrative archive: '.$archiveReason;
                $sr->version = $sr->version + 1;
                $sr->save();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Student archived successfully.',
        ], 200);
    }

    /**
     * Restore the specified soft-deleted resource.
     */
    public function restore(Request $request, $id): JsonResponse
    {
        $request->validate([
            'version' => ['required', 'integer'],
        ], [
            'version.required' => 'Expected record version is required.',
        ]);

        $expectedVersion = (int) $request->input('version');

        try {
            $restoredStudent = DB::transaction(function () use ($id, $expectedVersion) {
                $student = Student::onlyTrashed()->where('id', $id)->lockForUpdate()->firstOrFail();

                $this->authorize('restore', $student);

                // Optimistic concurrency control (DEC-21)
                if ((int) $student->version !== $expectedVersion) {
                    abort(response()->json([
                        'message' => 'Conflict: The student record has been modified by another process. Please reload.',
                        'current_version' => (int) $student->version,
                    ], 409));
                }

                // Periksa potensi konflik identitas terhadap record aktif lain (DEC-21)
                if ($student->nik && Student::where('nik', $student->nik)->whereNull('deleted_at')->where('id', '!=', $student->id)->exists()) {
                    throw ValidationException::withMessages([
                        'nik' => ['Cannot restore: The NIK has already been registered by another active student.'],
                    ]);
                }
                if ($student->nis && Student::where('nis', $student->nis)->whereNull('deleted_at')->where('id', '!=', $student->id)->exists()) {
                    throw ValidationException::withMessages([
                        'nis' => ['Cannot restore: The NIS has already been registered in this school.'],
                    ]);
                }
                if ($student->nisn && Student::where('nisn', $student->nisn)->whereNull('deleted_at')->where('id', '!=', $student->id)->exists()) {
                    throw ValidationException::withMessages([
                        'nisn' => ['Cannot restore: The NISN has already been registered by another active student.'],
                    ]);
                }

                $student->restore();
                $student->archive_reason = null;
                $student->version = $student->version + 1;
                $student->save();

                // Keanggotaan rombel lama TIDAK diaktifkan otomatis (DEC-21: archived_at dipertahankan)
                return $student;
            });

            return response()->json([
                'success' => true,
                'message' => 'Student restored successfully.',
                'data' => $restoredStudent->fresh(),
            ], 200);
        } catch (QueryException $e) {
            return $this->handleUniqueConstraintViolation($e);
        }
    }

    /**
     * Handle raw SQL unique constraint violation into safe, clean English validation messages.
     */
    private function handleUniqueConstraintViolation(QueryException $e): JsonResponse
    {
        $msg = strtolower($e->getMessage());

        if (str_contains($msg, 'nik')) {
            return response()->json([
                'message' => 'The NIK has already been registered.',
                'errors' => ['nik' => ['The NIK has already been registered.']],
            ], 422);
        }

        if (str_contains($msg, 'nisn')) {
            return response()->json([
                'message' => 'The NISN has already been registered.',
                'errors' => ['nisn' => ['The NISN has already been registered.']],
            ], 422);
        }

        if (str_contains($msg, 'nis')) {
            return response()->json([
                'message' => 'The NIS has already been registered in this school.',
                'errors' => ['nis' => ['The NIS has already been registered in this school.']],
            ], 422);
        }

        throw $e;
    }
}
