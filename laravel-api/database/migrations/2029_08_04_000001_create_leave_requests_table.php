<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 08a (DEC-35): pengajuan izin siswa dan pegawai.
     * Additive: tabel baru; tidak mengubah domain lama. Tidak ada soft delete;
     * pembatalan adalah status bisnis, bukan penghapusan.
     */
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20);
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('student_rombel_id')->nullable()->constrained('student_rombels')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('requested_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'start_date'], 'leave_requests_status_start_index');
            $table->index(['subject_type', 'student_id', 'start_date'], 'leave_requests_student_start_index');
            $table->index(['employee_id', 'start_date'], 'leave_requests_employee_start_index');
            $table->index(['school_year_id', 'student_rombel_id'], 'leave_requests_year_rombel_index');
            $table->index(['type', 'start_date'], 'leave_requests_type_start_index');
            $table->index('requested_by', 'leave_requests_requested_by_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
