<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Counter nomor resmi surat keluar (Fase 07b, DEC-33).
 *
 * Hanya diubah di dalam transaksi issue; tidak ada endpoint langsung.
 */
class LetterNumberCounter extends Model
{
    use HasFactory;

    public const SCOPE_OUTGOING = 'outgoing';

    protected $guarded = ['id'];

    protected $table = 'letter_number_counters';

    protected $fillable = [
        'scope',
        'period',
        'last_number',
    ];

    protected $casts = [
        'last_number' => 'integer',
    ];
}
