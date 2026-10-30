<?php

use App\Models\Ability;
use App\Models\Role;
use App\Support\AbilityCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Fase 05a (DEC-25 D5): kemampuan students.import untuk Admin Sistem dan
     * Operator Sekolah. Tidak mengubah kemampuan lain yang sudah dikonfigurasi.
     */
    public function up(): void
    {
        $def = AbilityCatalog::definitions()['students.import'] ?? null;
        if (! $def) {
            return;
        }

        $ability = Ability::firstOrCreate(
            ['name' => 'students.import'],
            [
                'group' => $def['group'],
                'description' => $def['description'],
                'is_sensitive' => $def['sensitive'],
            ]
        );

        foreach (['admin-sistem', 'operator-sekolah'] as $slug) {
            $role = Role::where('slug', $slug)->first();
            if ($role && ! $role->abilities()->where('abilities.id', $ability->id)->exists()) {
                $role->abilities()->attach($ability->id);
            }
        }
    }

    public function down(): void
    {
        $ability = Ability::where('name', 'students.import')->first();
        if ($ability) {
            $ability->roles()->detach();
            $ability->delete();
        }
    }
};
