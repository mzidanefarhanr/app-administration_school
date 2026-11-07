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
        Schema::table('student_rombels', function (Blueprint $table) {
            if (! Schema::hasColumn('student_rombels', 'latest_student_graduation_date')) {
                $table->date('latest_student_graduation_date')->nullable()->after('latest_student_exit_school_id');
            }
            if (! Schema::hasColumn('student_rombels', 'latest_information')) {
                $table->string('latest_information')->nullable()->after('latest_student_graduation_date');
            }
            if (! Schema::hasColumn('student_rombels', 'check_dapodik')) {
                $table->boolean('check_dapodik')->nullable()->after('latest_information');
            }
            if (! Schema::hasColumn('student_rombels', 'informations_dapodik')) {
                $table->string('informations_dapodik')->nullable()->after('check_dapodik');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_rombels', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('student_rombels', 'informations_dapodik')) {
                $columnsToDrop[] = 'informations_dapodik';
            }
            if (Schema::hasColumn('student_rombels', 'check_dapodik')) {
                $columnsToDrop[] = 'check_dapodik';
            }
            if (Schema::hasColumn('student_rombels', 'latest_information')) {
                $columnsToDrop[] = 'latest_information';
            }
            if (Schema::hasColumn('student_rombels', 'latest_student_graduation_date')) {
                $columnsToDrop[] = 'latest_student_graduation_date';
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
