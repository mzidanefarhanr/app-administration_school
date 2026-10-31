<?php

namespace App\Models;

use App\Traits\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataQualityReview extends Model
{
    use Loggable;

    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_IGNORED = 'ignored';

    protected $guarded = ['id'];

    protected $casts = [
        'school_year_id' => 'integer',
        'subject_id' => 'integer',
        'subject_version' => 'integer',
        'related_subject_id' => 'integer',
        'related_version' => 'integer',
        'version' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Audit hanya menyimpan metadata temuan; nama/NIK/NUPTK/alamat tidak
     * pernah masuk log (DEC-28).
     *
     * @var array<int, string>
     */
    protected array $auditableAttributes = [
        'issue_key',
        'domain',
        'issue_type',
        'field',
        'subject_type',
        'school_year_id',
        'subject_id',
        'status',
        'reason_code',
        'version',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id')->withTrashed();
    }
}
