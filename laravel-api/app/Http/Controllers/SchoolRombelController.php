<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolRombelRequest;
use App\Http\Requests\UpdateSchoolRombelRequest;
use App\Http\Resources\StatusResource;
use App\Models\Employee;
use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\StudentRombel;
use App\Models\TeachingAssignment;
use App\Models\TeachingScheduleException;
use App\Services\Academic\SchoolRombelImportExportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolRombelController extends Controller
{
    private const LIST_RELATIONS = [
        'schoolLevel:id,name',
        'schoolYear:id,name,start_date,end_date,locked_at',
        'studentMajor:id,name',
        'classTeachEmployee:id,name',
        'classTeach:id,name',
    ];

    /**
     * Display classes, optionally filtered by academic year (Fase 06a).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', SchoolRombel::class);

        $validated = $request->validate([
            'school_year_id' => ['nullable', 'integer'],
        ]);

        $query = $this->baseQuery();

        if (! empty($validated['school_year_id'])) {
            $query->where('school_year_id', (int) $validated['school_year_id']);
        }

        $classes = $query
            ->orderByDesc('school_year_id')
            ->orderBy('name')
            ->get()
            ->map(fn (SchoolRombel $rombel) => $this->serialize($rombel))
            ->values();

        return new StatusResource(true, 'Classes List', $classes);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created class (DEC-29 D6/D7).
     */
    public function store(StoreSchoolRombelRequest $request): JsonResponse
    {
        $data = $request->validated();
        $name = trim((string) $data['name']);

        $created = DB::transaction(function () use ($data, $name) {
            $schoolYear = SchoolYear::whereKey($data['school_year_id'])
                ->lockForUpdate()
                ->first();

            if (! $schoolYear || $schoolYear->deleted_at !== null) {
                throw ValidationException::withMessages([
                    'school_year_id' => ['The selected academic year is not available.'],
                ]);
            }

            if ($schoolYear->locked_at !== null) {
                throw ValidationException::withMessages([
                    'school_year_id' => ['The selected academic year is locked. Unlock it before changing classes.'],
                ]);
            }

            if ($this->nameExists((int) $schoolYear->id, $name)) {
                throw ValidationException::withMessages([
                    'name' => ['The class name has already been taken for this school year.'],
                ]);
            }

            $teacher = $this->resolveTeacher($data['class_teach_employee_id'] ?? null);
            $legacyTeacherId = $data['class_teach_id'] ?? $teacher?->user_id;

            return SchoolRombel::create([
                'version' => 1,
                'school_year_id' => $schoolYear->id,
                'school_level_id' => $data['school_level_id'],
                'student_major_id' => $data['student_major_id'] ?? null,
                'class_teach_employee_id' => $teacher?->id,
                'class_teach_id' => $legacyTeacherId,
                'name' => $name,
            ]);
        });

        $rombel = $this->baseQuery()->findOrFail($created->id);

        return response()->json([
            'success' => true,
            'message' => 'Class successfully created!',
            'data' => $this->serialize($rombel),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $this->authorize('viewAny', SchoolRombel::class);

        $rombel = $this->baseQuery()->find($id);

        if (! $rombel) {
            return response()->json(['message' => 'Class not found!'], 404);
        }

        return new StatusResource(true, 'Class detail found!', $this->serialize($rombel));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SchoolRombel $schoolRombel)
    {
        //
    }

    /**
     * Update a class with optimistic concurrency (DEC-29 D6).
     */
    public function update(UpdateSchoolRombelRequest $request, $id): JsonResponse
    {
        $data = $request->validated();
        $name = trim((string) $data['name']);
        $expectedVersion = (int) $data['expected_version'];

        $snapshot = SchoolRombel::find($id);
        if (! $snapshot) {
            return response()->json(['message' => 'Class not found!'], 404);
        }

        DB::transaction(function () use ($data, $name, $expectedVersion, $snapshot) {
            $yearIds = array_values(array_unique([
                (int) $snapshot->school_year_id,
                (int) $data['school_year_id'],
            ]));
            sort($yearIds);

            $years = SchoolYear::withTrashed()
                ->whereIn('id', $yearIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $currentYear = $years->get((int) $snapshot->school_year_id);
            $targetYear = $years->get((int) $data['school_year_id']);

            if (! $currentYear || $currentYear->trashed()) {
                throw ValidationException::withMessages([
                    'school_year_id' => ['The current academic year of this class is not available.'],
                ]);
            }

            if (! $targetYear || $targetYear->trashed()) {
                throw ValidationException::withMessages([
                    'school_year_id' => ['The selected academic year is not available.'],
                ]);
            }

            if ($currentYear->locked_at !== null || $targetYear->locked_at !== null) {
                throw ValidationException::withMessages([
                    'school_year_id' => ['The academic year of this class is locked. Unlock it before changing classes.'],
                ]);
            }

            $rombel = SchoolRombel::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();

            if ((int) $rombel->school_year_id !== (int) $snapshot->school_year_id) {
                $this->abortConflict($rombel, 'Conflict: The class was modified by another operator. Please reload.');
            }

            if ((int) $rombel->version !== $expectedVersion) {
                $this->abortConflict($rombel, 'Conflict: The class record has been updated by another operator. Please reload.');
            }

            $yearChanged = (int) $rombel->school_year_id !== (int) $targetYear->id;
            $levelChanged = (int) $rombel->school_level_id !== (int) $data['school_level_id'];
            $majorChanged = (int) ($rombel->student_major_id ?? 0) !== (int) ($data['student_major_id'] ?? 0);

            $hasHistory = StudentRombel::withTrashed()
                ->where('school_rombel', $rombel->id)
                ->exists();

            if ($hasHistory && ($yearChanged || $levelChanged || $majorChanged)) {
                $errors = [];
                if ($yearChanged) {
                    $errors['school_year_id'] = ['The academic year cannot be changed because this class has membership history.'];
                }
                if ($levelChanged) {
                    $errors['school_level_id'] = ['The school level cannot be changed because this class has membership history.'];
                }
                if ($majorChanged) {
                    $errors['student_major_id'] = ['The student major cannot be changed because this class has membership history.'];
                }
                throw ValidationException::withMessages($errors);
            }

            if ($yearChanged && (
                TeachingAssignment::where('school_rombel_id', $rombel->id)->exists()
                || TeachingScheduleException::where('school_rombel_id', $rombel->id)->exists()
            )) {
                throw ValidationException::withMessages([
                    'school_year_id' => ['The academic year cannot be changed because this class has teaching assignments or schedule exceptions.'],
                ]);
            }

            if ($this->nameExists((int) $targetYear->id, $name, (int) $rombel->id)) {
                throw ValidationException::withMessages([
                    'name' => ['The class name has already been taken for this school year.'],
                ]);
            }

            $teacher = $this->resolveTeacher($data['class_teach_employee_id'] ?? null);
            $legacyTeacherId = $data['class_teach_id'] ?? $teacher?->user_id;

            $rombel->update([
                'name' => $name,
                'school_year_id' => $targetYear->id,
                'school_level_id' => $data['school_level_id'],
                'student_major_id' => $data['student_major_id'] ?? null,
                'class_teach_employee_id' => $teacher?->id,
                'class_teach_id' => $legacyTeacherId,
                'version' => (int) $rombel->version + 1,
            ]);
        });

        $rombel = $this->baseQuery()->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Class successfully updated!',
            'data' => $this->serialize($rombel),
        ], 200);
    }

    /**
     * Soft delete a class that has no membership history (DEC-29 D6).
     */
    public function destroy(Request $request, $id)
    {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
        ], [
            'expected_version.required' => 'Expected version is required for concurrency control.',
            'expected_version.integer' => 'Expected version must be an integer.',
            'expected_version.min' => 'Expected version must be at least 1.',
        ]);

        $expectedVersion = (int) $validated['expected_version'];

        $snapshot = SchoolRombel::find($id);
        if (! $snapshot) {
            return response()->json(['message' => 'Class not found!'], 404);
        }

        DB::transaction(function () use ($snapshot, $expectedVersion) {
            $year = SchoolYear::withTrashed()
                ->whereKey($snapshot->school_year_id)
                ->lockForUpdate()
                ->first();

            if (! $year || $year->trashed()) {
                throw ValidationException::withMessages([
                    'school_year_id' => ['The academic year of this class is not available.'],
                ]);
            }

            if ($year->locked_at !== null) {
                throw ValidationException::withMessages([
                    'school_year_id' => ['The academic year of this class is locked. Unlock it before changing classes.'],
                ]);
            }

            $rombel = SchoolRombel::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();

            if ((int) $rombel->school_year_id !== (int) $snapshot->school_year_id) {
                $this->abortConflict($rombel, 'Conflict: The class was modified by another operator. Please reload.');
            }

            if ((int) $rombel->version !== $expectedVersion) {
                $this->abortConflict($rombel, 'Conflict: The class record has been updated by another operator. Please reload.');
            }

            $hasHistory = StudentRombel::withTrashed()
                ->where('school_rombel', $rombel->id)
                ->exists();

            if ($hasHistory) {
                throw ValidationException::withMessages([
                    'id' => ['This class already has membership history and cannot be deleted.'],
                ]);
            }

            $hasTeachingUsage = TeachingAssignment::where('school_rombel_id', $rombel->id)->exists()
                || TeachingScheduleException::where('school_rombel_id', $rombel->id)->exists();

            if ($hasTeachingUsage) {
                throw ValidationException::withMessages([
                    'id' => ['This class already has teaching assignments or schedule exceptions and cannot be deleted.'],
                ]);
            }

            $rombel->version = (int) $rombel->version + 1;
            $rombel->save();
            $rombel->delete();
        });

        return new StatusResource(true, 'Class successfully deleted!', null);
    }

    public function batchDelete(Request $request): StatusResource
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.expected_version' => ['required', 'integer', 'min:1'],
        ], [
            'items.required' => 'Select at least one class.',
            'items.min' => 'Select at least one class.',
            'items.*.expected_version.required' => 'Expected version is required for concurrency control.',
        ]);

        $items = $validated['items'];

        $count = DB::transaction(function () use ($items) {
            $processed = 0;
            foreach ($items as $item) {
                $id = (int) $item['id'];
                $expectedVersion = (int) $item['expected_version'];

                $snapshot = SchoolRombel::find($id);
                if (! $snapshot) {
                    continue;
                }

                $year = SchoolYear::withTrashed()
                    ->whereKey($snapshot->school_year_id)
                    ->lockForUpdate()
                    ->first();

                if (! $year || $year->trashed()) {
                    throw ValidationException::withMessages([
                        'school_year_id' => ["The academic year for class {$snapshot->name} is not available."],
                    ]);
                }

                if ($year->locked_at !== null) {
                    throw ValidationException::withMessages([
                        'school_year_id' => ["The academic year for class {$snapshot->name} is locked. Unlock it before deleting classes."],
                    ]);
                }

                $rombel = SchoolRombel::whereKey($snapshot->id)->lockForUpdate()->firstOrFail();

                if ((int) $rombel->version !== $expectedVersion) {
                    $this->abortConflict($rombel, "Conflict: Class {$rombel->name} record has been updated by another operator. Please reload.");
                }

                $hasHistory = StudentRombel::withTrashed()
                    ->where('school_rombel', $rombel->id)
                    ->exists();

                if ($hasHistory) {
                    throw ValidationException::withMessages([
                        'id' => ["Class {$rombel->name} already has membership history and cannot be deleted."],
                    ]);
                }

                $hasTeachingUsage = TeachingAssignment::where('school_rombel_id', $rombel->id)->exists()
                    || TeachingScheduleException::where('school_rombel_id', $rombel->id)->exists();

                if ($hasTeachingUsage) {
                    throw ValidationException::withMessages([
                        'id' => ["Class {$rombel->name} already has teaching assignments or schedule exceptions and cannot be deleted."],
                    ]);
                }

                $rombel->version = (int) $rombel->version + 1;
                $rombel->save();
                $rombel->delete();

                $processed++;
            }

            return $processed;
        });

        return new StatusResource(true, "Successfully deleted {$count} class(es).", ['deleted_count' => $count]);
    }

    /**
     * Ekspor daftar rombongan belajar ke Excel (.xlsx) atau CSV (.csv).
     */
    public function export(Request $request, SchoolRombelImportExportService $service): StreamedResponse
    {
        $this->authorize('viewAny', SchoolRombel::class);

        $validated = $request->validate([
            'school_year_id' => ['nullable', 'integer'],
            'school_level_id' => ['nullable', 'integer'],
            'student_major_id' => ['nullable', 'integer'],
            'teacher_search' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', 'string', 'in:xlsx,csv'],
            'lang' => ['nullable', 'string', 'in:id,en'],
        ]);

        $format = strtolower((string) ($validated['format'] ?? 'xlsx'));
        $lang = strtolower((string) ($validated['lang'] ?? 'id')) === 'en' ? 'en' : 'id';
        $dateSuffix = now()->format('Ymd_His');

        if ($format === 'csv') {
            $csvContent = $service->exportCsv($validated, $lang);
            $fileName = ($lang === 'id' ? 'Data-Rombongan-Belajar-' : 'Classes-') . "{$dateSuffix}.csv";

            return response()->streamDownload(
                fn () => print $csvContent,
                $fileName,
                [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Cache-Control' => 'no-cache, private',
                ]
            );
        }

        $xlsxContent = $service->exportXlsx($validated, $lang);
        $fileName = ($lang === 'id' ? 'Data-Rombongan-Belajar-' : 'Classes-') . "{$dateSuffix}.xlsx";

        return response()->streamDownload(
            fn () => print $xlsxContent,
            $fileName,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-cache, private',
            ]
        );
    }

    /**
     * Unduh template impor rombongan belajar (.xlsx / .csv).
     */
    public function importTemplate(Request $request, SchoolRombelImportExportService $service): StreamedResponse
    {
        $this->authorize('viewAny', SchoolRombel::class);

        $lang = strtolower((string) $request->query('lang', 'id')) === 'en' ? 'en' : 'id';
        $format = strtolower((string) $request->query('format', 'xlsx'));
        $schoolYearId = $request->query('school_year_id') ? (int) $request->query('school_year_id') : null;

        if ($format === 'csv') {
            return response()->streamDownload(
                fn () => print $service->templateCsv($lang, $schoolYearId),
                'rombongan-belajar-import-template.csv',
                [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Cache-Control' => 'no-cache, private',
                ]
            );
        }

        return response()->streamDownload(
            fn () => print $service->templateXlsx($lang, $schoolYearId),
            'rombongan-belajar-import-template.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-cache, private',
            ]
        );
    }

    /**
     * Proses impor rombongan belajar dari berkas Excel / CSV.
     */
    public function import(Request $request, SchoolRombelImportExportService $service): JsonResponse
    {
        $this->authorize('create', SchoolRombel::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'school_year_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $result = $service->import(
                $request->file('file'),
                isset($validated['school_year_id']) ? (int) $validated['school_year_id'] : null
            );

            return response()->json($result, $result['success'] ? 200 : 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 422);
        }
    }

    private function baseQuery(): Builder
    {
        return SchoolRombel::query()
            ->with(self::LIST_RELATIONS)
            ->withCount([
                'studentRombels as memberships_count' => fn (Builder $query) => $query->withTrashed(),
                'studentRombels as active_members_count' => fn (Builder $query) => $query
                    ->whereNull('deleted_at')
                    ->whereNull('archived_at')
                    ->where(fn (Builder $inner) => $inner
                        ->where('student_status_id', 1)
                        ->orWhereNull('student_status_id'))
                    ->whereNull('latest_student_exit_date'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SchoolRombel $rombel): array
    {
        $year = $rombel->schoolYear;
        $level = $rombel->schoolLevel;
        $major = $rombel->studentMajor;
        $employee = $rombel->classTeachEmployee;
        $legacyTeacher = $rombel->classTeach;

        return [
            'id' => $rombel->id,
            'name' => $rombel->name,
            'version' => (int) $rombel->version,
            'school_year_id' => $rombel->school_year_id,
            'school_year' => $year === null ? null : [
                'id' => $year->id,
                'name' => $year->name,
                'start_date' => $year->start_date?->toDateString(),
                'end_date' => $year->end_date?->toDateString(),
                'is_locked' => $year->locked_at !== null,
            ],
            'school_level_id' => $rombel->school_level_id,
            'school_level' => $level === null ? null : [
                'id' => $level->id,
                'name' => $level->name,
            ],
            'student_major_id' => $rombel->student_major_id,
            'student_major' => $major === null ? null : [
                'id' => $major->id,
                'name' => $major->name,
            ],
            'class_teach_employee_id' => $rombel->class_teach_employee_id,
            'class_teach_employee' => $employee === null ? null : [
                'id' => $employee->id,
                'name' => $employee->name,
            ],
            'class_teach_id' => $rombel->class_teach_id,
            'class_teach' => $legacyTeacher === null ? null : [
                'id' => $legacyTeacher->id,
                'name' => $legacyTeacher->name,
            ],
            'memberships_count' => (int) ($rombel->memberships_count ?? 0),
            'active_members_count' => (int) ($rombel->active_members_count ?? 0),
            'created_at' => $rombel->created_at,
            'updated_at' => $rombel->updated_at,
        ];
    }

    private function nameExists(int $schoolYearId, string $name, ?int $ignoreId = null): bool
    {
        return SchoolRombel::query()
            ->where('school_year_id', $schoolYearId)
            ->when($ignoreId !== null, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();
    }

    private function resolveTeacher(?int $employeeId): ?Employee
    {
        if ($employeeId === null) {
            return null;
        }

        $employee = Employee::query()
            ->eligibleHomeroomTeacher()
            ->whereKey($employeeId)
            ->first();

        if ($employee === null) {
            throw ValidationException::withMessages([
                'class_teach_employee_id' => ['The selected homeroom teacher is not an active teacher.'],
            ]);
        }

        return $employee;
    }

    private function abortConflict(SchoolRombel $rombel, string $message): void
    {
        $rombel = $this->baseQuery()->find($rombel->id) ?? $rombel;

        abort(response()->json([
            'message' => $message,
            'current_version' => (int) $rombel->version,
            'data' => $this->serialize($rombel),
        ], 409));
    }
}
