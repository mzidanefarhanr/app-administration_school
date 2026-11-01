<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 06c (DEC-31): koreksi periode terkunci yang immutable dan self-auditing.
     * Snapshot before/after hanya memuat ID, status, tanggal, dan versi.
     */
    public function up(): void
    {
        Schema::create('student_locked_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('action', 30);
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('mutation_id')->nullable()->constrained('student_mutations')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('student_rombel_id')->nullable()->constrained('student_rombels')->cascadeOnUpdate()->restrictOnDelete();
            $table->json('before');
            $table->json('after');
            $table->string('reason', 255);
            $table->string('approval_reference', 100)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->index(['school_year_id', 'student_id'], 'locked_corrections_year_student_index');
            $table->index('mutation_id');
            $table->index('student_rombel_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_locked_corrections');
    }
};
