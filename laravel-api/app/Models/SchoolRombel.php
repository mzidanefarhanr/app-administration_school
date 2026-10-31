<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolRombel extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    protected $guarded = ['id'];

    protected $table = 'school_rombels';

    protected $dates = ['deleted_at'];

    /**
     * Allowlist audit kelas; tidak ada data pribadi siswa/guru.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'name',
        'school_year_id',
        'school_level_id',
        'student_major_id',
        'class_teach_id',
        'class_teach_employee_id',
        'version',
        'deleted_at',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'version',
        'school_level_id',
        'school_year_id',
        'name',
        'student_major_id',
        'class_teach_id',
        'class_teach_employee_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        //
    ];

    public function schoolLevel(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'school_level_id', 'id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id', 'id');
    }

    public function studentMajor(): BelongsTo
    {
        return $this->belongsTo(StudentMajor::class, 'student_major_id', 'id');
    }

    public function classTeach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teach_id', 'id')->withTrashed();
    }

    public function classTeachEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'class_teach_employee_id', 'id')->withTrashed();
    }

    public function studentRombels(): HasMany
    {
        return $this->hasMany(StudentRombel::class, 'school_rombel', 'id');
    }
}
