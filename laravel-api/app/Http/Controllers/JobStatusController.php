<?php

namespace App\Http\Controllers;

use App\Services\Jobs\JobTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobStatusController extends Controller
{
    public function __construct(
        protected JobTrackingService $trackingService
    ) {}

    /**
     * Mengambil status dan progres pekerjaan latar belakang.
     *
     * Subfase 12b (DEC-46 D8):
     * Endpoint terpadu untuk polling status pekerjaan asinkron.
     */
    public function show(Request $request, string $jobId): JsonResponse
    {
        $status = $this->trackingService->getJobStatus($jobId, $request->user());

        if (! $status) {
            return response()->json([
                'success' => false,
                'message' => 'Status pekerjaan tidak ditemukan.',
                'errors' => [],
            ], 404);
        }

        // Otorisasi kepemilikan: hanya pemilik pekerjaan atau pengguna berwewenang manajemen
        $user = $request->user();
        $isOwner = $status->user_id === null || ($user && $user->id === $status->user_id);
        $canManage = $user && (
            $user->hasAbility('access.manage') ||
            $user->hasAbility('students.manage') ||
            $user->hasAbility('employees.manage') ||
            $user->hasAbility('tasks.view')
        );

        if (! $isOwner && ! $canManage) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk memantau pekerjaan ini.',
                'errors' => [],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $status->job_id,
                'type' => $status->type,
                'status' => $status->status,
                'progress_percentage' => $status->progress_percentage,
                'processed_records' => $status->processed_records,
                'total_records' => $status->total_records,
                'error_message' => $status->error_message,
                'result_payload' => $status->result_payload,
                'created_at' => $status->created_at?->toIso8601String(),
                'updated_at' => $status->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
