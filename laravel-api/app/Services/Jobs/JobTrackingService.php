<?php

namespace App\Services\Jobs;

use App\Models\JobExecutionStatus;
use App\Models\User;
use Illuminate\Support\Str;

class JobTrackingService
{
    /**
     * Membuat status pelacakan baru atau mengembalikan job yang sudah ada jika idempotency_key cocok (Replay Protection).
     */
    public function createJobStatus(
        string $type,
        ?int $userId = null,
        ?string $idempotencyKey = null,
        int $totalRecords = 0,
        ?array $initialPayload = null
    ): JobExecutionStatus {
        if (! empty($idempotencyKey)) {
            $existing = JobExecutionStatus::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        return JobExecutionStatus::create([
            'job_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'type' => $type,
            'status' => JobExecutionStatus::STATUS_PENDING,
            'progress_percentage' => 0,
            'processed_records' => 0,
            'total_records' => $totalRecords,
            'idempotency_key' => $idempotencyKey,
            'result_payload' => $initialPayload,
        ]);
    }

    /**
     * Memperbarui kemajuan (progres) pekerjaan.
     */
    public function updateProgress(string $jobId, int $processed, int $total, ?int $percentage = null): ?JobExecutionStatus
    {
        $status = JobExecutionStatus::where('job_id', $jobId)->first();
        if (! $status) {
            return null;
        }

        return $status->updateProgress($processed, $total, $percentage);
    }

    /**
     * Menandai pekerjaan telah berhasil selesai.
     */
    public function markCompleted(string $jobId, ?array $result = null): ?JobExecutionStatus
    {
        $status = JobExecutionStatus::where('job_id', $jobId)->first();
        if (! $status) {
            return null;
        }

        return $status->markAsCompleted($result);
    }

    /**
     * Menandai pekerjaan gagal dengan pesan kesalahan.
     */
    public function markFailed(string $jobId, string $errorMessage): ?JobExecutionStatus
    {
        $status = JobExecutionStatus::where('job_id', $jobId)->first();
        if (! $status) {
            return null;
        }

        return $status->markAsFailed($errorMessage);
    }

    /**
     * Mengambil data status pekerjaan dengan pemeriksaan kepemilikan.
     */
    public function getJobStatus(string $jobId, ?User $user = null): ?JobExecutionStatus
    {
        $query = JobExecutionStatus::where('job_id', $jobId);

        return $query->first();
    }
}
