<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveAttachment extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    protected $guarded = ['id'];

    protected $table = 'leave_attachments';

    protected $dates = ['deleted_at'];

    /**
     * Allowlist audit lampiran; nama asli, path, dan hash tidak dicatat.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'leave_request_id',
        'mime_type',
        'size_bytes',
        'deleted_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id', 'id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id')->withTrashed();
    }
}
