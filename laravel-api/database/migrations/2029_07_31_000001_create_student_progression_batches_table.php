<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 06b (DEC-30): riwayat batch progresi siswa dengan token hash.
     * Additive: hanya menambah tabel baru; tabel domain siswa tidak diubah.
     */
    public function up(): void
    {
        Schema::create('student_progression_batches', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('source_school_year_id')->constrained('school_years')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('target_school_year_id')->constrained('school_years')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 20)->default('preview');
            $table->json('decisions');
            $table->json('summary');
            $table->json('result')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('committed_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->index(['source_school_year_id', 'target_school_year_id'], 'progression_batches_year_pair_index');
            $table->index('status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_progression_batches');
    }
};
