<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    public const STATUS_INVALID = 'invalid';

    public const STATUS_READY = 'ready';

    public const STATUS_COMMITTED = 'committed';

    protected $guarded = ['id'];

    protected $casts = [
        'committed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
