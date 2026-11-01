<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agenda surat (Fase 07a, DEC-32).
 *
 * Tidak memakai soft delete; penghapusan domain tidak tersedia.
 * `void` adalah state bisnis yang tetap dapat dibaca dan diunduh.
 */
class Letter extends Model
{
    use HasFactory, Loggable;

    public const DIRECTION_INCOMING = 'incoming';

    public const DIRECTION_OUTGOING = 'outgoing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_VOID = 'void';

    public const DIRECTIONS = [self::DIRECTION_INCOMING, self::DIRECTION_OUTGOING];

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_VOID];

    protected $guarded = ['id'];

    protected $table = 'letters';

    /**
     * Allowlist audit; pengirim, perihal, catatan, dan alasan void tidak
     * diduplikasi ke activities.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'direction',
        'received_date',
        'letter_date',
        'source_number',
        'category',
        'status',
        'version',
        'voided_at',
        'voided_by',
        'created_by',
    ];

    protected $fillable = [
        'version',
        'direction',
        'received_date',
        'letter_date',
        'source_number',
        'sender',
        'subject',
        'category',
        'notes',
        'status',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
    ];

    protected $casts = [
        'received_date' => 'date:Y-m-d',
        'letter_date' => 'date:Y-m-d',
        'voided_at' => 'datetime',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Letter $model) {
            throw new \DomainException('Letters cannot be deleted; void the letter instead.');
        });
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LetterAttachment::class, 'letter_id', 'id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LetterEvent::class, 'letter_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id')->withTrashed();
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by', 'id')->withTrashed();
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOID;
    }
}
