<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 07c (DEC-34): dokumen PDF final dan snapshot immutable per surat.
     * Satu dokumen per surat; path privat; tanpa endpoint tulis langsung.
     */
    public function up(): void
    {
        Schema::create('letter_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->unique()
                ->constrained('letters')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('template_code', 50);
            $table->unsignedInteger('template_version');
            $table->unsignedInteger('letter_version');
            $table->string('official_number', 100);
            $table->json('snapshot');
            $table->string('pdf_path', 255);
            $table->char('pdf_sha256', 64);
            $table->unsignedBigInteger('pdf_size_bytes');
            $table->unsignedInteger('page_count');
            $table->string('renderer', 50);
            $table->timestamp('generated_at');
            $table->foreignId('generated_by')
                ->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->index(['template_code', 'template_version'], 'letter_documents_template_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_documents');
    }
};
