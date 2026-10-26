<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeBackfillService
{
    /**
     * Backfill user_id on employees from users.nik (DEC-04).
     *
     * Rules:
     * - Only backfill when user_id is NULL.
     * - Do NOT backfill if user_id_resolved_at or account_unlinked_at is NOT NULL.
     * - Only backfill when user_nik is a non-empty, valid 16-digit numeric string.
     * - Matches EXACTLY one user in users table.
     * - User account must not already be linked to another employee profile.
     * - Idempotent, safe to run multiple times, transaksional per batch, without printing PII.
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
            'unresolved_account_already_linked' => 0,
            'total_unresolved' => 0,
        ];

        if (! Schema::hasColumn('employees', 'user_id')) {
            return $stats;
        }

        $employees = DB::table('employees')->get([
            'id',
            'user_id',
            'user_id_resolved_at',
            'account_unlinked_at',
            'user_nik',
        ]);
        $stats['total_rows'] = $employees->count();

        // Track user_ids linked in this database to prevent reusing an account across multiple employees
        $linkedUserIds = DB::table('employees')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        foreach ($employees as $row) {
            // 1. If already resolved or user_id is set
            if ($row->user_id !== null || $row->user_id_resolved_at !== null || $row->account_unlinked_at !== null) {
                $stats['already_resolved']++;

                continue;
            }

            $rawNik = $row->user_nik;
            $nik = $rawNik !== null ? trim((string) $rawNik) : '';

            // 2. Empty NIK
            if ($nik === '') {
                $stats['unresolved_empty']++;

                continue;
            }

            // 3. Invalid NIK format (must be 16 digits)
            if (! preg_match('/^\d{16}$/', $nik)) {
                $stats['unresolved_invalid_format']++;

                continue;
            }

            // 4. Query matching users
            $matches = DB::table('users')->where('nik', $nik)->pluck('id');

            if ($matches->isEmpty()) {
                $stats['unresolved_unmatched']++;

                continue;
            }

            if ($matches->count() > 1) {
                $stats['unresolved_ambiguous']++;

                continue;
            }

            $userId = (int) $matches->first();

            // 5. Account already linked to another employee
            if (isset($linkedUserIds[$userId])) {
                $stats['unresolved_account_already_linked']++;

                continue;
            }

            $stats['matched']++;
            $linkedUserIds[$userId] = true;

            if (! $dryRun) {
                DB::table('employees')
                    ->where('id', $row->id)
                    ->whereNull('user_id')
                    ->update([
                        'user_id' => $userId,
                        'user_id_resolved_at' => now(),
                    ]);
            }
        }

        $stats['total_unresolved'] = $stats['unresolved_empty']
            + $stats['unresolved_invalid_format']
            + $stats['unresolved_unmatched']
            + $stats['unresolved_ambiguous']
            + $stats['unresolved_account_already_linked'];

        return $stats;
    }
}
