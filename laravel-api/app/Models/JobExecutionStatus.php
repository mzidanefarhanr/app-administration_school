<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobExecutionStatus extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'job_id',
        'user_id',
        'type',
        'status',
        'progress_percentage',
        'processed_records',
        'total_records',
        'idempotency_key',
        'error_message',
        'result_payload',
    ];

    protected $casts = [
        'progress_percentage' => 'integer',
        'processed_records' => 'integer',
        'total_records' => 'integer',
        'result_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsProcessing(): self
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
        ]);

        return $this;
    }

    public function updateProgress(int $processed, int $total, ?int $percentage = null): self
    {
        $calcPercentage = $total > 0 ? (int) min(100, round(($processed / $total) * 100)) : 0;

        $this->update([
            'status' => self::STATUS_PROCESSING,
            'processed_records' => $processed,
            'total_records' => $total,
            'progress_percentage' => $percentage ?? $calcPercentage,
        ]);

        return $this;
    }

    public function markAsCompleted(?array $result = null): self
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'progress_percentage' => 100,
            'result_payload' => $result ?? $this->result_payload,
        ]);

        return $this;
    }

    public function markAsFailed(string $errorMessage): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);

        return $this;
    }
}
