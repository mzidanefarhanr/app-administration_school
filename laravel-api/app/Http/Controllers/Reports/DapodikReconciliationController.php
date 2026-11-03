<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\DapodikReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DapodikReconciliationController extends Controller
{
    public function __construct(
        protected DapodikReconciliationService $reconciliationService
    ) {}

    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'school_year_id' => 'nullable|integer|exists:school_years,id',
        ]);

        $yearId = $request->input('school_year_id') ? (int) $request->input('school_year_id') : null;
        $result = $this->reconciliationService->reconcileCsv($request->file('file'), $yearId);

        return response()->json(['data' => $result]);
    }

    public function exportReconciliation(Request $request): StreamedResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'school_year_id' => 'nullable|integer|exists:school_years,id',
        ]);

        $yearId = $request->input('school_year_id') ? (int) $request->input('school_year_id') : null;

        return $this->reconciliationService->exportReconciliationFromUpload($request->file('file'), $yearId);
    }
}
