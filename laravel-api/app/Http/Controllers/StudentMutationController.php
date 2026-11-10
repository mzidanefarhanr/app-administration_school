<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelStudentMutationRequest;
use App\Http\Requests\RecordStudentMutationInRequest;
use App\Http\Requests\RecordStudentMutationOutRequest;
use App\Http\Resources\StatusResource;
use App\Services\Academic\StudentMutationService;
use App\Services\Academic\StudentMutationReportService;
use App\Services\Academic\StudentMutationImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentMutationController extends Controller
{
    public function __construct(private readonly StudentMutationService $service) {}

    /**
     * Academic years, classes, and reference lists for mutation dialogs.
     */
    public function options(Request $request): StatusResource
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'school_year_id' => ['nullable', 'integer', 'min:1'],
        ], [
            'school_year_id.integer' => 'The academic year must be an integer identifier.',
        ]);

        $data = $this->service->options(
            isset($validated['school_year_id']) ? (int) $validated['school_year_id'] : null
        );

        return new StatusResource(true, 'Mutation options retrieved successfully.', $data);
    }

    /**
     * Read-only education school search by NPSN or name.
     */
    public function schools(Request $request): StatusResource
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ], [
            'search.max' => 'The school search term may not exceed 100 characters.',
        ]);

        $items = $this->service->schools($validated['search'] ?? null);

        return new StatusResource(true, 'Schools retrieved successfully.', [
            'items' => $items,
            'max_results' => StudentMutationService::MAX_SEARCH_RESULTS,
        ]);
    }

    /**
     * Mutation history for a school year.
     */
    public function index(Request $request): StatusResource
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'school_year_id' => ['required', 'integer', 'min:1'],
            'type' => ['nullable', 'in:in,out'],
            'status' => ['nullable', 'in:active,cancelled'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,20,25,50,100'],
        ], [
            'school_year_id.required' => 'The academic year is required.',
            'type.in' => 'The mutation type must be in or out.',
            'status.in' => 'The mutation status must be active or cancelled.',
            'search.max' => 'The search term may not exceed 100 characters.',
            'page.min' => 'The page must be at least 1.',
        ]);

        $data = $this->service->history([
            'school_year_id' => (int) $validated['school_year_id'],
            'type' => $validated['type'] ?? null,
            'status' => $validated['status'] ?? null,
            'search' => $validated['search'] ?? null,
            'page' => isset($validated['page']) ? (int) $validated['page'] : 1,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : StudentMutationService::PER_PAGE,
        ]);

        return new StatusResource(true, 'Mutation history retrieved successfully.', $data);
    }

    /**
     * Year-level recap: mutation in/out, graduated, retained.
     */
    public function recap(Request $request): StatusResource
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'school_year_id' => ['required', 'integer', 'min:1'],
        ], [
            'school_year_id.required' => 'The academic year is required.',
        ]);

        $data = $this->service->recap((int) $validated['school_year_id']);

        return new StatusResource(true, 'Mutation recap retrieved successfully.', $data);
    }

    /**
     * Per-student mutation timeline.
     */
    public function timeline(Request $request, $id): StatusResource
    {
        $this->authorize('students.view');

        $data = $this->service->timeline((int) $id);

        return new StatusResource(true, 'Student mutations retrieved successfully.', $data);
    }

    /**
     * Record a mutation-in and create a new class membership.
     */
    public function mutationIn(RecordStudentMutationInRequest $request, $id): JsonResponse
    {
        $result = $this->service->mutationIn((int) $id, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Mutation-in recorded successfully.',
            'data' => $result,
        ], 200);
    }

    /**
     * Record a mutation-out and close one class membership.
     */
    public function mutationOut(RecordStudentMutationOutRequest $request, $id): JsonResponse
    {
        $result = $this->service->mutationOut((int) $id, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Mutation-out recorded successfully.',
            'data' => $result,
        ], 200);
    }

    /**
     * Compensating cancellation; no log or membership is deleted.
     */
    public function cancel(CancelStudentMutationRequest $request, $id, $mutation): JsonResponse
    {
        $result = $this->service->cancel(
            (int) $id,
            (int) $mutation,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Mutation cancelled successfully.',
            'data' => $result,
        ], 200);
    }

    /**
     * Data laporan mutasi siswa (sesuai format PDF resmi).
     */
    public function report(Request $request, StudentMutationReportService $reportService): JsonResponse
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'school_year_id' => ['required', 'integer', 'min:1'],
            'month' => ['nullable', 'integer', 'between:0,12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $data = $reportService->getReportData(
            (int) $validated['school_year_id'],
            isset($validated['month']) ? (int) $validated['month'] : null,
            isset($validated['year']) ? (int) $validated['year'] : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Mutation report retrieved successfully.',
            'data' => $data,
        ], 200);
    }

    /**
     * Unduh laporan mutasi siswa dalam format Excel (.xlsx).
     */
    public function exportExcel(Request $request, StudentMutationReportService $reportService): StreamedResponse
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'school_year_id' => ['required', 'integer', 'min:1'],
            'month' => ['nullable', 'integer', 'between:0,12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $xlsxContent = $reportService->exportExcel(
            (int) $validated['school_year_id'],
            isset($validated['month']) ? (int) $validated['month'] : null,
            isset($validated['year']) ? (int) $validated['year'] : null
        );

        $month = $validated['month'] ?? date('n');
        $year = $validated['year'] ?? date('Y');
        $fileName = "Data-Mutasi-Siswa-{$year}-{$month}.xlsx";

        return response()->streamDownload(
            fn () => print $xlsxContent,
            $fileName,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-cache, private',
            ]
        );
    }

    /**
     * Unduh template impor mutasi siswa (Excel / CSV).
     */
    public function importTemplate(Request $request, StudentMutationImportService $importService): StreamedResponse
    {
        $this->authorize('students.view');

        $lang = strtolower((string) $request->query('lang', 'en')) === 'id' ? 'id' : 'en';
        $format = strtolower((string) $request->query('format', 'xlsx'));

        if ($format === 'csv') {
            return response()->streamDownload(
                fn () => print $importService->templateCsv($lang),
                'mutations-import-template.csv',
                [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Cache-Control' => 'no-cache, private',
                ]
            );
        }

        return response()->streamDownload(
            fn () => print $importService->templateXlsx($lang),
            'mutations-import-template.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-cache, private',
            ]
        );
    }

    /**
     * Impor berkas mutasi siswa (Excel / CSV).
     */
    public function import(Request $request, StudentMutationImportService $importService): JsonResponse
    {
        $this->authorize('students.manage');

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'school_year_id' => ['required', 'integer', 'min:1'],
        ]);

        $result = $importService->import(
            $request->file('file'),
            $request->user(),
            (int) $request->input('school_year_id')
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
