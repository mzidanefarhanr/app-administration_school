<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('name', 100)->nullable()->after('id');
                $table->string('nik', 16)->nullable()->after('name');
                $table->unique('nik');
                $table->string('archive_reason', 255)->nullable()->after('exit_date');
                $table->index('name');
                $table->index('employee_type_id');
                $table->index('employee_status_id');
            });
        } elseif ($driver === 'sqlite') {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('name', 100)->nullable();
                $table->string('nik', 16)->nullable();
                $table->string('archive_reason', 255)->nullable();
            });

            Schema::table('employees', function (Blueprint $table) {
                $table->unique('nik');
                $table->index('name');
                $table->index('employee_type_id');
                $table->index('employee_status_id');
            });
        }

        $this->backfillNamesFromLinkedAccounts();
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        $this->assertRollbackSafe();

        if ($driver === 'mysql') {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique(['nik']);
                $table->dropIndex(['name']);
                $table->dropIndex(['employee_type_id']);
                $table->dropIndex(['employee_status_id']);
                $table->dropColumn(['name', 'nik', 'archive_reason']);
            });
        } elseif ($driver === 'sqlite') {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropUnique(['nik']);
                $table->dropIndex(['name']);
                $table->dropIndex(['employee_type_id']);
                $table->dropIndex(['employee_status_id']);
            });

            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn(['name', 'nik', 'archive_reason']);
            });
        }
    }

    /**
     * Copy users.name only for profiles linked through user_id. Unresolved rows
     * stay NULL and are reported by `app:employee-name-backfill`.
     */
    private function backfillNamesFromLinkedAccounts(): void
    {
        if (! Schema::hasColumn('employees', 'name')) {
            return;
        }

        $rows = DB::table('employees')
            ->whereNull('name')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get(['id', 'user_id']);

        foreach ($rows as $row) {
            $name = DB::table('users')->where('id', $row->user_id)->value('name');
            $name = is_string($name) ? trim($name) : '';

            if ($name === '') {
                continue;
            }

            DB::table('employees')
                ->where('id', $row->id)
                ->whereNull('name')
                ->update(['name' => $name]);
        }
    }

    /**
     * Refuse rollback while any row stores data only present in the new columns.
     */
    private function assertRollbackSafe(): void
    {
        $unrecoverableNames = DB::table('employees')
            ->whereNotNull('name')
            ->where(function ($query) {
                $query->whereNull('user_id')
                    ->orWhereNotIn('name', function ($sub) {
                        $sub->select('name')->from('users')->whereColumn('users.id', 'employees.user_id');
                    });
            })
            ->count();
        $niks = DB::table('employees')->whereNotNull('nik')->count();
        $reasons = DB::table('employees')->whereNotNull('archive_reason')->count();

        if ($unrecoverableNames > 0 || $niks > 0 || $reasons > 0) {
            throw new RuntimeException(sprintf(
                'Refusing to roll back employees profile fields (04b): %d row(s) hold a name that cannot be rebuilt from an account, %d row(s) have a NIK, %d row(s) have an archive reason.',
                $unrecoverableNames,
                $niks,
                $reasons
            ));
        }
    }
};
