<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 06a (DEC-29 D1): periode akademik aktif disimpan pada tabel singleton
     * id=1. Migration tidak menebak tahun/semester aktif; nilai dibiarkan null
     * sampai operator menetapkannya lewat UI.
     */
    public function up(): void
    {
        Schema::create('academic_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('active_school_year_id')->nullable()->constrained('school_years')->nullOnDelete();
            $table->foreignId('active_semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('academic_settings')->insertOrIgnore([
            'id' => 1,
            'active_school_year_id' => null,
            'active_semester_id' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_settings');
    }
};
