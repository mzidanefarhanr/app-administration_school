<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicSetting extends Model
{
    use HasFactory, Loggable;

    public const SINGLETON_ID = 1;

    protected $guarded = ['id'];

    protected $table = 'academic_settings';

    /**
     * Allowlist audit periode akademik; tidak memuat data pribadi.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'active_school_year_id',
        'active_semester_id',
        'updated_by',
    ];

    protected $fillable = [
        'active_school_year_id',
        'active_semester_id',
        'updated_by',
    ];

    public function activeSchoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'active_school_year_id', 'id')->withTrashed();
    }

    public function activeSemester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'active_semester_id', 'id')->withTrashed();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id')->withTrashed();
    }
}
