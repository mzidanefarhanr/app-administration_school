<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ability extends Model
{
    use HasFactory, Loggable;

    protected $guarded = ['id'];

    protected $table = 'abilities';

    /**
     * Atribut yang diizinkan untuk dicatat dalam log audit (allowlist).
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'name',
        'group',
        'description',
        'is_sensitive',
    ];

    protected $fillable = [
        'name',
        'group',
        'description',
        'is_sensitive',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'ability_role')->withTimestamps();
    }
}
