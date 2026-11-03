<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingSchedule extends Model
{
    use HasFactory, Loggable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'teaching_schedules';

    protected $fillable = [
        'teaching_assignment_id',
        'day_of_week',
        'period_start',
        'period_end',
        'start_time',
        'end_time',
        'room',
        'room_normalized',
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
        'teaching_assignment_id' => 'integer',
        'day_of_week' => 'integer',
        'period_start' => 'integer',
        'period_end' => 'integer',
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
        'teaching_assignment_id',
        'day_of_week',
        'period_start',
        'period_end',
        'start_time',
        'end_time',
        'room',
        'status',
        'version',
    ];

    public static function normalizeRoom(?string $room): ?string
    {
        if ($room === null) {
            return null;
        }

        $trimmed = trim((string) preg_replace('/\s+/', ' ', $room));

        return $trimmed === '' ? null : mb_strtoupper($trimmed, 'UTF-8');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(TeachingScheduleException::class, 'teaching_schedule_id');
    }

    public function journals(): HasMany
    {
        return $this->hasMany(TeachingJournal::class, 'teaching_schedule_id');
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
