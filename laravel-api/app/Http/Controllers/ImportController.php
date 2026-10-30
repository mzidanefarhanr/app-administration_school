<?php

namespace App\Http\Controllers;

use App\Exceptions\ImportBatchException;
use App\Http\Requests\StoreStudentImportRequest;
use App\Models\ImportBatch;
use App\Services\StudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor siswa CSV (Fase 05a, DEC-25).
 *
 * Endpoint batch membutuhkan `students.import`; unduh template cukup
 * `students.view` karena isinya hanya header. Batch hanya dapat diakses oleh
 * pembuatnya; pengguna lain menerima 404 agar keberadaan batch tidak bocor.
 */
class ImportController extends Controller
{
    public function __construct(private readonly StudentImportService $students) {}

    public function studentsTemplate(): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print $this->students->templateCsv(),
            'students-import-template.csv',
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

    public function show(ImportBatch $batch): JsonResponse
    {
        $this->authorizeBatch($batch);

        return response()->json([
            'success' => true,
            'message' => 'Import batch found.',
            'data' => $this->summary($batch),
        ], 200);
    }

    public function commit(ImportBatch $batch): JsonResponse
    {
        $this->authorizeBatch($batch);

        try {
            $batch = $this->students->commit($batch);
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

    public function errors(ImportBatch $batch): StreamedResponse|JsonResponse
    {
        $this->authorizeBatch($batch);

        $disk = Storage::disk((string) config('imports.disk', 'local'));

        if ($batch->errors_path === null || ! $disk->exists($batch->errors_path)) {
            return response()->json([
                'success' => false,
                'message' => 'No error report is available for this batch.',
            ], 404);
        }

        return response()->streamDownload(
            fn () => print $disk->get($batch->errors_path),
            'students-import-errors-'.$batch->token.'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    private function authorizeBatch(ImportBatch $batch): void
    {
        abort_unless(
            $batch->domain === 'students'
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
        $ttl = (int) config('imports.students.batch_ttl_minutes', 120);

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
