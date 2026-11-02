<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 09a (DEC-37): metadata arsip dokumen.
     * Additive: tabel baru; subjek ditautkan lewat whitelist service, bukan
     * foreign key polimorfik. Tidak ada soft delete pada 09a.
     */
    public function up(): void
    {
        Schema::create('archive_documents', function (Blueprint $table) {
            $table->id();
            $table->string('category', 40);
            $table->string('title', 200);
            $table->string('document_number', 100)->nullable();
            $table->date('document_date');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('description')->nullable();
            $table->string('subject_type', 20)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedSmallInteger('current_version')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index('category', 'archive_documents_category_index');
            $table->index('document_number', 'archive_documents_number_index');
            $table->index('document_date', 'archive_documents_date_index');
            $table->index('valid_until', 'archive_documents_valid_until_index');
            $table->index(['subject_type', 'subject_id'], 'archive_documents_subject_index');
            $table->index(['category', 'document_date'], 'archive_documents_category_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_documents');
    }
};
