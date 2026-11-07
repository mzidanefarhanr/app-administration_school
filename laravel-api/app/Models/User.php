<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Loggable, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $dates = ['deleted_at'];

    /**
     * Cache nama kemampuan efektif untuk instance ini (per request).
     *
     * @var array<int, string>|null
     */
    protected ?array $abilityNamesCache = null;

    /**
     * Atribut yang diizinkan untuk dicatat dalam log audit (allowlist).
     * Password, remember_token, dan rahasia autentikasi sengaja dikecualikan (DEC-17).
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'name',
        'username',
        'email',
        'nik',
        'type_user_id',
        'status_user_id',
        'first_login_at',
        'status_active',
        'version',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'version',
        'name',
        'email',
        'username',
        'password',
        'nik',
        'type_user_id',
        'status_user_id',
        'first_login_at',
        'status_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'first_login_at' => 'boolean',
        'status_active' => 'boolean',
    ];

    public function status_user(): BelongsTo
    {
        return $this->belongsTo(StatusUser::class, 'status_user_id', 'id')->withTrashed();
    }

    public function type_user(): BelongsTo
    {
        return $this->belongsTo(TypeUser::class, 'type_user_id', 'id')->withTrashed();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    /**
     * Kumpulan nama kemampuan efektif milik pengguna (gabungan dari semua peran).
     * Di-cache per instance agar resolusi pada satu request konsisten.
     *
     * @return array<int, string>
     */
    public function abilityNames(): array
    {
        if ($this->abilityNamesCache === null) {
            $this->abilityNamesCache = $this->roles()
                ->with('abilities')
                ->get()
                ->flatMap(fn (Role $role) => $role->abilities->pluck('name'))
                ->unique()
                ->values()
                ->all();
        }

        return $this->abilityNamesCache;
    }

    public function hasAbility(string $ability): bool
    {
        return in_array($ability, $this->abilityNames(), true);
    }

    /**
     * @param  string|array<int, string>  $abilities
     */
    public function hasAnyAbility($abilities): bool
    {
        foreach ((array) $abilities as $ability) {
            if ($this->hasAbility($ability)) {
                return true;
            }
        }

        return false;
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    /**
     * Apakah pengguna memiliki kemampuan pengelolaan akses (roles/abilities/users)?
     * Dipakai untuk melindungi admin terakhir.
     */
    public function canManageAccess(): bool
    {
        return $this->hasAnyAbility(['roles.manage', 'abilities.manage', 'access.assign']);
    }

    /**
     * Bersihkan cache kemampuan (dipakai setelah perubahan peran/kemampuan
     * dalam request yang sama).
     */
    public function flushAbilityCache(): void
    {
        $this->abilityNamesCache = null;
        $this->unsetRelation('roles');
    }
}
