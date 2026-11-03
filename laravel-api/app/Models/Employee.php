<?php

namespace App\Models;

use App\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    protected $guarded = ['id'];

    protected $table = 'employees';

    protected $dates = ['deleted_at', 'user_id_resolved_at', 'account_unlinked_at', 'legacy_profile_at'];

    protected $attributes = [
        'version' => 1,
    ];

    /**
     * Atribut yang diizinkan untuk dicatat dalam log audit (allowlist minimum).
     * Data pribadi (nama, NIK/NUPTK/NPWP/KK, ibu kandung, pasangan, kontak,
     * alamat, nilai/IPK ijazah) dikecualikan penuh (DEC-04 #6).
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'user_id',
        'employee_status_id',
        'employee_type_id',
        'employment_id',
        'subject_id',
        'gender_id',
        'religion_id',
        'tmt_employee',
        'exit_date',
        'version',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'nik',
        'archive_reason',
        'user_id',
        'user_id_resolved_at',
        'account_unlinked_at',
        'legacy_profile_at',
        'version',
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

    /**
     * Relasi utama profil pegawai ke model User berbasis user_id (DEC-04 #2).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->withTrashed();
    }

    /**
     * Relasi legacy ke model User berbasis string user_nik.
     */
    public function legacyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_nik', 'nik')->withTrashed();
    }

    /**
     * Resolusi akun pengguna yang aman dan anti-ambigu (DEC-04 #2 & #3):
     * 1. Jika user_id terisi, user_id adalah sumber kebenaran mutlak.
     * 2. Jika akun telah dilepas (account_unlinked_at) atau sudah diresolusi di Phase 04a,
     *    jangan pernah menghubungkan ulang melalui user_nik.
     * 3. Fallback NIK HANYA untuk baris legacy (legacy_profile_at terisi) yang belum
     *    pernah dimigrasikan, dan hanya bila terdapat tepat 1 user yang cocok.
     */
    public function resolveUser(): ?User
    {
        if ($this->user_id) {
            return $this->relationLoaded('user') ? $this->user : User::withTrashed()->find($this->user_id);
        }

        if ($this->account_unlinked_at !== null || $this->user_id_resolved_at !== null) {
            return null;
        }

        if ($this->legacy_profile_at === null) {
            return null;
        }

        if (! empty($this->user_nik)) {
            $matches = User::withTrashed()->where('nik', $this->user_nik)->get();
            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
    }

    /**
     * Eligibility for homeroom teacher: active Pendidik profile whose exit_date
     * has not passed (DEC-29 D4). Fails closed when the Pendidik type is absent.
     */
    public function scopeEligibleHomeroomTeacher(Builder $query): Builder
    {
        $teacherTypeId = EmployeeType::where('name', 'Pendidik')->value('id');

        if ($teacherTypeId === null) {
            return $query->whereRaw('1 = 0');
        }

        $today = Carbon::now('Asia/Jakarta')->toDateString();

        return $query->whereNull('employees.deleted_at')
            ->where('employees.employee_type_id', $teacherTypeId)
            ->where(function (Builder $builder) use ($today) {
                $builder->whereNull('employees.exit_date')
                    ->orWhere('employees.exit_date', '>=', $today);
            });
    }

    /**
     * Active teaching staff eligible for teaching assignments (DEC-39 D2).
     */
    public function scopeEligibleTeacher(Builder $query): Builder
    {
        return $this->scopeEligibleHomeroomTeacher($query);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'employee_id', 'id');
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

    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religion::class, 'religion_id', 'id')->withTrashed();
    }

    /**
     * Alias kompatibilitas untuk typo historis relogion()
     */
    public function relogion(): BelongsTo
    {
        return $this->religion();
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id', 'id');
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
