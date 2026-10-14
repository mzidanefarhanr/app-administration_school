<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $dates = ['deleted_at'];

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
