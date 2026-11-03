<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 09b (DEC-38 D3): audit akses download/preview arsip.
     *
     * Tabel append-only terpisah karena kolom activities.information adalah
     * enum tetap yang sudah dijalankan di database pemilik. Tidak menyimpan
     * judul, nomor dokumen, nama file, path, atau checksum.
     */
    public function up(): void
    {
        Schema::create('archive_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_document_id')->constrained('archive_documents')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('archive_file_version_id')->nullable()->constrained('archive_file_versions')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')
                ->cascadeOnUpdate()->nullOnDelete();
            $table->string('action', 20);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['archive_document_id', 'created_at'], 'archive_access_logs_document_index');
            $table->index(['user_id', 'created_at'], 'archive_access_logs_user_index');
            $table->index(['action', 'created_at'], 'archive_access_logs_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_access_logs');
    }
};
