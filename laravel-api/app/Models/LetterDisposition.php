<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Disposisi surat masuk per penerima (Fase 07b, DEC-33).
 *
 * Tanpa soft delete dan tanpa hard delete; completed/cancelled tetap history.
 * Catatan, hasil tindak lanjut, dan alasan cancel tidak masuk audit.
 */
class LetterDisposition extends Model
{
    use HasFactory, Loggable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
    ];

    protected $guarded = ['id'];

    protected $table = 'letter_dispositions';

    /**
     * Allowlist audit; note, follow_up_note, dan cancel_reason tidak
     * diduplikasi ke activities.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'letter_id',
        'recipient_employee_id',
        'due_date',
        'status',
        'version',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $fillable = [
        'letter_id',
        'recipient_employee_id',
        'assigned_by',
        'due_date',
        'status',
        'note',
        'follow_up_note',
        'version',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (LetterDisposition $model) {
            throw new \DomainException('Letter dispositions cannot be deleted; they are retained as history.');
        });
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class, 'letter_id', 'id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'recipient_employee_id', 'id')->withTrashed();
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by', 'id')->withTrashed();
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by', 'id')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by', 'id')->withTrashed();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
