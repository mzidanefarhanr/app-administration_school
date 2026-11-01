<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger approval/reject surat keluar yang immutable (Fase 07b, DEC-33).
 *
 * Tidak memakai Loggable; notes hanya dapat dibaca melalui detail surat
 * berizin dan tidak memiliki endpoint edit/hapus.
 */
class LetterApproval extends Model
{
    use HasFactory;

    public const ACTION_APPROVED = 'approved';

    public const ACTION_REJECTED = 'rejected';

    public const ACTIONS = [self::ACTION_APPROVED, self::ACTION_REJECTED];

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $table = 'letter_approvals';

    protected $fillable = [
        'letter_id',
        'action',
        'actor_id',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Letter approvals are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Letter approvals are immutable and cannot be deleted.');
        });
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class, 'letter_id', 'id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id', 'id')->withTrashed();
    }
}
