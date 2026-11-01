<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lampiran privat surat (Fase 07a, DEC-32).
 *
 * Soft delete; file fisik dipertahankan sampai keputusan retensi DEC-13.
 * Path dan checksum tidak pernah diekspos ke API atau audit.
 */
class LetterAttachment extends Model
{
    use HasFactory, Loggable, SoftDeletes;

    protected $guarded = ['id'];

    protected $table = 'letter_attachments';

    /**
     * Allowlist audit; nama asli, path, dan checksum tidak diduplikasi.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'letter_id',
        'mime_type',
        'size_bytes',
        'deleted_at',
    ];

    protected $fillable = [
        'letter_id',
        'original_name',
        'path',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class, 'letter_id', 'id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id')->withTrashed();
    }
}
