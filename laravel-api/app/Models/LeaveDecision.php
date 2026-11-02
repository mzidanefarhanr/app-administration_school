<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveDecision extends Model
{
    use HasFactory, Loggable;

    public const ACTION_APPROVED = 'approved';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_CANCELLED = 'cancelled';

    /**
     * Riwayat keputusan tidak memiliki updated_at dan bersifat immutable.
     */
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $table = 'leave_decisions';

    /**
     * Allowlist audit keputusan; alasan keputusan tidak dicatat.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'leave_request_id',
        'action',
        'from_status',
        'to_status',
        'actor_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \DomainException('Leave decisions are immutable.');
        });

        static::deleting(function () {
            throw new \DomainException('Leave decisions are immutable.');
        });
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id', 'id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id', 'id')->withTrashed();
    }
}
