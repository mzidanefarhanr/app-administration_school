<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mark employee rows that predate the Phase 04a account-relation foundation.
     *
     * Only these rows may ever resolve an account through the legacy user_nik
     * fallback. Profiles created after 04a always link through user_id, so a
     * deliberate unlink (or a hard-deleted account) can never be undone by NIK.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'legacy_profile_at')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->timestamp('legacy_profile_at')->nullable();
            });
        }

        // Every row present at this point came from the pre-04a schema.
        DB::table('employees')
            ->whereNull('legacy_profile_at')
            ->update(['legacy_profile_at' => now()]);
    }

    /**
     * Dropping the marker makes a profile ineligible for legacy NIK resolution.
     * Refuse while any row still relies on that fallback; otherwise the column
     * can be removed without changing behaviour.
     */
    public function down(): void
    {
        $reliant = DB::table('employees')
            ->whereNotNull('legacy_profile_at')
            ->whereNull('user_id')
            ->whereNull('user_id_resolved_at')
            ->whereNull('account_unlinked_at')
            ->whereNotNull('user_nik')
            ->count();

        if ($reliant > 0) {
            throw new RuntimeException(
                "Refusing to drop legacy_profile_at: {$reliant} row(s) still depend on legacy NIK resolution."
            );
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('legacy_profile_at');
        });
    }
};
