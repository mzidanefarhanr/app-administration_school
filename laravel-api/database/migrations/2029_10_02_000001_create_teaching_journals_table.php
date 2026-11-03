<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')
                ->constrained('school_years')
                ->restrictOnDelete();
            $table->foreignId('semester_id')
                ->constrained('semesters')
                ->restrictOnDelete();
            $table->foreignId('teaching_assignment_id')
                ->constrained('teaching_assignments')
                ->restrictOnDelete();
            $table->foreignId('teaching_schedule_id')
                ->constrained('teaching_schedules')
                ->restrictOnDelete();
            $table->foreignId('teaching_schedule_exception_id')
                ->nullable()
                ->constrained('teaching_schedule_exceptions')
                ->restrictOnDelete();
            $table->foreignId('school_rombel_id')
                ->constrained('school_rombels')
                ->restrictOnDelete();
            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->restrictOnDelete();
            $table->date('session_date');
            $table->string('source_type', 20)->default('regular');
            $table->unsignedTinyInteger('period_start');
            $table->unsignedTinyInteger('period_end');
            $table->char('scheduled_start_time', 5);
            $table->char('scheduled_end_time', 5);
            $table->char('actual_start_time', 5);
            $table->char('actual_end_time', 5);
            $table->string('room', 60)->nullable();
            $table->foreignId('assigned_employee_id')
                ->constrained('employees')
                ->restrictOnDelete();
            $table->foreignId('teacher_employee_id')
                ->constrained('employees')
                ->restrictOnDelete();
            $table->string('topic', 200);
            $table->string('method', 60)->default('lecture');
            $table->text('summary')->nullable();
            $table->text('notes')->nullable();
            $table->string('correction_reason', 255)->nullable();
            $table->foreignId('corrected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['teaching_schedule_id', 'session_date'],
                'uq_teaching_journals_schedule_date'
            );
            $table->index(
                ['school_year_id', 'semester_id', 'session_date'],
                'idx_teaching_journals_year_sem_date'
            );
            $table->index(
                ['school_rombel_id', 'session_date'],
                'idx_teaching_journals_rombel_date'
            );
            $table->index(
                ['teacher_employee_id', 'session_date'],
                'idx_teaching_journals_teacher_date'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_journals');
    }
};
