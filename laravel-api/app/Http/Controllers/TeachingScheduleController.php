<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeachingScheduleStoreRequest;
use App\Http\Requests\TeachingScheduleUpdateRequest;
use App\Models\TeachingSchedule;
use App\Services\TeachingScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachingScheduleController extends Controller
{
    public function __construct(private readonly TeachingScheduleService $service) {}

    public function index(Request $request): JsonResponse
    {
        $viewMode = (string) $request->input('view_mode', 'list');
        $perPage = min(max((int) $request->input('per_page', 50), 5), 300);

        $query = TeachingSchedule::query()
            ->with([
                'assignment.schoolYear:id,name,start_date,end_date,locked_at',
                'assignment.semester:id,name,name_other',
                'assignment.schoolRombel:id,name,school_year_id,school_level_id,student_major_id',
                'assignment.schoolRombel.schoolLevel:id,name',
                'assignment.subject:id,name,description',
                'assignment.employee:id,name,nuptk,subject_id',
            ])
            ->withCount('exceptions')
            ->when($request->filled('teaching_assignment_id'), fn ($q) => $q->where('teaching_assignment_id', (int) $request->input('teaching_assignment_id')))
            ->when($request->filled('day_of_week'), fn ($q) => $q->where('day_of_week', (int) $request->input('day_of_week')))
            ->when(
                $request->filled('status') && $request->input('status') !== 'all',
                fn ($q) => $q->where('status', (string) $request->input('status'))
            )
            ->when($request->filled('room'), function ($q) use ($request) {
                $roomNorm = TeachingSchedule::normalizeRoom((string) $request->input('room'));
                if ($roomNorm !== null) {
                    $q->where('room_normalized', 'like', '%'.$roomNorm.'%');
                }
            })
            ->whereHas('assignment', function ($aq) use ($request) {
                $aq->when($request->filled('school_year_id'), fn ($q) => $q->where('school_year_id', (int) $request->input('school_year_id')))
                    ->when($request->filled('semester_id'), fn ($q) => $q->where('semester_id', (int) $request->input('semester_id')))
                    ->when($request->filled('school_rombel_id'), fn ($q) => $q->where('school_rombel_id', (int) $request->input('school_rombel_id')))
                    ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', (int) $request->input('subject_id')))
                    ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', (int) $request->input('employee_id')))
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
                    });
            })
            ->orderBy('day_of_week')
            ->orderBy('period_start')
            ->orderBy('start_time')
            ->orderBy('id');

        if ($viewMode === 'matrix') {
            $items = $query->limit(500)->get()
                ->map(fn (TeachingSchedule $slot) => $this->service->serializeSchedule($slot))
                ->values();

            $days = config('teaching.days_of_week', []);
            $matrixByDay = [];
            foreach ($days as $dayNum => $dayLabel) {
                $matrixByDay[] = [
                    'day_of_week' => (int) $dayNum,
                    'day_label' => $dayLabel,
                    'slots' => $items->where('day_of_week', (int) $dayNum)->values(),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Weekly timetable retrieved.',
                'data' => $items,
                'matrix' => $matrixByDay,
                'meta' => [
                    'current_page' => 1,
                    'per_page' => $items->count(),
                    'total' => $items->count(),
                    'last_page' => 1,
                ],
            ]);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Teaching schedules retrieved.',
            'data' => collect($paginator->items())
                ->map(fn (TeachingSchedule $slot) => $this->service->serializeSchedule($slot))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(TeachingScheduleStoreRequest $request): JsonResponse
    {
        $schedule = $this->service->createSchedule(
            $request->validated(),
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Teaching schedule slot created.',
            'data' => $this->service->serializeSchedule($schedule),
        ], 201);
    }

    public function update(TeachingScheduleUpdateRequest $request, TeachingSchedule $teachingSchedule): JsonResponse
    {
        $schedule = $this->service->updateSchedule(
            $teachingSchedule,
            $request->validated(),
            (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Teaching schedule slot updated.',
            'data' => $this->service->serializeSchedule($schedule),
        ]);
    }

    public function destroy(TeachingSchedule $teachingSchedule): JsonResponse
    {
        $this->service->deleteSchedule($teachingSchedule);

        return response()->json([
            'success' => true,
            'message' => 'Teaching schedule slot deleted.',
            'data' => null,
        ]);
    }
}
