<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorReminder extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_WARN = 'warn';

    public const PRIORITY_INFO = 'info';

    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_LETTERS = 'letters';

    public const CATEGORY_LEAVES = 'leaves';

    public const CATEGORY_TEACHING = 'teaching';

    public const CATEGORY_STUDENTS = 'students';

    public const CATEGORY_EMPLOYEES = 'employees';

    public const CATEGORY_ARCHIVES = 'archives';

    public const CATEGORY_SYSTEM = 'system';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'priority',
        'due_date',
        'status',
        'system_source_key',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
