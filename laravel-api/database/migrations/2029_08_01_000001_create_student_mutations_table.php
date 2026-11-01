<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 06c (DEC-31): log mutasi masuk/keluar yang immutable (tanpa soft delete).
     * Additive: hanya menambah tabel baru; tabel domain siswa tidak diubah.
     */
    public function up(): void
    {
        Schema::create('student_mutations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('type', 10);
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('student_rombel_id')->nullable()->constrained('student_rombels')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('school_rombel_id')->constrained('school_rombels')->cascadeOnUpdate()->restrictOnDelete();
            $table->date('effective_date');
            $table->string('school_npsn', 20);
            $table->string('reason', 255);
            $table->string('status', 10)->default('active');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'effective_date'], 'student_mutations_student_date_index');
            $table->index(['school_year_id', 'type', 'status'], 'student_mutations_year_type_status_index');
            $table->index('student_rombel_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_mutations');
    }
};
