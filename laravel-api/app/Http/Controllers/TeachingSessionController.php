<?php

namespace App\Http\Controllers;

use App\Services\TeachingSessionJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachingSessionController extends Controller
{
    public function __construct(
        private readonly TeachingSessionJournalService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_year_id' => ['required', 'integer', 'min:1'],
            'semester_id' => ['required', 'integer', 'min:1'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'school_rombel_id' => ['nullable', 'integer', 'min:1'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'employee_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:all,effective,recorded,missing,excluded'],
            'scope' => ['nullable', 'string', 'in:mine,all'],
        ]);

        $result = $this->service->listSessions($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Teaching sessions retrieved.',
            'data' => $result['data'],
            'summary' => $result['summary'],
            'meta' => $result['meta'],
        ]);
    }
}
