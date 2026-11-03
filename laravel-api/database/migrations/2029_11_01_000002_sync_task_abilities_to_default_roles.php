<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('abilities') || ! Schema::hasTable('roles') || ! Schema::hasTable('ability_role')) {
            return;
        }

        $now = now();

        $abilities = [
            'tasks.view' => [
                'group' => 'tasks',
                'description' => 'Melihat daftar tugas dan pengingat operasional.',
                'is_sensitive' => false,
            ],
            'tasks.manage' => [
                'group' => 'tasks',
                'description' => 'Mengelola pengingat internal operasional.',
                'is_sensitive' => false,
            ],
        ];

        $abilityIds = [];
        foreach ($abilities as $name => $meta) {
            $existingId = DB::table('abilities')->where('name', $name)->value('id');
            if ($existingId !== null) {
                $abilityIds[$name] = (int) $existingId;

                continue;
            }

            $abilityIds[$name] = (int) DB::table('abilities')->insertGetId([
                'name' => $name,
                'group' => $meta['group'],
                'description' => $meta['description'],
                'is_sensitive' => $meta['is_sensitive'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleGrants = [
            'admin-sistem' => ['tasks.view', 'tasks.manage'],
            'operator-sekolah' => ['tasks.view', 'tasks.manage'],
            'kepala-sekolah' => ['tasks.view'],
            'wakil-kesiswaan' => ['tasks.view'],
            'guru' => ['tasks.view'],
        ];

        foreach ($roleGrants as $roleSlug => $grantedAbilities) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) {
                continue;
            }

            foreach ($grantedAbilities as $abilityName) {
                $abilityId = $abilityIds[$abilityName] ?? null;
                if (! $abilityId) {
                    continue;
                }

                $exists = DB::table('ability_role')
                    ->where('role_id', $roleId)
                    ->where('ability_id', $abilityId)
                    ->exists();

                if (! $exists) {
                    DB::table('ability_role')->insert([
                        'role_id' => $roleId,
                        'ability_id' => $abilityId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('abilities')) {
            return;
        }

        $abilityIds = DB::table('abilities')
            ->whereIn('name', ['tasks.view', 'tasks.manage'])
            ->pluck('id');

        if ($abilityIds->isNotEmpty() && Schema::hasTable('ability_role')) {
            DB::table('ability_role')->whereIn('ability_id', $abilityIds)->delete();
        }

        DB::table('abilities')->whereIn('name', ['tasks.view', 'tasks.manage'])->delete();
    }
};
