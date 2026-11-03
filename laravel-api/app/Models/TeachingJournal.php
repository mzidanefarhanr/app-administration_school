<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingJournal extends Model
{
    use HasFactory, Loggable;

    public const SOURCE_REGULAR = 'regular';

    public const SOURCE_RESCHEDULED = 'rescheduled';

    protected $table = 'teaching_journals';

    protected $fillable = [
        'school_year_id',
        'semester_id',
        'teaching_assignment_id',
        'teaching_schedule_id',
        'teaching_schedule_exception_id',
        'school_rombel_id',
        'subject_id',
        'session_date',
        'source_type',
        'period_start',
        'period_end',
        'scheduled_start_time',
        'scheduled_end_time',
        'actual_start_time',
        'actual_end_time',
        'room',
        'assigned_employee_id',
        'teacher_employee_id',
        'topic',
        'method',
        'summary',
        'notes',
        'correction_reason',
        'corrected_by',
        'corrected_at',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'source_type' => self::SOURCE_REGULAR,
        'method' => 'lecture',
        'version' => 1,
    ];

    protected $casts = [
        'school_year_id' => 'integer',
        'semester_id' => 'integer',
        'teaching_assignment_id' => 'integer',
        'teaching_schedule_id' => 'integer',
        'teaching_schedule_exception_id' => 'integer',
        'school_rombel_id' => 'integer',
        'subject_id' => 'integer',
        'session_date' => 'date:Y-m-d',
        'period_start' => 'integer',
        'period_end' => 'integer',
        'assigned_employee_id' => 'integer',
        'teacher_employee_id' => 'integer',
        'corrected_by' => 'integer',
        'corrected_at' => 'datetime',
        'version' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Audit allowlist (DEC-40 D8): excludes free-text topic, summary, notes, and correction_reason.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'school_year_id',
        'semester_id',
        'teaching_assignment_id',
        'teaching_schedule_id',
        'teaching_schedule_exception_id',
        'school_rombel_id',
        'subject_id',
        'session_date',
        'source_type',
        'period_start',
        'period_end',
        'scheduled_start_time',
        'scheduled_end_time',
        'actual_start_time',
        'actual_end_time',
        'room',
        'assigned_employee_id',
        'teacher_employee_id',
        'method',
        'corrected_by',
        'corrected_at',
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

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TeachingSchedule::class, 'teaching_schedule_id');
    }

    public function exception(): BelongsTo
    {
        return $this->belongsTo(TeachingScheduleException::class, 'teaching_schedule_exception_id');
    }

    public function scheduleException(): BelongsTo
    {
        return $this->belongsTo(TeachingScheduleException::class, 'teaching_schedule_exception_id');
    }

    public function schoolRombel(): BelongsTo
    {
        return $this->belongsTo(SchoolRombel::class, 'school_rombel_id')->withTrashed();
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id')->withTrashed();
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id')->withTrashed();
    }

    public function teacherEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_employee_id')->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_employee_id')->withTrashed();
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by')->withTrashed();
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
