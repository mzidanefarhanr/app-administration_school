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
            'teaching.view' => [
                'group' => 'teaching',
                'description' => 'Melihat jadwal/jurnal pembelajaran.',
                'is_sensitive' => false,
            ],
            'teaching.manage' => [
                'group' => 'teaching',
                'description' => 'Mengelola jadwal/jurnal pembelajaran.',
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
            'admin-sistem' => ['teaching.view', 'teaching.manage'],
            'operator-sekolah' => ['teaching.view', 'teaching.manage'],
            'wakil-kesiswaan' => ['teaching.view', 'teaching.manage'],
            'kepala-sekolah' => ['teaching.view'],
            'guru' => ['teaching.view'],
        ];

        foreach ($roleGrants as $slug => $grantNames) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            if ($roleId === null) {
                continue;
            }

            foreach ($grantNames as $abilityName) {
                $abilityId = $abilityIds[$abilityName] ?? null;
                if ($abilityId === null) {
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
        if (! Schema::hasTable('abilities') || ! Schema::hasTable('roles') || ! Schema::hasTable('ability_role')) {
            return;
        }

        $manageId = DB::table('abilities')->where('name', 'teaching.manage')->value('id');
        $viewId = DB::table('abilities')->where('name', 'teaching.view')->value('id');

        $operatorId = DB::table('roles')->where('slug', 'operator-sekolah')->value('id');
        if ($operatorId !== null && $manageId !== null) {
            DB::table('ability_role')
                ->where('role_id', $operatorId)
                ->where('ability_id', $manageId)
                ->delete();
        }

        $guruId = DB::table('roles')->where('slug', 'guru')->value('id');
        if ($guruId !== null && $viewId !== null) {
            DB::table('ability_role')
                ->where('role_id', $guruId)
                ->where('ability_id', $viewId)
                ->delete();
        }
    }
};
