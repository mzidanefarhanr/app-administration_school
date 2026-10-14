<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ability extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'abilities';

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
