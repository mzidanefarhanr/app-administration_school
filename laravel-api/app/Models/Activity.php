<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'activities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'before',
        'after',
        'new',
        'delete',
        'table_name',
        'record_id',
        'information',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'new' => 'array',
        'delete' => 'array',
    ];

    /**
     * Hook pengujian atomisitas transaksi: menyimulasikan kegagalan INSERT audit.
     *
     * @var (\Closure(\App\Models\Activity): void)|null
     */
    public static ?\Closure $testCreatingHook = null;

    /**
     * Audit log bersifat immutable append-only (DEC-17).
     * Modifikasi dan penghapusan catatan audit dilarang pada model level.
     */
    protected static function booted(): void
    {
        static::creating(function ($activity) {
            if (static::$testCreatingHook !== null) {
                (static::$testCreatingHook)($activity);
            }
        });

        static::updating(function () {
            throw new \RuntimeException('Audit logs are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Audit logs are immutable and cannot be deleted.');
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        //
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->withTrashed();
    }
}
