<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeachingScheduleExceptionStoreRequest;
use App\Http\Requests\TeachingScheduleExceptionUpdateRequest;
use App\Models\TeachingScheduleException;
use App\Services\TeachingScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachingScheduleExceptionController extends Controller
{
    public function __construct(private readonly TeachingScheduleService $service) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'school_year_id' => ['nullable', 'integer'],
            'semester_id' => ['nullable', 'integer'],
            'exception_type' => ['nullable', 'string', 'max:30'],
            'school_rombel_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = TeachingScheduleException::query()
            ->with([
                'schoolYear:id,name,start_date,end_date,locked_at',
                'semester:id,name,name_other',
                'schoolRombel:id,name,school_year_id',
                'schedule.assignment.schoolRombel:id,name',
                'schedule.assignment.subject:id,name',
                'schedule.assignment.employee:id,name,nuptk',
                'substituteEmployee:id,name,nuptk',
            ])
            ->when($request->filled('school_year_id'), fn ($q) => $q->where('school_year_id', (int) $request->input('school_year_id')))
            ->when($request->filled('semester_id'), fn ($q) => $q->where('semester_id', (int) $request->input('semester_id')))
            ->when(
                $request->filled('exception_type') && $request->input('exception_type') !== 'all',
                fn ($q) => $q->where('exception_type', (string) $request->input('exception_type'))
            )
            ->when($request->filled('school_rombel_id'), function ($q) use ($request) {
                $rombelId = (int) $request->input('school_rombel_id');
                $q->where(function ($sub) use ($rombelId) {
                    $sub->where('school_rombel_id', $rombelId)
                        ->orWhere(function ($h) {
                            $h->where('exception_type', TeachingScheduleException::TYPE_HOLIDAY)
                                ->whereNull('school_rombel_id');
                        });
                });
            })
            ->when($request->filled('employee_id'), function ($q) use ($request) {
                $employeeId = (int) $request->input('employee_id');
                $q->where(function ($sub) use ($employeeId) {
                    $sub->where('substitute_employee_id', $employeeId)
                        ->orWhereHas('schedule.assignment', fn ($aq) => $aq->where('employee_id', $employeeId));
                });
            })
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('exception_date', '>=', (string) $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('exception_date', '<=', (string) $request->input('date_to')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->input('search'));
                if ($term === '') {
                    return;
                }
                $like = '%'.$term.'%';
                $q->where(function ($sub) use ($like) {
                    $sub->where('reason', 'like', $like)
                        ->orWhereHas('schoolRombel', fn ($rq) => $rq->where('name', 'like', $like))
                        ->orWhereHas('schedule.assignment.subject', fn ($sq) => $sq->where('name', 'like', $like))
                        ->orWhereHas('schedule.assignment.employee', fn ($eq) => $eq->where('name', 'like', $like));
                });
            })
            ->orderByDesc('exception_date')
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Schedule exceptions retrieved.',
            'data' => collect($paginator->items())
                ->map(fn (TeachingScheduleException $item) => $this->service->serializeException($item))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(TeachingScheduleExceptionStoreRequest $request): JsonResponse
    {
        $exception = $this->service->createException(
            $request->validated(),
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Schedule exception created.',
            'data' => $this->service->serializeException($exception),
        ], 201);
    }

    public function update(
        TeachingScheduleExceptionUpdateRequest $request,
        TeachingScheduleException $teachingScheduleException
    ): JsonResponse {
        $exception = $this->service->updateException(
            $teachingScheduleException,
            $request->validated(),
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Schedule exception updated.',
            'data' => $this->service->serializeException($exception),
        ]);
    }

    public function destroy(TeachingScheduleException $teachingScheduleException): JsonResponse
    {
        $this->service->deleteException($teachingScheduleException);

        return response()->json([
            'success' => true,
            'message' => 'Schedule exception deleted.',
            'data' => null,
        ]);
    }
}
