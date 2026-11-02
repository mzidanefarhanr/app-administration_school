<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versi file arsip yang immutable (Fase 09a, DEC-37).
 *
 * Tidak memakai Loggable; perubahan current_version pada dokumen induk
 * tercatat oleh audit ArchiveDocument.
 */
class ArchiveFileVersion extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $table = 'archive_file_versions';

    protected $casts = [
        'version_number' => 'integer',
        'size_bytes' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \DomainException('Archive file versions are immutable.');
        });

        static::deleting(function () {
            throw new \DomainException('Archive file versions are immutable.');
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ArchiveDocument::class, 'archive_document_id', 'id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id')->withTrashed();
    }
}
