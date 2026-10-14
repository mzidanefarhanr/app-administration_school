<?php

use App\Models\Ability;
use App\Models\Role;
use App\Models\User;
use App\Support\AbilityCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 01a — data migration: seed katalog kemampuan/peran awal dan petakan
 * pengguna lama ke peran baru.
 *
 * Keputusan pemilik (DEC-14, 2026-10-14 WIB): pemetaan legacy memakai BUKTI NAMA
 * peran legacy (type_users.name), bukan angka ID:
 *   - type_users.name = 'Admin'  → peran 'admin-sistem' (Admin Sistem).
 *   - type_users.name = 'User'   → TIDAK dipetakan otomatis. Peran pengguna
 *     legacy 'User' ditetapkan manual oleh admin (default deny sampai ditetapkan).
 *   - nama lain / tanpa tipe     → tanpa peran (default deny).
 *
 * Idempotent: hanya menambah role_user yang belum ada. Tidak mengubah kolom
 * users yang sudah ada (type_user_id/status tetap) dan tidak menghapus
 * penetapan peran yang dibuat admin secara eksplisit.
 *
 * down(): hanya menghapus role_user hasil auto-map 'Admin' (bila assigned_by
 * masih null, yaitu belum ditetapkan manual), tidak menghapus users.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Pastikan katalog kemampuan ada.
        foreach (AbilityCatalog::definitions() as $name => $def) {
            Ability::updateOrCreate(
                ['name' => $name],
                [
                    'group' => $def['group'],
                    'description' => $def['description'],
                    'is_sensitive' => $def['sensitive'],
                ]
            );
        }

        // 2. Pastikan peran awal ada + sinkronkan kemampuan awal (hanya bila
        //    peran belum memiliki kemampuan apa pun, agar tidak menimpa
        //    konfigurasi yang sudah disesuaikan admin).
        foreach (AbilityCatalog::initialRoles() as $slug => $roleDef) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $roleDef['name'],
                    'description' => $roleDef['description'],
                    'is_protected' => $roleDef['protected'],
                ]
            );

            if ($role->abilities()->count() === 0) {
                $role->abilities()->sync(
                    Ability::whereIn('name', $roleDef['abilities'])->pluck('id')->all()
                );
            }
        }

        // 3. Auto-map HANYA 'Admin' → 'admin-sistem'. 'User' tidak dipetakan otomatis.
        $adminRoleId = Role::where('slug', 'admin-sistem')->value('id');

        if ($adminRoleId !== null) {
            $adminTypeId = DB::table('type_users')->where('name', 'Admin')->value('id');

            if ($adminTypeId !== null) {
                $userIds = DB::table('users')
                    ->where('type_user_id', $adminTypeId)
                    ->pluck('id');

                foreach ($userIds as $userId) {
                    $exists = DB::table('role_user')
                        ->where('user_id', $userId)
                        ->where('role_id', $adminRoleId)
                        ->exists();

                    if (! $exists) {
                        DB::table('role_user')->insert([
                            'user_id' => $userId,
                            'role_id' => $adminRoleId,
                            'assigned_by' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Hapus hanya penautan hasil auto-map 'Admin' yang belum ditetapkan manual.
        $adminRoleId = DB::table('roles')->where('slug', 'admin-sistem')->value('id');
        $adminTypeId = DB::table('type_users')->where('name', 'Admin')->value('id');

        if ($adminRoleId === null || $adminTypeId === null) {
            return;
        }

        $userIds = DB::table('users')->where('type_user_id', $adminTypeId)->pluck('id');

        DB::table('role_user')
            ->where('role_id', $adminRoleId)
            ->whereNull('assigned_by')
            ->whereIn('user_id', $userIds)
            ->delete();
    }
};
