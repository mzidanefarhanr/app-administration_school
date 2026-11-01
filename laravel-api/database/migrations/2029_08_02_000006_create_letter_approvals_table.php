<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 07b (DEC-33): ledger approval/reject surat keluar yang immutable.
     * Melengkapi letter_events; tanpa endpoint edit/hapus.
     */
    public function up(): void
    {
        Schema::create('letter_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained('letters')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('action', 20);
            $table->foreignId('actor_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['letter_id', 'created_at'], 'letter_approvals_letter_created_index');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_approvals');
    }
};
