<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 05c (DEC-28): status tinjauan temuan kelengkapan data.
     *
     * Tidak menyalin data pribadi; hanya kunci temuan yang stabil dan metadata
     * domain. school_year_id hanya berlaku untuk domain siswa, pegawai NULL.
     */
    public function up(): void
    {
        Schema::create('data_quality_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('issue_key', 64)->unique();
            $table->string('domain', 20);
            $table->string('issue_type', 20);
            $table->string('field', 50)->nullable();
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('school_year_id')->nullable();
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('subject_version');
            $table->unsignedBigInteger('related_subject_id')->nullable();
            $table->unsignedBigInteger('related_version')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('reason_code', 40)->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['domain', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_reviews');
    }
};
