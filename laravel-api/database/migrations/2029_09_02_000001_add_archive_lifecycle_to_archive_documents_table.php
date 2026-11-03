<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 09b (DEC-38 D5): siklus hidup arsip dokumen.
     *
     * Additive: tiga kolom baru tanpa soft delete. Status active/archived
     * dihitung dari archived_at, bukan kolom status tersimpan.
     */
    public function up(): void
    {
        Schema::table('archive_documents', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('version');
            $table->string('archive_reason', 255)->nullable()->after('archived_at');
            $table->foreignId('archived_by')->nullable()->after('archive_reason')
                ->constrained('users')->cascadeOnUpdate()->nullOnDelete();

            $table->index('archived_at', 'archive_documents_archived_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('archive_documents', function (Blueprint $table) {
            $table->dropIndex('archive_documents_archived_at_index');
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn(['archived_at', 'archive_reason']);
        });
    }
};
