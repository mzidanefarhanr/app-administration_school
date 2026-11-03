<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeachingJournalStoreRequest;
use App\Http\Requests\TeachingJournalUpdateRequest;
use App\Models\TeachingJournal;
use App\Services\TeachingSessionJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachingJournalController extends Controller
{
    public function __construct(
        private readonly TeachingSessionJournalService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_year_id' => ['nullable', 'integer', 'min:1'],
            'semester_id' => ['nullable', 'integer', 'min:1'],
            'school_rombel_id' => ['nullable', 'integer', 'min:1'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'employee_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'method' => ['nullable', 'string'],
            'source_type' => ['nullable', 'string', 'in:regular,rescheduled'],
            'scope' => ['nullable', 'string', 'in:mine,all'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->service->listJournals($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Teaching journals retrieved.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function show(Request $request, TeachingJournal $teachingJournal): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Teaching journal retrieved.',
            'data' => $this->service->showJournal($teachingJournal, $request->user()),
        ]);
    }

    public function store(TeachingJournalStoreRequest $request): JsonResponse
    {
        $result = $this->service->recordJournal($request->validated(), $request->user());
        $statusCode = $result['created'] ? 201 : 200;

        return response()->json([
            'success' => true,
            'message' => $result['idempotent_replay']
                ? 'Teaching journal already recorded (idempotent replay).'
                : 'Teaching journal recorded.',
            'idempotent_replay' => $result['idempotent_replay'],
            'data' => $result['journal'],
        ], $statusCode);
    }

    public function update(TeachingJournalUpdateRequest $request, TeachingJournal $teachingJournal): JsonResponse
    {
        $updated = $this->service->updateJournal($teachingJournal, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Teaching journal updated.',
            'data' => $updated,
        ]);
    }
}
