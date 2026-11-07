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
        Schema::table('employees', function (Blueprint $table) {
            // Academic extra fields
            $table->string('junior_high_school_nisn', 50)->nullable()->after('junior_high_school_passing_grade');
            $table->string('senior_high_school_major', 100)->nullable()->after('senior_high_school_name');
            $table->string('senior_high_school_nisn', 50)->nullable()->after('senior_high_school_passing_grade');

            // Dapodik, Verval, and Certification status fields (in English)
            $table->string('dapodik_status', 100)->nullable()->after('check_dapodik');
            $table->string('verval_elementary_school', 50)->nullable()->after('dapodik_status');
            $table->string('verval_junior_high_school', 50)->nullable()->after('verval_elementary_school');
            $table->string('verval_senior_high_school', 50)->nullable()->after('verval_junior_high_school');
            $table->string('verval_bachelor_degree', 50)->nullable()->after('verval_senior_high_school');
            $table->string('verval_diploma', 50)->nullable()->after('verval_bachelor_degree');
            $table->string('certification_status', 50)->nullable()->after('government_certificate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'junior_high_school_nisn',
                'senior_high_school_major',
                'senior_high_school_nisn',
                'dapodik_status',
                'verval_elementary_school',
                'verval_junior_high_school',
                'verval_senior_high_school',
                'verval_bachelor_degree',
                'verval_diploma',
                'certification_status',
            ]);
        });
    }
};
