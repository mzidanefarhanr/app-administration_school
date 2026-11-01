<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 07a (DEC-32): history surat yang immutable (append-only).
     * Tidak menggantikan activities; metadata minimal tanpa PII/isi file.
     */
    public function up(): void
    {
        Schema::create('letter_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained('letters')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('event', 30);
            $table->foreignId('actor_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['letter_id', 'created_at'], 'letter_events_letter_created_index');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_events');
    }
};
