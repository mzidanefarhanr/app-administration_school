<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 07a (DEC-32): agenda surat masuk.
     * Additive: tabel baru; tidak mengubah tabel domain yang sudah ada.
     * Tidak ada soft delete; void adalah state bisnis, bukan penghapusan.
     */
    public function up(): void
    {
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('direction', 10)->default('incoming');
            $table->date('received_date');
            $table->date('letter_date');
            $table->string('source_number', 100)->nullable();
            $table->string('sender', 150);
            $table->string('subject', 200);
            $table->string('category', 50);
            $table->text('notes')->nullable();
            $table->string('status', 10)->default('active');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->index(['direction', 'received_date'], 'letters_direction_received_index');
            $table->index(['direction', 'status'], 'letters_direction_status_index');
            $table->index('category');
            $table->index('source_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
    }
};
