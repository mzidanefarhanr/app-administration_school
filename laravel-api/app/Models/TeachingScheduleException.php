<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingScheduleException extends Model
{
    use HasFactory, Loggable;

    public const TYPE_HOLIDAY = 'holiday';

    public const TYPE_CANCELLED = 'cancelled';

    public const TYPE_RESCHEDULED = 'rescheduled';

    protected $table = 'teaching_schedule_exceptions';

    protected $fillable = [
        'school_year_id',
        'semester_id',
        'exception_type',
        'exception_date',
        'school_rombel_id',
        'teaching_schedule_id',
        'rescheduled_date',
        'rescheduled_period_start',
        'rescheduled_period_end',
        'rescheduled_start_time',
        'rescheduled_end_time',
        'rescheduled_room',
        'rescheduled_room_normalized',
        'substitute_employee_id',
        'reason',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'version' => 1,
    ];

    protected $casts = [
        'school_year_id' => 'integer',
        'semester_id' => 'integer',
        'exception_date' => 'date:Y-m-d',
        'school_rombel_id' => 'integer',
        'teaching_schedule_id' => 'integer',
        'rescheduled_date' => 'date:Y-m-d',
        'rescheduled_period_start' => 'integer',
        'rescheduled_period_end' => 'integer',
        'substitute_employee_id' => 'integer',
        'version' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Audit allowlist (DEC-39 D10): excludes free-text reason.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'school_year_id',
        'semester_id',
        'exception_type',
        'exception_date',
        'school_rombel_id',
        'teaching_schedule_id',
        'rescheduled_date',
        'rescheduled_period_start',
        'rescheduled_period_end',
        'rescheduled_start_time',
        'rescheduled_end_time',
        'rescheduled_room',
        'substitute_employee_id',
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

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TeachingSchedule::class, 'teaching_schedule_id');
    }

    public function substituteEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'substitute_employee_id')->withTrashed();
    }

    public function journals(): HasMany
    {
        return $this->hasMany(TeachingJournal::class, 'teaching_schedule_exception_id');
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
