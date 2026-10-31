<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 06a (DEC-29 D4/D6): wali kelas bersumber dari profil pegawai.
     * class_teach_id lama dipertahankan untuk kompatibilitas. Backfill hanya
     * untuk pemetaan user tunggal yang aktif bertipe Pendidik; baris lain
     * dibiarkan null dan dilaporkan sebagai agregat tanpa data pribadi.
     */
    public function up(): void
    {
        Schema::table('school_rombels', function (Blueprint $table) {
            $table->foreignId('class_teach_employee_id')
                ->nullable()
                ->after('class_teach_id')
                ->constrained('employees')
                ->nullOnDelete();
            $table->index(['school_year_id', 'deleted_at']);
        });

        $teacherTypeId = DB::table('employee_types')->where('name', 'Pendidik')->value('id');
        $filled = 0;
        $unresolved = 0;

        if ($teacherTypeId !== null) {
            $candidates = DB::table('school_rombels')
                ->whereNotNull('class_teach_id')
                ->whereNull('class_teach_employee_id')
                ->select(['id', 'class_teach_id'])
                ->cursor();

            foreach ($candidates as $rombel) {
                $employeeIds = DB::table('employees')
                    ->where('user_id', $rombel->class_teach_id)
                    ->whereNull('deleted_at')
                    ->where('employee_type_id', $teacherTypeId)
                    ->pluck('id');

                if ($employeeIds->count() === 1) {
                    DB::table('school_rombels')
                        ->where('id', $rombel->id)
                        ->update(['class_teach_employee_id' => $employeeIds->first()]);
                    $filled++;
                } else {
                    $unresolved++;
                }
            }
        }

        Log::info('Phase 06a backfill class_teach_employee_id', [
            'filled' => $filled,
            'unresolved' => $unresolved,
            'teacher_type_found' => $teacherTypeId !== null,
        ]);
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('school_rombels', function (Blueprint $table) use ($driver) {
            $table->dropIndex(['school_year_id', 'deleted_at']);
            if ($driver === 'mysql') {
                $table->dropForeign(['class_teach_employee_id']);
            }
            $table->dropColumn('class_teach_employee_id');
        });
    }
};
