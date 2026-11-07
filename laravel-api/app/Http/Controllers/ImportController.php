<?php

namespace App\Http\Controllers;

use App\Exceptions\ImportBatchException;
use App\Http\Requests\StoreEmployeeImportRequest;
use App\Http\Requests\StoreStudentImportRequest;
use App\Models\ImportBatch;
use App\Services\EmployeeImportService;
use App\Services\StudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor CSV (Fase 05a/05b, DEC-25/DEC-26).
 *
 * Endpoint batch membutuhkan `students.import` atau `employees.import` sesuai
 * domain; unduh template cukup izin view karena isinya hanya header. Batch
 * hanya dapat diakses oleh pembuatnya dan domainnya; pengguna lain menerima
 * 404 agar keberadaan batch tidak bocor.
 */
class ImportController extends Controller
{
    public function __construct(
        private readonly StudentImportService $students,
        private readonly EmployeeImportService $employees,
    ) {}

    public function studentsTemplate(Request $request): StreamedResponse
    {
        $lang = strtolower((string) $request->query('lang', 'en')) === 'id' ? 'id' : 'en';
        $format = strtolower((string) $request->query('format', 'csv'));
        $isXlsx = $format === 'xlsx' || $format === 'excel';
        $baseName = 'students-import-template';

        if ($isXlsx) {
            return response()->streamDownload(
                fn () => print $this->students->templateXlsx(null, $lang),
                "{$baseName}.xlsx",
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'no-cache, private',
                ]
            );
        }

        return response()->streamDownload(
            fn () => print $this->students->templateCsv(null, $lang),
            "{$baseName}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function importStudents(StoreStudentImportRequest $request): JsonResponse
    {
        $result = $this->students->createBatch(
            $request->file('file'),
            (string) $request->input('mode'),
            (int) $request->user()->id
        );

        $batch = $result['batch'];
        $ready = $batch->status === ImportBatch::STATUS_READY;

        return response()->json([
            'success' => true,
            'message' => $ready
                ? 'Import preview ready.'
                : 'The file contains validation errors. Fix them and upload the file again.',
            'data' => array_merge($this->summary($batch), [
                'preview' => $result['preview'],
                'errors' => $result['errors'],
            ]),
        ], 201);
    }

    public function employeesTemplate(Request $request): StreamedResponse
    {
        $type = $request->query('type');
        $lang = strtolower((string) $request->query('lang', 'en')) === 'id' ? 'id' : 'en';
        $format = strtolower((string) $request->query('format', 'csv'));
        $isXlsx = $format === 'xlsx' || $format === 'excel';

        $baseName = ($type === 'Pendidik' || strcasecmp((string) $type, 'teacher') === 0 || strcasecmp((string) $type, 'teachers') === 0 || strcasecmp((string) $type, 'guru') === 0)
            ? 'teachers-import-template'
            : (($type === 'Tenaga Kependidikan' || strcasecmp((string) $type, 'staff') === 0 || strcasecmp((string) $type, 'employees') === 0 || strcasecmp((string) $type, 'karyawan') === 0)
                ? 'employees-import-template'
                : 'employee-import-template');

        if ($isXlsx) {
            return response()->streamDownload(
                fn () => print $this->employees->templateXlsx($type, $lang),
                "{$baseName}.xlsx",
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'no-cache, private',
                ]
            );
        }

        return response()->streamDownload(
            fn () => print $this->employees->templateCsv($type, $lang),
            "{$baseName}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function importEmployees(StoreEmployeeImportRequest $request): JsonResponse
    {
        $result = $this->employees->createBatch(
            $request->file('file'),
            (string) $request->input('mode'),
            (int) $request->user()->id
        );

        $batch = $result['batch'];
        $ready = $batch->status === ImportBatch::STATUS_READY;

        return response()->json([
            'success' => true,
            'message' => $ready
                ? 'Import preview ready.'
                : 'The file contains validation errors. Fix them and upload the file again.',
            'data' => array_merge($this->summary($batch), [
                'preview' => $result['preview'],
                'errors' => $result['errors'],
            ]),
        ], 201);
    }

    public function show(ImportBatch $batch): JsonResponse
    {
        $this->authorizeBatch($batch, 'students');

        return response()->json([
            'success' => true,
            'message' => 'Import batch found.',
            'data' => $this->summary($batch),
        ], 200);
    }

    public function showEmployee(ImportBatch $batch): JsonResponse
    {
        $this->authorizeBatch($batch, 'employees');

        return response()->json([
            'success' => true,
            'message' => 'Import batch found.',
            'data' => $this->summary($batch),
        ], 200);
    }

    public function commit(ImportBatch $batch): JsonResponse
    {
        $this->authorizeBatch($batch, 'students');

        return $this->commitBatch($batch, $this->students);
    }

    public function commitEmployee(ImportBatch $batch): JsonResponse
    {
        $this->authorizeBatch($batch, 'employees');

        return $this->commitBatch($batch, $this->employees);
    }

    public function errors(ImportBatch $batch): StreamedResponse|JsonResponse
    {
        return $this->batchErrors($batch, 'students');
    }

    public function errorsEmployee(ImportBatch $batch): StreamedResponse|JsonResponse
    {
        return $this->batchErrors($batch, 'employees');
    }

    private function commitBatch(ImportBatch $batch, StudentImportService|EmployeeImportService $service): JsonResponse
    {
        try {
            $batch = $service->commit($batch);
        } catch (ImportBatchException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => $exception->payload(),
            ], $exception->statusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'Import committed.',
            'data' => $this->summary($batch),
        ], 200);
    }

    private function batchErrors(ImportBatch $batch, string $domain): StreamedResponse|JsonResponse
    {
        $this->authorizeBatch($batch, $domain);

        $disk = Storage::disk((string) config('imports.disk', 'local'));

        if ($batch->errors_path === null || ! $disk->exists($batch->errors_path)) {
            return response()->json([
                'success' => false,
                'message' => 'No error report is available for this batch.',
            ], 404);
        }

        return response()->streamDownload(
            fn () => print $disk->get($batch->errors_path),
            $domain.'-import-errors-'.$batch->token.'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    private function authorizeBatch(ImportBatch $batch, string $domain): void
    {
        abort_unless(
            $batch->domain === $domain
                && $batch->created_by !== null
                && (int) $batch->created_by === (int) request()->user()->id,
            404
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ImportBatch $batch): array
    {
        $prefix = $batch->domain === 'employees' ? 'employees' : 'students';
        $ttl = (int) config('imports.'.$prefix.'.batch_ttl_minutes', 120);

        return [
            'token' => $batch->token,
            'domain' => $batch->domain,
            'mode' => $batch->mode,
            'status' => $batch->status,
            'file_name' => $batch->file_name,
            'total_rows' => (int) $batch->total_rows,
            'valid_rows' => (int) $batch->valid_rows,
            'invalid_rows' => (int) $batch->invalid_rows,
            'committed_rows' => (int) $batch->committed_rows,
            'has_errors' => $batch->errors_path !== null,
            'created_at' => optional($batch->created_at)->toIso8601String(),
            'committed_at' => optional($batch->committed_at)->toIso8601String(),
            'expires_at' => optional($batch->created_at)?->copy()->addMinutes($ttl)->toIso8601String(),
        ];
    }
}
