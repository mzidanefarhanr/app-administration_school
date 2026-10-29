<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeBackfillService
{
    /**
     * Backfill user_id on employees from users.nik (DEC-04).
     *
     * Rules:
     * - Only legacy rows (legacy_profile_at set) are eligible; profiles created
     *   after 04a must link through user_id and never inherit an account by NIK.
     * - Only backfill when user_id is NULL.
     * - Do NOT backfill if user_id_resolved_at or account_unlinked_at is NOT NULL.
     * - Only backfill when user_nik is a non-empty, valid 16-digit numeric string.
     * - Matches EXACTLY one active (not soft-deleted) user account.
     * - User account must not already be linked to another employee profile.
     * - Idempotent, atomic for non-dry runs, and never prints PII.
     *
     * @return array<string, int>
     */
    public function backfill(bool $dryRun = false): array
    {
        $stats = [
            'total_rows' => 0,
            'skipped_non_legacy' => 0,
            'already_resolved' => 0,
            'matched' => 0,
            'unresolved_empty' => 0,
            'unresolved_invalid_format' => 0,
            'unresolved_unmatched' => 0,
            'unresolved_ambiguous' => 0,
            'unresolved_soft_deleted' => 0,
            'unresolved_account_already_linked' => 0,
            'total_unresolved' => 0,
        ];

        if (! Schema::hasColumn('employees', 'user_id')) {
            return $stats;
        }

        $run = function () use (&$stats, $dryRun) {
            $employees = DB::table('employees')
                ->orderBy('id')
                ->get([
                    'id',
                    'user_id',
                    'user_id_resolved_at',
                    'account_unlinked_at',
                    'legacy_profile_at',
                    'user_nik',
                ]);
            $stats['total_rows'] = $employees->count();

            // Accounts already linked anywhere (including soft-deleted profiles) are off limits.
            $linkedUserIds = DB::table('employees')
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->all();

            foreach ($employees as $row) {
                if ($row->legacy_profile_at === null) {
                    $stats['skipped_non_legacy']++;

                    continue;
                }

                if ($row->user_id !== null || $row->user_id_resolved_at !== null || $row->account_unlinked_at !== null) {
                    $stats['already_resolved']++;

                    continue;
                }

                $rawNik = $row->user_nik;
                $nik = $rawNik !== null ? trim((string) $rawNik) : '';

                if ($nik === '') {
                    $stats['unresolved_empty']++;

                    continue;
                }

                if (! preg_match('/^\d{16}$/', $nik)) {
                    $stats['unresolved_invalid_format']++;

                    continue;
                }

                $matches = DB::table('users')
                    ->whereNull('deleted_at')
                    ->where('nik', $nik)
                    ->pluck('id');

                if ($matches->isEmpty()) {
                    $anyMatch = DB::table('users')->where('nik', $nik)->exists();
                    $stats[$anyMatch ? 'unresolved_soft_deleted' : 'unresolved_unmatched']++;

                    continue;
                }

                if ($matches->count() > 1) {
                    $stats['unresolved_ambiguous']++;

                    continue;
                }

                $userId = (int) $matches->first();

                if (isset($linkedUserIds[$userId])) {
                    $stats['unresolved_account_already_linked']++;

                    continue;
                }

                $stats['matched']++;
                $linkedUserIds[$userId] = true;

                if (! $dryRun) {
                    $updated = DB::table('employees')
                        ->where('id', $row->id)
                        ->whereNull('user_id')
                        ->update([
                            'user_id' => $userId,
                            'user_id_resolved_at' => now(),
                        ]);

                    if ($updated !== 1) {
                        throw new \RuntimeException(
                            "Employee record {$row->id} changed during backfill; aborting without partial writes."
                        );
                    }
                }
            }
        };

        if ($dryRun) {
            $run();
        } else {
            // All-or-nothing: a concurrent link collision aborts and rolls back every write.
            DB::transaction($run);
        }

        $stats['total_unresolved'] = $stats['unresolved_empty']
            + $stats['unresolved_invalid_format']
            + $stats['unresolved_unmatched']
            + $stats['unresolved_ambiguous']
            + $stats['unresolved_soft_deleted']
            + $stats['unresolved_account_already_linked'];

        return $stats;
    }
}
