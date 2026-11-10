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
        Schema::table('school_profiles', function (Blueprint $table) {
            $table->string('education_school_npsn')->nullable()->change();
            $table->unsignedBigInteger('principal_id')->nullable()->change();
            $table->unsignedBigInteger('status_principal_id')->nullable()->change();

            if (! Schema::hasColumn('school_profiles', 'principal_employee_id')) {
                $table->unsignedBigInteger('principal_employee_id')->nullable()->after('principal_id');
                $table->foreign('principal_employee_id')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('school_profiles', 'zip_code')) {
                $table->string('zip_code', 30)->nullable()->after('nis');
            }

            if (! Schema::hasColumn('school_profiles', 'nuptk')) {
                $table->string('nuptk', 100)->nullable()->after('nrks');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('school_profiles', 'principal_employee_id')) {
                $table->dropForeign(['principal_employee_id']);
                $table->dropColumn('principal_employee_id');
            }

            if (Schema::hasColumn('school_profiles', 'zip_code')) {
                $table->dropColumn('zip_code');
            }

            if (Schema::hasColumn('school_profiles', 'nuptk')) {
                $table->dropColumn('nuptk');
            }
        });
    }
};
