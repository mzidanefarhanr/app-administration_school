<?php

namespace App\Models;

use App\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Metadata arsip dokumen (Fase 09a, DEC-37).
 *
 * Subjek domain disimpan sebagai type/id dengan whitelist service; tidak
 * memakai relasi polimorfik. Status validitas dihitung dari tanggal WIB.
 */
class ArchiveDocument extends Model
{
    use HasFactory, Loggable;

    public const SUBJECT_STUDENT = 'student';

    public const SUBJECT_EMPLOYEE = 'employee';

    public const SUBJECT_LETTER = 'letter';

    public const SUBJECT_LEAVE = 'leave';

    public const SUBJECT_TYPES = [
        self::SUBJECT_STUDENT,
        self::SUBJECT_EMPLOYEE,
        self::SUBJECT_LETTER,
        self::SUBJECT_LEAVE,
    ];

    public const VALIDITY_NONE = 'none';

    public const VALIDITY_UPCOMING = 'upcoming';

    public const VALIDITY_VALID = 'valid';

    public const VALIDITY_EXPIRING_SOON = 'expiring_soon';

    public const VALIDITY_EXPIRED = 'expired';

    protected $guarded = ['id'];

    protected $table = 'archive_documents';

    /**
     * Allowlist audit; judul, nomor, deskripsi, dan nama file tidak dicatat.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'category',
        'document_date',
        'valid_from',
        'valid_until',
        'subject_type',
        'subject_id',
        'current_version',
        'version',
    ];

    protected $fillable = [
        'category',
        'title',
        'document_number',
        'document_date',
        'valid_from',
        'valid_until',
        'description',
        'subject_type',
        'subject_id',
        'current_version',
        'version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'document_date' => 'date:Y-m-d',
        'valid_from' => 'date:Y-m-d',
        'valid_until' => 'date:Y-m-d',
        'current_version' => 'integer',
        'version' => 'integer',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(ArchiveFileVersion::class, 'archive_document_id')
            ->orderBy('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ArchiveFileVersion::class, 'archive_document_id')
            ->latestOfMany('version_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id')->withTrashed();
    }

    public function categoryLabel(): string
    {
        return (string) config('archives.categories.'.$this->category, $this->category);
    }

    public function validityStatus(?string $today = null): string
    {
        if ($this->valid_until === null) {
            return self::VALIDITY_NONE;
        }

        $today = $today ?? Carbon::now('Asia/Jakarta')->toDateString();

        if ($this->valid_from !== null && $this->valid_from->format('Y-m-d') > $today) {
            return self::VALIDITY_UPCOMING;
        }

        if ($this->valid_until->format('Y-m-d') < $today) {
            return self::VALIDITY_EXPIRED;
        }

        $soon = Carbon::createFromFormat('Y-m-d', $today, 'Asia/Jakarta')
            ->addDays((int) config('archives.expiring_soon_days'))
            ->toDateString();

        if ($this->valid_until->format('Y-m-d') <= $soon) {
            return self::VALIDITY_EXPIRING_SOON;
        }

        return self::VALIDITY_VALID;
    }
}
