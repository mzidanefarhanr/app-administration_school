<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Http\Resources\StatusResource;
use App\Models\Gender;
use App\Models\SchoolRombel;
use App\Models\Student;
use App\Models\StudentRombel;
use App\Support\StudentDirectory;
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

        // Dialog Archived (03b) tetap memakai daftar arsip terpisah; hanya untuk pengelola.
        if ($request->boolean('only_archived')) {
            $this->authorize('students.manage');

            $students = Student::onlyTrashed()->with([
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
            ])->get();

            return new StatusResource(true, 'List Data Student', $students);
        }

        $validated = $request->validate([
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'placement' => ['nullable', 'in:all,placed,unplaced'],
            'rombel' => ['nullable', 'integer', 'exists:school_rombels,id'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'in:id,name,nis,nisn'],
            'direction' => ['nullable', 'in:asc,desc'],
        ], [
            'school_year_id.required' => 'A school year is required to list students.',
            'placement.in' => 'The placement filter must be one of: all, placed, unplaced.',
            'rombel.integer' => 'The class filter must be an integer.',
            'rombel.exists' => 'The selected class does not exist.',
            'per_page.in' => 'The per page value must be one of: 10, 20, 50, 100.',
            'sort.in' => 'The sort field must be one of: id, name, nis, nisn.',
            'direction.in' => 'The sort direction must be asc or desc.',
        ]);

        $yearId = (int) $validated['school_year_id'];
        $placement = $validated['placement'] ?? 'all';
        $rombelId = isset($validated['rombel']) ? (int) $validated['rombel'] : null;

        if ($rombelId !== null && ! SchoolRombel::where('id', $rombelId)->where('school_year_id', $yearId)->exists()) {
            throw ValidationException::withMessages([
                'rombel' => ['The selected class does not belong to the selected school year.'],
            ]);
        }

        if ($rombelId !== null && $placement === 'unplaced') {
            throw ValidationException::withMessages([
                'placement' => ['The unplaced filter cannot be combined with a class filter.'],
            ]);
        }

        $filters = [
            'school_year_id' => $yearId,
            'search' => $validated['search'] ?? null,
            'rombel' => $rombelId,
        ];

        // Rekap dihitung dari seluruh hasil filter, bukan halaman aktif.
        $scope = StudentDirectory::base($filters);
        $placedCount = (clone $scope)
            ->whereExists(StudentDirectory::activeMembershipExists($yearId))
            ->count('students.id');
        $scopeCount = $scope->count('students.id');
        $unplacedCount = max(0, $scopeCount - $placedCount);
        $totalMatches = match ($placement) {
            'placed' => $placedCount,
            'unplaced' => $unplacedCount,
            default => $scopeCount,
        };

        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $perPage = (int) ($validated['per_page'] ?? 20);

        $listQuery = StudentDirectory::place(StudentDirectory::base($filters), $yearId, $placement)
            ->with('gender:id,name')
            ->orderBy('students.'.$sort, $direction);

        // Urutan stabil saat nilai sort sama.
        if ($sort !== 'id') {
            $listQuery->orderBy('students.id');
        }

        $paginator = $listQuery->paginate($perPage)->withQueryString();

        $memberships = StudentDirectory::membershipsFor($paginator->getCollection(), $yearId);
        $data = $paginator->getCollection()->map(function (Student $student) use ($memberships) {
            $membership = $memberships[$student->id] ?? null;

            return [
                'id' => $student->id,
                'version' => (int) $student->version,
                'name' => $student->name,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'gender' => $student->gender?->name,
                'rombel' => $membership?->schoolRombel
                    ? [
                        'id' => $membership->schoolRombel->id,
                        'name' => $membership->schoolRombel->name,
                        'membership_id' => (int) $membership->id,
                        'membership_version' => (int) $membership->version,
                    ]
                    : null,
                'placement' => $membership ? 'placed' : 'unplaced',
            ];
        })->values();

        $result = StudentDirectory::place(StudentDirectory::base($filters), $yearId, $placement);
        $genderRows = (clone $result)
            ->select(['students.gender_id'])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('students.gender_id')
            ->get();
        $genderNames = Gender::whereIn('id', $genderRows->pluck('gender_id')->filter())->pluck('name', 'id');
        $byGender = $genderRows->map(fn ($row) => [
            'label' => $genderNames[$row->gender_id] ?? 'Unspecified',
            'total' => (int) $row->total,
        ])->values();

        $byRombel = collect();
        if ($placement !== 'unplaced') {
            $placed = StudentDirectory::place(StudentDirectory::base($filters), $yearId, 'placed')->get();
            $ids = $placed->pluck('id')->filter()->all();
            $niks = $placed->pluck('nik')->filter()->all();

            if ($ids || $niks) {
                $byRombel = DB::table('student_rombels as sr')
                    ->join('school_rombels as rb', 'rb.id', '=', 'sr.school_rombel')
                    ->where('rb.school_year_id', $yearId)
                    ->whereNull('sr.deleted_at')
                    ->whereNull('sr.archived_at')
                    ->where(function ($q) {
                        $q->where('sr.student_status_id', 1)->orWhereNull('sr.student_status_id');
                    })
                    ->whereNull('sr.latest_student_exit_date')
                    ->where(function ($q) use ($ids, $niks) {
                        $q->whereIn('sr.student_id', $ids)
                            ->orWhere(function ($legacy) use ($niks) {
                                $legacy->whereNull('sr.student_id')->whereIn('sr.student_nik', $niks);
                            });
                    })
                    ->groupBy('rb.id', 'rb.name')
                    ->orderBy('rb.name')
                    ->selectRaw('rb.id as id, rb.name as name, COUNT(DISTINCT COALESCE(sr.student_id, sr.student_nik)) as total')
                    ->get()
                    ->map(fn ($row) => [
                        'id' => (int) $row->id,
                        'name' => $row->name,
                        'total' => (int) $row->total,
                    ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Student list retrieved successfully.',
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'recap' => [
                'total_matches' => $totalMatches,
                'placed_count' => $placedCount,
                'unplaced_count' => $unplacedCount,
                'by_gender' => $byGender,
                'by_rombel' => $byRombel,
            ],
        ], 200);
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
