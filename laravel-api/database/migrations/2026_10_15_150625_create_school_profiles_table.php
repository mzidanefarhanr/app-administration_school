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
        Schema::create('school_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('education_school_npsn');
            $table->foreign('education_school_npsn')->references('npsn')->on('education_schools');
            $table->unsignedBigInteger('principal_id');
            $table->foreign('principal_id')->references('id')->on('users');
            $table->unsignedBigInteger('school_year_id');
            $table->foreign('school_year_id')->unique()->references('id')->on('school_years');
            $table->unsignedBigInteger('status_principal_id');
            $table->foreign('status_principal_id')->references('id')->on('status_users');
            $table->string('nds')->nullable();
            $table->string('nss')->nullable();
            $table->string('nis')->nullable();
            $table->string('nrks')->nullable();
            $table->date('tmt_principal')->nullable();
            $table->string('official_number')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('school_committee_name')->nullable();
            $table->string('school_committee_number')->nullable();
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
        Schema::dropIfExists('school_profiles');
    }
};
