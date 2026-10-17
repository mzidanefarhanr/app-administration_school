<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    protected $guarded = ['id'];

    protected $dates = ['deleted_at'];

    /**
     * Atribut yang diizinkan untuk dicatat dalam log audit (allowlist).
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'name',
        'slug',
        'description',
        'is_protected',
        'version',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_protected',
        'version',
    ];

    protected $casts = [
        'is_protected' => 'boolean',
    ];

    public function abilities(): BelongsToMany
    {
        return $this->belongsToMany(Ability::class, 'ability_role')->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    public function hasAbility(string $ability): bool
    {
        return $this->abilities->contains('name', $ability);
    }
}
