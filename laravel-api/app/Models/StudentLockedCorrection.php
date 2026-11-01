<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Koreksi periode terkunci (Fase 06c, DEC-31).
 *
 * Self-auditing dan immutable: tidak memakai Loggable, tidak boleh diubah
 * atau dihapus setelah dibuat. Snapshot hanya memuat ID/status/tanggal/versi.
 */
class StudentLockedCorrection extends Model
{
    use HasFactory;

    public const ACTION_MUTATION_IN = 'mutation_in';

    public const ACTION_MUTATION_OUT = 'mutation_out';

    public const ACTION_MUTATION_CANCEL = 'mutation_cancel';

    public const ACTION_MEMBERSHIP_EXIT = 'membership_exit';

    public const ACTION_MEMBERSHIP_REOPEN = 'membership_reopen';

    public const ACTIONS = [
        self::ACTION_MUTATION_IN,
        self::ACTION_MUTATION_OUT,
        self::ACTION_MUTATION_CANCEL,
        self::ACTION_MEMBERSHIP_EXIT,
        self::ACTION_MEMBERSHIP_REOPEN,
    ];

    public const TARGET_MUTATION = 'mutation';

    public const TARGET_MEMBERSHIP = 'membership';

    protected $guarded = ['id'];

    protected $table = 'student_locked_corrections';

    protected $fillable = [
        'school_year_id',
        'student_id',
        'action',
        'target_type',
        'target_id',
        'mutation_id',
        'student_rombel_id',
        'before',
        'after',
        'reason',
        'approval_reference',
        'created_by',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \DomainException('Locked corrections are immutable.');
        });

        static::deleting(function () {
            throw new \DomainException('Locked corrections are immutable.');
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'id')->withTrashed();
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id', 'id')->withTrashed();
    }

    public function mutation(): BelongsTo
    {
        return $this->belongsTo(StudentMutation::class, 'mutation_id', 'id');
    }

    public function studentRombel(): BelongsTo
    {
        return $this->belongsTo(StudentRombel::class, 'student_rombel_id', 'id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id')->withTrashed();
    }
}
