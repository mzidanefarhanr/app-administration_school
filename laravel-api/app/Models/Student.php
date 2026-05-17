<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'students';
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'version',
        'name',
        'nik',
        'birthplace',
        'birthdate',
        'age',
        'gender_id',
        'religion_id',
        'education_level_id',
        'blood_type_id',
        'profession_id',
        'village_id',
        'zip_code',
        'rt_num',
        'rw_num',
        'address',
        'kk',
        'nis',
        'nisn',
        'child_order_to',
        'child_order_total',
        'family_status_id',
        'hp_num',
        'history_illness',
        'body_height',
        'body_weight',
        'last_education_school_id',
        'certificate_year',
        'certificate_num',
        'akta',
        'latest_education_school_id',
        // 'student_status_id',
        // 'student_entry_id',
        // 'latest_student_entry_date',
        // 'mutation_education_school_id',
        // 'latest_student_exit_date',
        // 'latest_student_exit_school_id',
        // 'student_major_id',
        'father_name',
        'father_nik',
        'father_birthplace',
        'father_birthdate',
        'father_education_level_id',
        'father_profession_id',
        'father_income',
        'father_hp_num',
        'mother_name',
        'mother_nik',
        'mother_birthplace',
        'mother_birthdate',
        'mother_education_level_id',
        'mother_profession_id',
        'mother_income',
        'mother_hp_num',
        'guardian_name',
        'guardian_nik',
        'guardian_birthplace',
        'guardian_birthdate',
        'guardian_education_level_id',
        'guardian_profession_id',
        'guardian_income',
        'guardian_hp_num',
        'check_ppdbbersama',
        'check_kjp',
        'check_pip',
    ];

     /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        //
    ];

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class, 'gender_id', 'id');
    }
    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religion::class, 'religion_id', 'id');
    }
    public function education_level(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class, 'education_level_id', 'id');
    }
    public function blood_type(): BelongsTo
    {
        return $this->belongsTo(BloodType::class, 'blood_type_id', 'id');
    }
    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class, 'profession_id', 'id');
    }
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'village_id', 'id');
    }
    public function family_status(): BelongsTo
    {
        return $this->belongsTo(FamilyStatus::class, 'family_status_id', 'id');
    }
    public function last_education_school(): BelongsTo
    {
        return $this->belongsTo(EducationSchool::class, 'last_education_school_id', 'npsn');
    }
    public function latest_education_school(): BelongsTo
    {
        return $this->belongsTo(EducationSchool::class, 'latest_education_school_id', 'npsn');
    }
    // public function student_status(): BelongsTo
    // {
    //     return $this->belongsTo(StudentStatus::class, 'student_status_id', 'id');
    // }
    // public function student_entry(): BelongsTo
    // {
    //     return $this->belongsTo(StudentEntry::class, 'student_entry_id', 'id');
    // }
    // public function mutation_education_school(): BelongsTo
    // {
    //     return $this->belongsTo(EducationSchool::class, 'mutation_education_school_id', 'npsn');
    // }
    // public function latest_student_exit_school(): BelongsTo
    // {
    //     return $this->belongsTo(EducationSchool::class, 'latest_student_exit_school_id', 'npsn');
    // }
    // public function student_major(): BelongsTo
    // {
    //     return $this->belongsTo(StudentMajor::class, 'student_major_id', 'id');
    // }
    public function father_education_level(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class, 'father_education_level_id', 'id');
    }
    public function father_profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class, 'father_profession_id', 'id');
    }
    public function mother_education_level(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class, 'mother_education_level_id', 'id');
    }
    public function mother_profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class, 'mother_profession_id', 'id');
    }
    public function guardian_education_level(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class, 'guardian_education_level_id', 'id');
    }
    public function guardian_profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class, 'guardian_profession_id', 'id');
    }

}
