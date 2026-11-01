<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 07b (DEC-33): counter nomor resmi surat keluar.
     * Hanya berubah di dalam transaksi issue; nomor tidak pernah dipakai ulang.
     */
    public function up(): void
    {
        Schema::create('letter_number_counters', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 50)->default('outgoing');
            $table->string('period', 10);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['scope', 'period'], 'letter_number_counters_scope_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_number_counters');
    }
};
