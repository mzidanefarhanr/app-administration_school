<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dokumen PDF final dan snapshot immutable per surat (Fase 07c, DEC-34).
 *
 * Tidak memakai Loggable; path/hash/snapshot hanya untuk audit internal
 * dan tidak diekspos melalui API.
 */
class LetterDocument extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $table = 'letter_documents';

    protected $fillable = [
        'letter_id',
        'template_code',
        'template_version',
        'letter_version',
        'official_number',
        'snapshot',
        'pdf_path',
        'pdf_sha256',
        'pdf_size_bytes',
        'page_count',
        'renderer',
        'generated_at',
        'generated_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'template_version' => 'integer',
        'letter_version' => 'integer',
        'pdf_size_bytes' => 'integer',
        'page_count' => 'integer',
        'generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException('Letter documents are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Letter documents are immutable and cannot be deleted.');
        });
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class, 'letter_id', 'id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by', 'id')->withTrashed();
    }
}
