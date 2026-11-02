<?php

namespace App\Models;

use App\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    use HasFactory, Loggable;

    public const SUBJECT_STUDENT = 'student';

    public const SUBJECT_EMPLOYEE = 'employee';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    public const RECAP_MAX_RANGE_DAYS = 366;

    protected $guarded = ['id'];

    protected $table = 'leave_requests';

    /**
     * Allowlist audit izin; alasan tidak pernah dicatat (DEC-35 D11).
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'subject_type',
        'student_id',
        'employee_id',
        'school_year_id',
        'student_rombel_id',
        'type',
        'start_date',
        'end_date',
        'status',
        'version',
        'requested_by',
        'submitted_at',
        'submitted_by',
        'decided_at',
        'decided_by',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new \DomainException('Leave requests cannot be deleted; cancel the request instead.');
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'id')->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id')->withTrashed();
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id', 'id')->withTrashed();
    }

    public function studentRombel(): BelongsTo
    {
        return $this->belongsTo(StudentRombel::class, 'student_rombel_id', 'id')->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'id')->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'id')->withTrashed();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by', 'id')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by', 'id')->withTrashed();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LeaveAttachment::class, 'leave_request_id', 'id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(LeaveDecision::class, 'leave_request_id', 'id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED], true);
    }

    public function days(): int
    {
        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = Carbon::parse($this->end_date)->startOfDay();

        return (int) $start->diffInDays($end) + 1;
    }

    public function typeLabel(): string
    {
        return (string) config('leaves.types.'.$this->subject_type.'.'.$this->type.'.label', $this->type);
    }
}
