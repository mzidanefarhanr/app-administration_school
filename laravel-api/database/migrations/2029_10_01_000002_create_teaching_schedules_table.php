<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 = Monday .. 6 = Saturday
            $table->unsignedTinyInteger('period_start'); // 1..12
            $table->unsignedTinyInteger('period_end');   // 1..12, >= period_start
            $table->string('start_time', 5); // HH:MM (24-hour)
            $table->string('end_time', 5);   // HH:MM (24-hour, > start_time)
            $table->string('room', 60)->nullable();
            $table->string('room_normalized', 60)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('notes', 255)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['teaching_assignment_id', 'status'], 'teaching_schedules_assignment_status_idx');
            $table->index(['day_of_week', 'status', 'start_time', 'end_time'], 'teaching_schedules_day_status_time_idx');
            $table->index(['room_normalized', 'day_of_week', 'status'], 'teaching_schedules_room_day_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_schedules');
    }
};
