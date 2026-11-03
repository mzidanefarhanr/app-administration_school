<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeachingReportIndexRequest;
use App\Services\TeachingReportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeachingReportController extends Controller
{
    public function __construct(
        private readonly TeachingReportService $service
    ) {}

    public function missingJournals(TeachingReportIndexRequest $request): JsonResponse
    {
        $result = $this->service->missingJournalsReport($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Missing teaching journals report retrieved.',
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function exportMissingJournals(TeachingReportIndexRequest $request): StreamedResponse
    {
        return $this->service->exportMissingJournalsCsv($request->validated(), $request->user());
    }
}
