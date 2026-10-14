<?php

namespace Database\Seeders;

use App\Models\Ability;
use App\Models\Role;
use App\Support\AbilityCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeder idempotent untuk katalog kemampuan dan peran awal (DEC-03).
 *
 * Tidak memuat identitas pribadi. Aman dijalankan berulang: kemampuan dan
 * peran dibuat/diperbarui berdasarkan nama, dan pemetaan peran→kemampuan
 * disinkronkan ke konfigurasi awal.
 *
 * PENTING: pemetaan peran→kemampuan di sini adalah konfigurasi AWAL. Setelah
 * dijalankan, Admin Sistem dapat mengubahnya lewat UI tanpa mengubah seeder.
 * Menjalankan ulang seeder ini akan menyetel ulang pemetaan ke default untuk
 * peran bawaan; dokumentasikan bila dipakai pada database yang sudah dikonfigurasi.
 */
class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Katalog kemampuan.
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

        // 2. Peran awal + pemetaan kemampuan.
        foreach (AbilityCatalog::initialRoles() as $slug => $roleDef) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $roleDef['name'],
                    'description' => $roleDef['description'],
                    'is_protected' => $roleDef['protected'],
                ]
            );

            $abilityIds = Ability::whereIn('name', $roleDef['abilities'])->pluck('id')->all();
            $role->abilities()->sync($abilityIds);
        }
    }
}
