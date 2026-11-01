<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * History surat yang immutable, append-only (Fase 07a, DEC-32).
 *
 * Tidak memakai Loggable agar tidak menduplikasi activities. Metadata
 * dibatasi pada field operasional tanpa PII atau isi file.
 */
class LetterEvent extends Model
{
    use HasFactory;

    public const EVENT_CREATED = 'created';

    public const EVENT_UPDATED = 'updated';

    public const EVENT_VOIDED = 'voided';

    public const EVENT_ATTACHMENT_ADDED = 'attachment_added';

    public const EVENT_ATTACHMENT_REMOVED = 'attachment_removed';

    public const EVENTS = [
        self::EVENT_CREATED,
        self::EVENT_UPDATED,
        self::EVENT_VOIDED,
        self::EVENT_ATTACHMENT_ADDED,
        self::EVENT_ATTACHMENT_REMOVED,
    ];

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $table = 'letter_events';

    protected $fillable = [
        'letter_id',
        'event',
        'actor_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Hook pengujian atomisitas transaksi: menyimulasikan kegagalan INSERT event.
     *
     * @var (\Closure(\App\Models\LetterEvent): void)|null
     */
    public static ?\Closure $testCreatingHook = null;

    protected static function booted(): void
    {
        static::creating(function (LetterEvent $event) {
            if (static::$testCreatingHook !== null) {
                (static::$testCreatingHook)($event);
            }
        });

        static::updating(function () {
            throw new \RuntimeException('Letter events are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Letter events are immutable and cannot be deleted.');
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
