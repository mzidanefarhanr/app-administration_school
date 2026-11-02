<?php

use App\Models\Ability;
use App\Models\Role;
use App\Support\AbilityCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Fase 08a (DEC-35 D9): kemampuan self-service izin dan peran awal guru.
     *
     * Additive/idempotent: hanya menambah ability/pivot yang belum ada dan tidak
     * mengubah peran/ability yang sudah dikonfigurasi admin. Peran `guru` dibuat
     * hanya bila belum ada; peran lama tidak dihapus/diubah namanya.
     */
    public function up(): void
    {
        $def = AbilityCatalog::definitions()['leaves.request'] ?? null;

        if ($def === null) {
            return;
        }

        $ability = Ability::firstOrCreate(
            ['name' => 'leaves.request'],
            [
                'group' => $def['group'],
                'description' => $def['description'],
                'is_sensitive' => $def['sensitive'],
            ]
        );

        $teacherRole = Role::firstOrCreate(
            ['slug' => 'guru'],
            [
                'name' => 'Teacher',
                'description' => 'Submits leave requests for their own linked employee profile.',
                'is_protected' => false,
                'version' => 1,
            ]
        );

        foreach (['admin-sistem', 'operator-sekolah', 'kepala-sekolah', 'wakil-kesiswaan', 'guru'] as $slug) {
            $role = $slug === 'guru' ? $teacherRole : Role::where('slug', $slug)->first();

            if ($role && ! $role->abilities()->where('abilities.id', $ability->id)->exists()) {
                $role->abilities()->attach($ability->id);
            }
        }
    }

    public function down(): void
    {
        $ability = Ability::where('name', 'leaves.request')->first();

        if ($ability) {
            $ability->roles()->detach();
            $ability->delete();
        }

        $teacherRole = Role::where('slug', 'guru')->first();

        if ($teacherRole) {
            $teacherRole->abilities()->detach();
            $teacherRole->delete();
        }
    }
};
