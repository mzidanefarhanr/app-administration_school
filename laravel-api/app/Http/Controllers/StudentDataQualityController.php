<?php

namespace App\Http\Controllers;

use App\Http\Requests\DataQualityStudentIssueRequest;
use App\Http\Requests\DataQualityStudentSummaryRequest;
use App\Http\Requests\ReviewStudentDataQualityIssueRequest;
use App\Services\DataQuality\ReviewService;
use App\Services\DataQuality\StudentCompletenessInspector;
use Illuminate\Http\JsonResponse;

class StudentDataQualityController extends Controller
{
    public function __construct(
        private readonly StudentCompletenessInspector $inspector,
        private readonly ReviewService $reviews,
    ) {}

    public function summary(DataQualityStudentSummaryRequest $request): JsonResponse
    {
        $summary = $this->inspector->summary((int) $request->validated('school_year_id'));

        return response()->json([
            'success' => true,
            'message' => 'Student data quality summary retrieved successfully.',
            'data' => $summary,
        ], 200);
    }

    public function issues(DataQualityStudentIssueRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $paginator = $this->inspector->issues((int) $validated['school_year_id'], $validated);

        return response()->json([
            'success' => true,
            'message' => 'Student data quality issues retrieved successfully.',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], 200);
    }

    public function review(ReviewStudentDataQualityIssueRequest $request, string $issueKey): JsonResponse
    {
        $review = $this->reviews->reviewStudent(
            $request->validated(),
            $issueKey,
            (int) $request->user()->id
        );

        return response()->json($review, 200);
    }
}
