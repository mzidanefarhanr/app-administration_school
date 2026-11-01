<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 07b (DEC-33): metadata surat keluar dan alur approval/sign.
     * Additive; kolom incoming 07a tidak diubah nullability/semantiknya.
     */
    public function up(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->string('recipient', 150)->nullable()->after('sender');
            $table->string('official_number', 100)->nullable()->unique()->after('source_number');
            $table->string('workflow_status', 20)->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('workflow_status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('issued_at')->nullable()->after('approved_by');
            $table->foreignId('issued_by')->nullable()->after('issued_at')
                ->constrained('users')->cascadeOnUpdate()->nullOnDelete();

            $table->index('recipient');
            $table->index(['direction', 'workflow_status'], 'letters_direction_workflow_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropUnique(['official_number']);
            $table->dropIndex(['recipient']);
            $table->dropIndex('letters_direction_workflow_status_index');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('issued_by');
            $table->dropColumn([
                'recipient',
                'official_number',
                'workflow_status',
                'approved_at',
                'issued_at',
            ]);
        });
    }
};
