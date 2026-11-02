<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 09a (DEC-37): versi file arsip yang immutable.
     * Append-only; koreksi dilakukan dengan versi baru, bukan update/hapus.
     */
    public function up(): void
    {
        Schema::create('archive_file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_document_id')->constrained('archive_documents')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->string('note', 500)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['archive_document_id', 'version_number'], 'archive_file_versions_document_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_file_versions');
    }
};
