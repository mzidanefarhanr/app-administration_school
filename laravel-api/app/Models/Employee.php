<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes, Loggable;

    protected $guarded = ['id'];
    protected $table = 'employees';
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_nik',
        'employee_status_id',
        'employee_type_id',
        'employment_id',
        'subject_id',
        'religion_id',
        'district_id',
        'marital_status_id',
        'gender_id',
        'nuptk',
        'npwp',
        'no_kk',
        'place_of_birth',
        'date_of_birth',
        'address',
        'rt',
        'rw',
        'zip_code',
        'wa_number',
        'email',
        'appointment_certificate',
        'tmt_employee',
        'certificate_of_teaching_hours',
        'biological_mothers_name',
        'partners_name',
        'elementary_school_name',
        'elementary_school_entry',
        'elementary_school_graduation',
        'nisn',
        'elementary_school_passing_grade',
        'junior_high_school_name',
        'junior_high_school_entry',
        'junior_high_school_graduation',
        'junior_high_school_passing_grade',
        'senior_high_school_name',
        'senior_high_school_entry',
        'senior_high_school_graduation',
        'senior_high_school_passing_grade',
        'bachelor_campus_name',
        'bachelor_major',
        'bachelor_faculty',
        'bachelor_entry',
        'bachelor_graduation',
        'bachelor_nim',
        'bachelor_passing_grade',
        'master_campus_name',
        'master_major',
        'master_faculty',
        'master_entry',
        'master_graduation',
        'master_nim',
        'master_passing_grade',
        'exit_date',
        'check_dapodik',
        'government_certificate',
    ];

     /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        //
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_nik', 'nik')->withTrashed();
    }
    public function employeeStatus(): BelongsTo
    {
        return $this->belongsTo(EmployeeStatus::class, 'employee_status_id', 'id')->withTrashed();
    }
    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeType::class, 'employee_type_id', 'id')->withTrashed();
    }
    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class, 'employment_id', 'id')->withTrashed();
    }
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'id')->withTrashed();
    }
    public function relogion(): BelongsTo
    {
        return $this->belongsTo(Religion::class, 'religion_id', 'id')->withTrashed();
    }
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id', 'id')->withTrashed();
    }
    public function maritalStatus(): BelongsTo
    {
        return $this->belongsTo(MaritalStatus::class, 'marital_status_id', 'id')->withTrashed();
    }
    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class, 'gender_id', 'id')->withTrashed();
    }
}
