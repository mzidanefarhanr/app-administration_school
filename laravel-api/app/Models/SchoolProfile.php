<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolProfile extends Model
{
    use HasFactory, SoftDeletes, Loggable;

    protected $guarded = ['id'];
    protected $table = 'school_profiles';
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'education_school_npsn',
        'principal_id',
        'school_year_id',
        'status_principal_id',
        'nds',
        'nss',
        'nis',
        'nrks',
        'tmt_principal',
        'official_number',
        'email',
        'website',
        'school_committee_name',
        'school_committee_number',
    ];

     /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        //
    ];

    public function educationSchool(): BelongsTo
    {
        return $this->belongsTo(EducationSchool::class, 'education_school_npsn', 'npsn')->withTrashed();
    }
    public function principal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_id', 'id')->withTrashed();
    }
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id', 'id')->withTrashed();
    }
    public function statusPrincipal(): BelongsTo
    {
        return $this->belongsTo(StatusUser::class, 'status_principal_id', 'id')->withTrashed();
    }
}
