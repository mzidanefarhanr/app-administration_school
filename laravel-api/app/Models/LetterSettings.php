<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kop, penandatangan, dan aset privat per profil sekolah (Fase 07c, DEC-34).
 *
 * Tidak memakai soft delete; settings per tahun ajaran tetap disimpan.
 * Path aset tidak diekspos melalui API.
 */
class LetterSettings extends Model
{
    use HasFactory, Loggable;

    public const ASSETS = ['logo', 'signature', 'stamp'];

    protected $guarded = ['id'];

    protected $table = 'letter_settings';

    /**
     * Allowlist audit; teks kop lengkap, nama penandatangan, identitas,
     * dan path file tidak diduplikasi ke activities.
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'school_profile_id',
        'school_name',
        'place_of_issue',
        'signatory_title',
        'version',
        'updated_by',
        'logo_sha256',
        'signature_sha256',
        'stamp_sha256',
    ];

    protected $fillable = [
        'school_profile_id',
        'version',
        'government_line',
        'school_name',
        'address_line',
        'contact_line',
        'place_of_issue',
        'logo_path',
        'logo_mime',
        'logo_size_bytes',
        'logo_sha256',
        'signatory_name',
        'signatory_title',
        'signatory_identifier_label',
        'signatory_identifier',
        'signature_path',
        'signature_mime',
        'signature_size_bytes',
        'signature_sha256',
        'stamp_path',
        'stamp_mime',
        'stamp_size_bytes',
        'stamp_sha256',
        'updated_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'logo_size_bytes' => 'integer',
        'signature_size_bytes' => 'integer',
        'stamp_size_bytes' => 'integer',
    ];

    public function schoolProfile(): BelongsTo
    {
        return $this->belongsTo(SchoolProfile::class, 'school_profile_id', 'id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id')->withTrashed();
    }
}
