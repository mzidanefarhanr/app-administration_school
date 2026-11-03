<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\SchoolStatisticsReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolReportController extends Controller
{
    public function __construct(
        protected SchoolStatisticsReportService $reportService
    ) {}

    public function schoolStatistics(Request $request): JsonResponse
    {
        $limitYears = (int) $request->query('limit_years', 5);
        $data = $this->reportService->getMultiYearTrends($limitYears);

        return response()->json(['data' => $data]);
    }

    public function demographics(Request $request): JsonResponse
    {
        $schoolYearId = $request->query('school_year_id') ? (int) $request->query('school_year_id') : null;
        $data = $this->reportService->getDemographics($schoolYearId);

        return response()->json(['data' => $data]);
    }

    public function dapodikReadiness(Request $request): JsonResponse
    {
        $schoolYearId = $request->query('school_year_id') ? (int) $request->query('school_year_id') : null;
        $data = $this->reportService->getDapodikReadiness($schoolYearId);

        return response()->json(['data' => $data]);
    }

    public function exportSchoolStatistics(Request $request): StreamedResponse
    {
        $limitYears = (int) $request->query('limit_years', 5);

        return $this->reportService->exportMultiYearTrendsCsv($limitYears);
    }
}
