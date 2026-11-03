<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeachingAssignmentStoreRequest;
use App\Http\Requests\TeachingAssignmentUpdateRequest;
use App\Models\TeachingAssignment;
use App\Models\TeachingSchedule;
use App\Services\TeachingScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachingAssignmentController extends Controller
{
    public function __construct(private readonly TeachingScheduleService $service) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'school_year_id' => ['nullable', 'integer'],
            'semester_id' => ['nullable', 'integer'],
            'school_rombel_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = TeachingAssignment::query()
            ->with([
                'schoolYear:id,name,start_date,end_date,locked_at',
                'semester:id,name,name_other',
                'schoolRombel:id,name,school_year_id,school_level_id,student_major_id',
                'schoolRombel.schoolLevel:id,name',
                'schoolRombel.studentMajor:id,name',
                'subject:id,name,description',
                'employee:id,name,nuptk,subject_id,employee_type_id,exit_date',
                'employee.subject:id,name',
            ])
            ->withCount([
                'schedules',
                'schedules as active_schedules_count' => fn ($q) => $q->where('status', TeachingSchedule::STATUS_ACTIVE),
            ])
            ->when($request->filled('school_year_id'), fn ($q) => $q->where('school_year_id', (int) $request->input('school_year_id')))
            ->when($request->filled('semester_id'), fn ($q) => $q->where('semester_id', (int) $request->input('semester_id')))
            ->when($request->filled('school_rombel_id'), fn ($q) => $q->where('school_rombel_id', (int) $request->input('school_rombel_id')))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', (int) $request->input('subject_id')))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', (int) $request->input('employee_id')))
            ->when(
                $request->filled('status') && $request->input('status') !== 'all',
                fn ($q) => $q->where('status', (string) $request->input('status'))
            )
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->input('search'));
                if ($term === '') {
                    return;
                }
                $like = '%'.$term.'%';
                $q->where(function ($sub) use ($like) {
                    $sub->whereHas('employee', fn ($eq) => $eq->where('name', 'like', $like)->orWhere('nuptk', 'like', $like))
                        ->orWhereHas('subject', fn ($sq) => $sq->where('name', 'like', $like))
                        ->orWhereHas('schoolRombel', fn ($rq) => $rq->where('name', 'like', $like));
                });
            })
            ->orderByDesc('school_year_id')
            ->orderBy('semester_id')
            ->orderBy('school_rombel_id')
            ->orderBy('subject_id')
            ->orderBy('id');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Teaching assignments retrieved.',
            'data' => collect($paginator->items())
                ->map(fn (TeachingAssignment $item) => $this->service->serializeAssignment($item))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(TeachingAssignmentStoreRequest $request): JsonResponse
    {
        $assignment = $this->service->createAssignment(
            $request->validated(),
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Teaching assignment created.',
            'data' => $this->service->serializeAssignment($assignment),
        ], 201);
    }

    public function update(TeachingAssignmentUpdateRequest $request, TeachingAssignment $teachingAssignment): JsonResponse
    {
        $assignment = $this->service->updateAssignment(
            $teachingAssignment,
            $request->validated(),
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Teaching assignment updated.',
            'data' => $this->service->serializeAssignment($assignment),
        ]);
    }

    public function destroy(TeachingAssignment $teachingAssignment): JsonResponse
    {
        $this->service->deleteAssignment($teachingAssignment);

        return response()->json([
            'success' => true,
            'message' => 'Teaching assignment deleted.',
            'data' => null,
        ]);
    }
}
