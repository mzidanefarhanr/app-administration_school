<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->restrictOnDelete();
            $table->string('exception_type', 20); // holiday | cancelled | rescheduled
            $table->date('exception_date');
            $table->foreignId('school_rombel_id')->nullable()->constrained('school_rombels')->restrictOnDelete();
            $table->foreignId('teaching_schedule_id')->nullable()->constrained('teaching_schedules')->restrictOnDelete();
            $table->date('rescheduled_date')->nullable();
            $table->unsignedTinyInteger('rescheduled_period_start')->nullable();
            $table->unsignedTinyInteger('rescheduled_period_end')->nullable();
            $table->string('rescheduled_start_time', 5)->nullable();
            $table->string('rescheduled_end_time', 5)->nullable();
            $table->string('rescheduled_room', 60)->nullable();
            $table->string('rescheduled_room_normalized', 60)->nullable();
            $table->foreignId('substitute_employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->string('reason', 255);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['school_year_id', 'semester_id', 'exception_date', 'exception_type'],
                'teaching_sched_exc_period_date_type_idx'
            );
            $table->index(
                ['teaching_schedule_id', 'exception_date'],
                'teaching_sched_exc_schedule_date_idx'
            );
            $table->index(
                ['rescheduled_date', 'exception_type'],
                'teaching_sched_exc_resched_date_type_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_schedule_exceptions');
    }
};
