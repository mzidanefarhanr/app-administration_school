<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 07c (DEC-34): tipe surat dan subjek dokumen bertipe.
     * Additive; kolom 07a/07b tidak diubah. Null = surat umum 07b.
     */
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->string('letter_type', 30)->nullable()->after('category');
            $table->string('document_subject_type', 30)->nullable()->after('letter_type');
            $table->unsignedBigInteger('document_subject_id')->nullable()->after('document_subject_type');
            $table->json('document_fields')->nullable()->after('document_subject_id');

            $table->index('letter_type');
            $table->index(['direction', 'letter_type'], 'letters_direction_letter_type_index');
            $table->index(['document_subject_type', 'document_subject_id'], 'letters_document_subject_index');
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropIndex(['letter_type']);
            $table->dropIndex('letters_direction_letter_type_index');
            $table->dropIndex('letters_document_subject_index');
            $table->dropColumn([
                'letter_type',
                'document_subject_type',
                'document_subject_id',
                'document_fields',
            ]);
        });
    }
};
