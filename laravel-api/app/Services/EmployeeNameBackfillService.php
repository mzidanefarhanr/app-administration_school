<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill employees.name for legacy rows (DEC-24 D1).
 *
 * Primary source: users.name through user_id. Legacy fallback: the unique
 * account matched by user_nik, only for rows still eligible for legacy
 * resolution (pre-04a marker set, no link/unlink history). Names are never
 * guessed from partial data; unresolved rows are reported as aggregate counts
 * only, without exposing any PII.
 */
class EmployeeNameBackfillService
{
    /**
     * @return array<string, int>
     */
    public function backfill(bool $dryRun = false): array
    {
        $stats = [
            'total_rows' => 0,
            'already_named' => 0,
            'filled_from_user_id' => 0,
            'filled_from_legacy_nik' => 0,
            'unresolved_no_account' => 0,
            'unresolved_empty_user_name' => 0,
            'unresolved_ambiguous_legacy' => 0,
            'total_unresolved' => 0,
        ];

        if (! Schema::hasColumn('employees', 'name')) {
            return $stats;
        }

        $run = function () use (&$stats, $dryRun) {
            $employees = DB::table('employees')
                ->orderBy('id')
                ->get([
                    'id',
                    'name',
                    'user_id',
                    'user_id_resolved_at',
                    'account_unlinked_at',
                    'legacy_profile_at',
                    'user_nik',
                ]);
            $stats['total_rows'] = $employees->count();

            foreach ($employees as $row) {
                if ($row->name !== null && trim((string) $row->name) !== '') {
                    $stats['already_named']++;

                    continue;
                }

                $name = null;
                $source = null;

                if ($row->user_id !== null) {
                    $name = DB::table('users')->where('id', $row->user_id)->value('name');
                    $source = 'user_id';
                } elseif ($this->legacyEligible($row) && preg_match('/^\d{16}$/', trim((string) $row->user_nik))) {
                    $matches = DB::table('users')
                        ->where('nik', trim((string) $row->user_nik))
                        ->limit(2)
                        ->pluck('name');

                    if ($matches->count() > 1) {
                        $stats['unresolved_ambiguous_legacy']++;

                        continue;
                    }

                    if ($matches->count() === 1) {
                        $name = $matches->first();
                        $source = 'legacy_nik';
                    }
                }

                if ($source === null) {
                    $stats['unresolved_no_account']++;

                    continue;
                }

                $name = is_string($name) ? trim($name) : '';

                if ($name === '') {
                    $stats['unresolved_empty_user_name']++;

                    continue;
                }

                $stats[$source === 'user_id' ? 'filled_from_user_id' : 'filled_from_legacy_nik']++;

                if (! $dryRun) {
                    $updated = DB::table('employees')
                        ->where('id', $row->id)
                        ->where(function ($query) {
                            $query->whereNull('name')->orWhere('name', '');
                        })
                        ->update(['name' => $name]);

                    if ($updated !== 1) {
                        throw new \RuntimeException(
                            "Employee record {$row->id} changed during name backfill; aborting without partial writes."
                        );
                    }
                }
            }
        };

        if ($dryRun) {
            $run();
        } else {
            DB::transaction($run);
        }

        $stats['total_unresolved'] = $stats['unresolved_no_account']
            + $stats['unresolved_empty_user_name']
            + $stats['unresolved_ambiguous_legacy'];

        return $stats;
    }

    private function legacyEligible(object $row): bool
    {
        return $row->legacy_profile_at !== null
            && $row->user_id === null
            && $row->user_id_resolved_at === null
            && $row->account_unlinked_at === null;
    }
}
