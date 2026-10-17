<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolLevel extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    protected $guarded = ['id'];

    protected $table = 'school_levels';

    protected $dates = ['deleted_at'];

    /**
     * Atribut yang diizinkan untuk dicatat dalam log audit (allowlist).
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'name',
    ];

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

    public function schoolrombel(): HasMany
    {
        return $this->hasMany(SchoolRombel::class, 'school_level_id', 'id');
    }
}
