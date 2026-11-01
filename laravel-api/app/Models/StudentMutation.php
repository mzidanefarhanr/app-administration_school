<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log domain mutasi siswa (Fase 06c, DEC-31).
 *
 * Tidak memakai soft delete dan tidak boleh dihapus melalui aplikasi;
 * pembatalan mengubah status menjadi `cancelled` secara kompensatif.
 */
class StudentMutation extends Model
{
    use HasFactory, Loggable;

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const TYPES = [self::TYPE_IN, self::TYPE_OUT];

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_CANCELLED];

    protected $guarded = ['id'];

    protected $table = 'student_mutations';

    /**
     * Allowlist audit; alasan dan data pribadi tidak diduplikasi ke activities.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'student_id',
        'school_year_id',
        'type',
        'student_rombel_id',
        'school_rombel_id',
        'effective_date',
        'school_npsn',
        'status',
        'version',
    ];

    protected $fillable = [
        'version',
        'type',
        'school_year_id',
        'student_id',
        'student_rombel_id',
        'school_rombel_id',
        'effective_date',
        'school_npsn',
        'reason',
        'status',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'created_by',
    ];

    protected $casts = [
        'effective_date' => 'date:Y-m-d',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (StudentMutation $model) {
            throw new \DomainException('Mutation logs cannot be deleted.');
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

    public function studentRombel(): BelongsTo
    {
        return $this->belongsTo(StudentRombel::class, 'student_rombel_id', 'id')->withTrashed();
    }

    public function schoolRombel(): BelongsTo
    {
        return $this->belongsTo(SchoolRombel::class, 'school_rombel_id', 'id')->withTrashed();
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(EducationSchool::class, 'school_npsn', 'npsn')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by', 'id')->withTrashed();
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
