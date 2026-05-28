<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            // ── RELATIONSHIP MATRIX LAYER ──
            // $table->unsignedBigInteger('user_nik');
            $table->string('user_nik');
            $table->foreign('user_nik')->references('nik')->on('users');
            $table->unsignedBigInteger('employee_status_id');
            $table->foreign('employee_status_id')->references('id')->on('employee_statuses');
            $table->unsignedBigInteger('employee_type_id');
            $table->foreign('employee_type_id')->references('id')->on('employee_types');
            $table->unsignedBigInteger('employment_id');
            $table->foreign('employment_id')->references('id')->on('employments');
            $table->unsignedBigInteger('subject_id');
            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->unsignedBigInteger('religion_id');
            $table->foreign('religion_id')->references('id')->on('religions');
            $table->unsignedBigInteger('district_id');
            $table->foreign('district_id')->references('id')->on('districts');
            $table->unsignedBigInteger('marital_status_id');
            $table->foreign('marital_status_id')->references('id')->on('marital_statuses');
            $table->unsignedBigInteger('gender_id');
            $table->foreign('gender_id')->references('id')->on('genders');

            // ── CORE IDENTIFIER STRINGS ──
            $table->string('nuptk')->nullable();
            $table->string('npwp')->nullable();
            $table->string('no_kk')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('zip_code', 10)->nullable(); // Kept as string to preserve leading zeros safely
            $table->string('wa_number')->nullable();
            $table->string('email')->nullable();

            // ── CONTRACT & SELECTION METADATA ──
            $table->string('appointment_certificate')->nullable();
            $table->date('tmt_employee');
            $table->string('certificate_of_teaching_hours')->nullable();
            $table->string('biological_mothers_name')->nullable();
            $table->string('partners_name')->nullable();

            // ── ACADEMIC HISTORY LAYER ──
            // Elementary School
            $table->string('elementary_school_name')->nullable();
            $table->date('elementary_school_entry')->nullable();
            $table->date('elementary_school_graduation')->nullable();
            $table->string('nisn')->nullable();
            $table->decimal('elementary_school_passing_grade', 5, 2)->nullable(); // e.g., 85.50

            // Junior High School
            $table->string('junior_high_school_name')->nullable();
            $table->date('junior_high_school_entry')->nullable();
            $table->date('junior_high_school_graduation')->nullable();
            $table->decimal('junior_high_school_passing_grade', 5, 2)->nullable();

            // Senior High School
            $table->string('senior_high_school_name')->nullable();
            $table->date('senior_high_school_entry')->nullable();
            $table->date('senior_high_school_graduation')->nullable();
            $table->decimal('senior_high_school_passing_grade', 5, 2)->nullable();

            // Bachelor Degree (S1)
            $table->string('bachelor_campus_name')->nullable();
            $table->string('bachelor_major')->nullable();
            $table->string('bachelor_faculty')->nullable();
            $table->date('bachelor_entry')->nullable();
            $table->date('bachelor_graduation')->nullable();
            $table->string('bachelor_nim')->nullable();
            $table->decimal('bachelor_passing_grade', 4, 2)->nullable(); // Perfect size layout for 4.00 max GPA scale

            // Master Degree (S2)
            $table->string('master_campus_name')->nullable();
            $table->string('master_major')->nullable();
            $table->string('master_faculty')->nullable();
            $table->date('master_entry')->nullable();
            $table->date('master_graduation')->nullable();
            $table->string('master_nim')->nullable();
            $table->decimal('master_passing_grade', 4, 2)->nullable();

            // ── SYSTEM CONFIGURATION BOOLEANS ──
            $table->date('exit_date')->nullable();
            $table->boolean('check_dapodik')->default(false);
            $table->boolean('government_certificate')->default(false);
            $table->softDeletes();
            $table->index('deleted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
