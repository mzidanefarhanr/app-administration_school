<?php

use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use App\Models\StudentMutation;
use App\Models\StudentRombel;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaultAdmin = User::first();
        $adminId = $defaultAdmin ? $defaultAdmin->id : 1;
        $defaultYear = SchoolYear::where('name', '2024/2025')->first() ?? SchoolYear::first();
        $defaultYearId = $defaultYear ? $defaultYear->id : 6;
        $defaultSchoolNpsn = '20107400';

        // 1. Backfill Mutasi Keluar (student_status_id = 2)
        $outMemberships = StudentRombel::where('student_status_id', 2)
            ->whereNull('deleted_at')
            ->get();

        foreach ($outMemberships as $membership) {
            $existing = StudentMutation::where('student_id', $membership->student_id)
                ->where('type', StudentMutation::TYPE_OUT)
                ->first();

            if (! $existing) {
                $rombel = SchoolRombel::find($membership->school_rombel);
                $yearId = $rombel?->school_year_id ?: $defaultYearId;
                $effectiveDate = $membership->latest_student_exit_date ?: '2025-01-20';
                $npsn = $membership->latest_student_exit_school_id ?: $defaultSchoolNpsn;

                StudentMutation::create([
                    'version' => 1,
                    'type' => StudentMutation::TYPE_OUT,
                    'school_year_id' => $yearId,
                    'student_id' => $membership->student_id,
                    'student_rombel_id' => $membership->id,
                    'school_rombel_id' => $membership->school_rombel,
                    'effective_date' => $effectiveDate,
                    'school_npsn' => $npsn,
                    'reason' => 'Mengundurkan Diri / Pindah Sekolah',
                    'status' => StudentMutation::STATUS_ACTIVE,
                    'created_by' => $adminId,
                ]);
            }
        }

        // 2. Backfill Mutasi Masuk (student_entry_id = 2)
        $inMemberships = StudentRombel::where('student_entry_id', 2)
            ->whereNull('deleted_at')
            ->get();

        foreach ($inMemberships as $membership) {
            $existing = StudentMutation::where('student_id', $membership->student_id)
                ->where('type', StudentMutation::TYPE_IN)
                ->first();

            if (! $existing) {
                $rombel = SchoolRombel::find($membership->school_rombel);
                $yearId = $rombel?->school_year_id ?: $defaultYearId;
                $effectiveDate = $membership->latest_student_entry_date ?: '2024-07-22';
                $npsn = $membership->mutation_education_school_id ?: $defaultSchoolNpsn;

                StudentMutation::create([
                    'version' => 1,
                    'type' => StudentMutation::TYPE_IN,
                    'school_year_id' => $yearId,
                    'student_id' => $membership->student_id,
                    'student_rombel_id' => $membership->id,
                    'school_rombel_id' => $membership->school_rombel,
                    'effective_date' => $effectiveDate,
                    'school_npsn' => $npsn,
                    'reason' => 'Pindahan / Mutasi Masuk',
                    'status' => StudentMutation::STATUS_ACTIVE,
                    'created_by' => $adminId,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Additive backfill migration; rollback is not required.
    }
};
