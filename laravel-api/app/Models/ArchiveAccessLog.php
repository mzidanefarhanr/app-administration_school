<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit akses download/preview arsip yang append-only (Fase 09b, DEC-38 D3).
 *
 * Terpisah dari activities karena kolom information adalah enum tetap.
 * Tidak menyimpan judul, nomor dokumen, nama file, path, atau checksum.
 */
class ArchiveAccessLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const ACTION_DOWNLOAD = 'download';

    public const ACTION_PREVIEW = 'preview';

    public const ACTIONS = [
        self::ACTION_DOWNLOAD,
        self::ACTION_PREVIEW,
    ];

    protected $guarded = ['id'];

    protected $table = 'archive_access_logs';

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \DomainException('Archive access logs are immutable.');
        });

        static::deleting(function () {
            throw new \DomainException('Archive access logs are immutable.');
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ArchiveDocument::class, 'archive_document_id', 'id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ArchiveFileVersion::class, 'archive_file_version_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->withTrashed();
    }

    public function actionLabel(): string
    {
        return (string) config('archives.access_log.actions.'.$this->action, $this->action);
    }
}
