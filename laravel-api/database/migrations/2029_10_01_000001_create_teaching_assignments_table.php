<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->restrictOnDelete();
            $table->foreignId('school_rombel_id')->constrained('school_rombels')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->unsignedTinyInteger('hours_per_week')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('notes', 255)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['school_year_id', 'semester_id', 'school_rombel_id', 'subject_id', 'employee_id'],
                'teaching_assignments_unique_period_rombel_subject_teacher'
            );
            $table->index(['school_year_id', 'semester_id', 'status'], 'teaching_assignments_period_status_idx');
            $table->index(['school_rombel_id', 'status'], 'teaching_assignments_rombel_status_idx');
            $table->index(['employee_id', 'status'], 'teaching_assignments_employee_status_idx');
            $table->index(['subject_id', 'status'], 'teaching_assignments_subject_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
    }
};
