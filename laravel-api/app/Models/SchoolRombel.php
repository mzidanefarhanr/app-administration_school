<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolRombel extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];
    protected $table = 'school_rombels';
    protected $dates = ['deleted_at'];

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
        return $this->belongsTo(User::class, 'class_teach_id', 'id');
    }
}
