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
        Schema::create('student_rombels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('student_nik');
            $table->foreign('student_nik')->references('nik')->on('students'); // Siswa
            $table->unsignedBigInteger('school_rombel');
            $table->foreign('school_rombel')->references('id')->on('school_rombels'); // Jenis Kelamin
            $table->unsignedBigInteger('student_status_id')->nullable();
            $table->foreign('student_status_id')->references('id')->on('student_statuses'); // Status Keaktifan Siswa
            $table->unsignedBigInteger('student_entry_id')->nullable();
            $table->foreign('student_entry_id')->references('id')->on('student_entries'); // Status Masuk Siswa
            $table->date('latest_student_entry_date')->nullable(); // Tanggal Masuk Siswa
            $table->string('mutation_education_school_id')->nullable();
            $table->foreign('mutation_education_school_id')->references('npsn')->on('education_schools'); // Asal Mutasi Sekolah
            $table->date('latest_student_exit_date')->nullable(); // Tanggal Keluar Siswa
            $table->string('latest_student_exit_school_id')->nullable();
            $table->foreign('latest_student_exit_school_id')->references('npsn')->on('education_schools'); // Sekolah Keluar Siswa
            $table->date('latest_student_graduation_date')->nullable(); // Tanggal Lulus Siswa
            $table->string('latest_information')->nullable(); // Informasi Terbaru
            $table->boolean('check_dapodik')->nullable(); // Dapodik Cek
            $table->string('informations_dapodik')->nullable(); // Informasi Dapodik Terbaru
            // $table->unsignedBigInteger('student_major_id')->nullable();
            // $table->foreign('student_major_id')->references('id')->on('student_majors'); // Jurusan

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
        Schema::dropIfExists('student_rombels');
    }
};
