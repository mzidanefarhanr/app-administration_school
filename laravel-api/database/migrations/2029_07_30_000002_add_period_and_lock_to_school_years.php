<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 06a (DEC-29 D2/D3): rentang tanggal dan lock per tahun ajaran.
     * Semua kolom additive dan nullable; tanggal tidak ditebak dari nama.
     */
    public function up(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('name');
            $table->date('end_date')->nullable()->after('start_date');
            $table->timestamp('locked_at')->nullable()->after('end_date');
            $table->foreignId('locked_by')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            $table->string('lock_reason', 255)->nullable()->after('locked_by');
            $table->index('locked_at');
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('school_years', function (Blueprint $table) use ($driver) {
            $table->dropIndex(['locked_at']);
            if ($driver === 'mysql') {
                $table->dropForeign(['locked_by']);
            }
            $table->dropColumn(['start_date', 'end_date', 'locked_at', 'locked_by', 'lock_reason']);
        });
    }
};
