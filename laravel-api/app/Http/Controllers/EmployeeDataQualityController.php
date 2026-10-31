<?php

namespace App\Http\Controllers;

use App\Http\Requests\DataQualityEmployeeIssueRequest;
use App\Http\Requests\DataQualityEmployeeSummaryRequest;
use App\Http\Requests\ReviewEmployeeDataQualityIssueRequest;
use App\Services\DataQuality\EmployeeCompletenessInspector;
use App\Services\DataQuality\ReviewService;
use Illuminate\Http\JsonResponse;

class EmployeeDataQualityController extends Controller
{
    public function __construct(
        private readonly EmployeeCompletenessInspector $inspector,
        private readonly ReviewService $reviews,
    ) {}

    public function summary(DataQualityEmployeeSummaryRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Employee data quality summary retrieved successfully.',
            'data' => $this->inspector->summary(),
        ], 200);
    }

    public function issues(DataQualityEmployeeIssueRequest $request): JsonResponse
    {
        $paginator = $this->inspector->issues($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee data quality issues retrieved successfully.',
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

    public function review(ReviewEmployeeDataQualityIssueRequest $request, string $issueKey): JsonResponse
    {
        $review = $this->reviews->reviewEmployee(
            $request->validated(),
            $issueKey,
            (int) $request->user()->id
        );

        return response()->json($review, 200);
    }
}
