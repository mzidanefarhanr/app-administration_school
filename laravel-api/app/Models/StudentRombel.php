<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentRombel extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    protected $guarded = ['id'];

    protected $table = 'student_rombels';

    protected $dates = ['deleted_at'];

    /**
     * Atribut yang diizinkan untuk dicatat dalam log audit (allowlist).
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'student_id',
        'school_rombel',
        'student_status_id',
        'student_entry_id',
        'latest_student_entry_date',
        'latest_student_exit_date',
        'latest_student_graduation_date',
        'archived_at',
        'version',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'version',
        'student_id',
        'student_nik',
        'school_rombel',
        'student_status_id',
        'student_entry_id',
        'latest_student_entry_date',
        'mutation_education_school_id',
        'latest_student_exit_date',
        'latest_student_exit_school_id',
        'latest_student_graduation_date',
        'latest_information',
        'archived_at',
        'archive_reason',
        'check_dapodik',
        'informations_dapodik',
        // 'student_major_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (StudentRombel $model) {
            // Sinkronisasi dua arah tanpa mengharuskan student_nik selalu ada
            if ($model->student_id && empty($model->student_nik)) {
                $student = $model->relationLoaded('student') ? $model->student : Student::find($model->student_id);
                if ($student && ! empty($student->nik)) {
                    $model->student_nik = $student->nik;
                }
            } elseif (! empty($model->student_nik) && ! $model->student_id) {
                $matches = Student::where('nik', $model->student_nik)->pluck('id');
                if ($matches->count() === 1) {
                    $model->student_id = $matches->first();
                }
            }

            // Penegakan aturan: maksimal 1 keanggotaan rombel aktif per siswa per tahun ajaran (DEC-20, DEC-21)
            $isActive = is_null($model->deleted_at)
                && is_null($model->archived_at)
                && (is_null($model->student_status_id) || (int) $model->student_status_id === 1)
                && is_null($model->latest_student_exit_date);

            if ($isActive && ($model->student_id || $model->student_nik)) {
                // Keanggotaan aktif baru tidak boleh merujuk siswa terarsip (DEC-21)
                $targetStudent = $model->student_id
                    ? Student::withTrashed()->find($model->student_id)
                    : ($model->student_nik ? Student::withTrashed()->where('nik', $model->student_nik)->first() : null);

                if ($targetStudent && $targetStudent->trashed()) {
                    throw new \DomainException('Cannot create active rombel membership for an archived student.');
                }

                $targetRombel = SchoolRombel::find($model->school_rombel);
                if ($targetRombel && $targetRombel->school_year_id) {
                    $effStudentId = $model->student_id;
                    $effStudentNik = $model->student_nik;
                    if (! $effStudentId && $effStudentNik) {
                        $effStudentId = Student::where('nik', $effStudentNik)->value('id');
                    }
                    if (! $effStudentNik && $effStudentId) {
                        $effStudentNik = Student::where('id', $effStudentId)->value('nik');
                    }

                    $query = static::query()
                        ->whereHas('schoolRombel', fn ($q) => $q->where('school_year_id', $targetRombel->school_year_id))
                        ->whereNull('deleted_at')
                        ->whereNull('archived_at')
                        ->where(fn ($q) => $q->where('student_status_id', 1)->orWhereNull('student_status_id'))
                        ->whereNull('latest_student_exit_date');

                    if ($model->exists) {
                        $query->where('id', '!=', $model->id);
                    }

                    $query->where(function ($q) use ($effStudentId, $effStudentNik) {
                        $q->where(function ($sub) use ($effStudentId) {
                            if ($effStudentId) {
                                $sub->where('student_id', $effStudentId);
                            }
                        })->orWhere(function ($sub) use ($effStudentNik) {
                            if ($effStudentNik) {
                                $sub->where('student_nik', $effStudentNik);
                            }
                        });
                    });

                    if ($query->exists()) {
                        throw new \DomainException('Student already has an active rombel membership in this academic year.');
                    }
                }
            }
        });
    }

    /**
     * Relasi utama keanggotaan rombel ke model Student (Fase 03a).
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }

    public function studentNik(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_nik', 'nik');
    }

    /**
     * Resolusi siswa yang aman dan anti-ambigu (DEC-20):
     * 1. Jika student_id tersedia, student_id adalah sumber kebenaran mutlak.
     * 2. Jika student_id belum tersedia (data legacy), gunakan student_nik HANYA
     *    bila pasangan NIK dapat ditentukan secara tunggal (tepat 1 siswa).
     * 3. Jika NIK tidak cocok atau ambigu (> 1 siswa), kembalikan null (tidak mengambil kandidat pertama).
     */
    public function resolveStudent(): ?Student
    {
        if ($this->student_id) {
            return $this->relationLoaded('student') ? $this->student : Student::find($this->student_id);
        }

        if (! empty($this->student_nik)) {
            $matches = Student::where('nik', $this->student_nik)->get();
            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
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
