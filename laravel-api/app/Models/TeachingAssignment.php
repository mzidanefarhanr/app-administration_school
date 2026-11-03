<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingAssignment extends Model
{
    use HasFactory, Loggable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'teaching_assignments';

    protected $fillable = [
        'school_year_id',
        'semester_id',
        'school_rombel_id',
        'subject_id',
        'employee_id',
        'hours_per_week',
        'status',
        'notes',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'version' => 1,
    ];

    protected $casts = [
        'school_year_id' => 'integer',
        'semester_id' => 'integer',
        'school_rombel_id' => 'integer',
        'subject_id' => 'integer',
        'employee_id' => 'integer',
        'hours_per_week' => 'integer',
        'version' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Audit allowlist (DEC-39 D10): excludes free-text notes.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'school_year_id',
        'semester_id',
        'school_rombel_id',
        'subject_id',
        'employee_id',
        'hours_per_week',
        'status',
        'version',
    ];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id')->withTrashed();
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id')->withTrashed();
    }

    public function schoolRombel(): BelongsTo
    {
        return $this->belongsTo(SchoolRombel::class, 'school_rombel_id')->withTrashed();
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id')->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(TeachingSchedule::class, 'teaching_assignment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
