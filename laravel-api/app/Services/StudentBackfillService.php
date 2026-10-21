<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StudentBackfillService
{
    /**
     * Backfill student_id on student_rombels from students.nik.
     *
     * Rules (DEC-20):
     * - Only backfill when student_id is NULL.
     * - Only backfill when student_nik is non-empty, valid 16-digit numeric string,
     *   and matches EXACTLY one student in students table.
     * - Unresolved rows (empty, invalid format, unmatched, ambiguous) remain untouched.
     * - Idempotent: safe to run multiple times without corrupting data.
     *
     * @return array<string, int>
     */
    public function backfill(bool $dryRun = false): array
    {
        $stats = [
            'total_rows' => 0,
            'already_resolved' => 0,
            'matched' => 0,
            'unresolved_empty' => 0,
            'unresolved_invalid_format' => 0,
            'unresolved_unmatched' => 0,
            'unresolved_ambiguous' => 0,
            'total_unresolved' => 0,
        ];

        $hasStudentId = \Illuminate\Support\Facades\Schema::hasColumn('student_rombels', 'student_id');
        $columns = $hasStudentId ? ['id', 'student_id', 'student_nik'] : ['id', 'student_nik'];

        $rombels = DB::table('student_rombels')->get($columns);
        $stats['total_rows'] = $rombels->count();

        foreach ($rombels as $row) {
            if ($hasStudentId && $row->student_id !== null) {
                $stats['already_resolved']++;

                continue;
            }

            $rawNik = $row->student_nik;
            $nik = $rawNik !== null ? trim((string) $rawNik) : '';

            if ($nik === '') {
                $stats['unresolved_empty']++;

                continue;
            }

            if (! preg_match('/^\d{16}$/', $nik)) {
                $stats['unresolved_invalid_format']++;

                continue;
            }

            $matches = DB::table('students')->where('nik', $nik)->pluck('id');

            if ($matches->isEmpty()) {
                $stats['unresolved_unmatched']++;

                continue;
            }

            if ($matches->count() > 1) {
                $stats['unresolved_ambiguous']++;

                continue;
            }

            $stats['matched']++;

            if (! $dryRun && $hasStudentId) {
                DB::table('student_rombels')
                    ->where('id', $row->id)
                    ->whereNull('student_id')
                    ->update(['student_id' => $matches->first()]);
            }
        }

        $stats['total_unresolved'] = $stats['unresolved_empty']
            + $stats['unresolved_invalid_format']
            + $stats['unresolved_unmatched']
            + $stats['unresolved_ambiguous'];

        return $stats;
    }

    /**
     * Check if existing data violates the single active rombel per student per year rule.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findConflictingActiveMemberships(): array
    {
        $rows = DB::table('student_rombels as sr1')
            ->join('school_rombels as r1', 'r1.id', '=', 'sr1.school_rombel')
            ->join('student_rombels as sr2', function ($join) {
                $join->on('sr1.id', '<', 'sr2.id');
            })
            ->join('school_rombels as r2', 'r2.id', '=', 'sr2.school_rombel')
            ->whereColumn('r1.school_year_id', 'r2.school_year_id')
            ->whereNull('sr1.deleted_at')
            ->whereNull('sr2.deleted_at')
            ->where(function ($q) {
                $q->where('sr1.student_status_id', 1)->orWhereNull('sr1.student_status_id');
            })
            ->where(function ($q) {
                $q->where('sr2.student_status_id', 1)->orWhereNull('sr2.student_status_id');
            })
            ->whereNull('sr1.latest_student_exit_date')
            ->whereNull('sr2.latest_student_exit_date')
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('sr1.student_id')
                        ->whereColumn('sr1.student_id', 'sr2.student_id');
                })->orWhere(function ($sub) {
                    $sub->whereNull('sr1.student_id')
                        ->whereNotNull('sr1.student_nik')
                        ->whereColumn('sr1.student_nik', 'sr2.student_nik');
                });
            })
            ->select([
                'sr1.id as row1_id',
                'sr2.id as row2_id',
                'r1.school_year_id',
            ])
            ->get();

        return $rows->toArray();
    }
}
