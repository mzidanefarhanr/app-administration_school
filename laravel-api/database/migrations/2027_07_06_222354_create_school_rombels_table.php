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
        Schema::create('school_rombels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('version')->default(1);
            $table->unsignedBigInteger('school_level_id');
            $table->foreign('school_level_id')->references('id')->on('school_levels');
            $table->unsignedBigInteger('school_year_id');
            $table->foreign('school_year_id')->references('id')->on('school_years');
            $table->string('name');
            $table->unsignedBigInteger('student_major_id')->nullable();
            $table->foreign('student_major_id')->references('id')->on('student_majors'); // Jurusan
            $table->unsignedBigInteger('class_teach_id')->nullable();
            $table->foreign('class_teach_id')->references('id')->on('users'); // Wali Kelas
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
        Schema::dropIfExists('school_rombels');
    }
};
