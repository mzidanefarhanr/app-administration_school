<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profession extends Model
{
    use HasFactory, SoftDeletes, Loggable;

    protected $guarded = ['id'];
    protected $table = 'professions';
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

     /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        //
    ];

    public function studentsProfession(): HasMany
    {
        return $this->hasMany(Student::class, 'profession_id', 'id');
    }
    public function fathersProfession(): HasMany
    {
        return $this->hasMany(Student::class, 'father_profession_id', 'id');
    }
    public function mothersProfession(): HasMany
    {
        return $this->hasMany(Student::class, 'mother_profession_id', 'id');
    }
    public function guardiansProfession(): HasMany
    {
        return $this->hasMany(Student::class, 'guardian_profession_id', 'id');
    }
}
