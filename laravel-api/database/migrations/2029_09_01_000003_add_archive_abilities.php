<?php

use App\Models\Ability;
use App\Models\Role;
use App\Support\AbilityCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Fase 09a (DEC-37 D4): kemampuan arsip.
     *
     * Additive/idempotent: hanya menambah ability/pivot yang belum ada dan
     * tidak mengubah peran yang sudah dikonfigurasi admin.
     */
    public function up(): void
    {
        $roleMap = [
            'archives.view' => ['admin-sistem', 'operator-sekolah', 'kepala-sekolah', 'wakil-kesiswaan'],
            'archives.manage' => ['admin-sistem', 'operator-sekolah'],
        ];

        foreach ($roleMap as $name => $slugs) {
            $def = AbilityCatalog::definitions()[$name] ?? null;

            if ($def === null) {
                continue;
            }

            $ability = Ability::firstOrCreate(
                ['name' => $name],
                [
                    'group' => $def['group'],
                    'description' => $def['description'],
                    'is_sensitive' => $def['sensitive'],
                ]
            );

            foreach ($slugs as $slug) {
                $role = Role::where('slug', $slug)->first();

                if ($role && ! $role->abilities()->where('abilities.id', $ability->id)->exists()) {
                    $role->abilities()->attach($ability->id);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['archives.view', 'archives.manage'] as $name) {
            $ability = Ability::where('name', $name)->first();

            if ($ability) {
                $ability->roles()->detach();
                $ability->delete();
            }
        }
    }
};
