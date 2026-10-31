<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProgressionBatch extends Model
{
    use HasFactory, Loggable;

    public const STATUS_PREVIEW = 'preview';

    public const STATUS_COMMITTED = 'committed';

    protected $guarded = ['id'];

    protected $table = 'student_progression_batches';

    /**
     * Allowlist audit batch progresi; token dan payload keputusan tidak dicatat.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'source_school_year_id',
        'target_school_year_id',
        'status',
        'summary',
        'created_by',
        'committed_by',
        'committed_at',
        'expires_at',
    ];

    protected $fillable = [
        'token_hash',
        'source_school_year_id',
        'target_school_year_id',
        'status',
        'decisions',
        'summary',
        'result',
        'created_by',
        'committed_by',
        'expires_at',
        'committed_at',
    ];

    protected $casts = [
        'decisions' => 'array',
        'summary' => 'array',
        'result' => 'array',
        'expires_at' => 'datetime',
        'committed_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function sourceSchoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'source_school_year_id', 'id')->withTrashed();
    }

    public function targetSchoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'target_school_year_id', 'id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id')->withTrashed();
    }

    public function committer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committed_by', 'id')->withTrashed();
    }

    public function isCommitted(): bool
    {
        return $this->status === self::STATUS_COMMITTED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
