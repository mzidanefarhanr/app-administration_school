<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentRombel extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'student_rombels';
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'version',
        'student_nik',
        'school_rombel',
        'student_status_id',
        'student_entry_id',
        'latest_student_entry_date',
        'mutation_education_school_id',
        'latest_student_exit_date',
        'latest_student_exit_school_id',
        // 'student_major_id',
    ];

     /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        //
    ];

    public function studentNik(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_nik', 'nik');
    }
    public function schoolRombel(): BelongsTo
    {
        return $this->belongsTo(SchoolRombel::class, 'school_rombel', 'id');
    }

    public function studentStatus(): BelongsTo
    {
        return $this->belongsTo(StudentStatus::class, 'student_status_id', 'id');
    }
    public function studentEntry(): BelongsTo
    {
        return $this->belongsTo(StudentEntry::class, 'student_entry_id', 'id');
    }
    public function mutationEducationSchool(): BelongsTo
    {
        return $this->belongsTo(EducationSchool::class, 'mutation_education_school_id', 'npsn');
    }
    public function latestStudentExitSchool(): BelongsTo
    {
        return $this->belongsTo(EducationSchool::class, 'latest_student_exit_school_id', 'npsn');
    }
    // public function student_major(): BelongsTo
    // {
    //     return $this->belongsTo(StudentMajor::class, 'student_major_id', 'id');
    // }
}
