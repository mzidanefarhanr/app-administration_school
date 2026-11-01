<?php

use App\Models\Ability;
use App\Models\Role;
use App\Support\AbilityCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Fase 06c (DEC-31): kemampuan sensitif koreksi periode terkunci.
     * Hanya dikaitkan ke Admin Sistem; tidak mengubah pemetaan ability lain.
     */
    public function up(): void
    {
        $def = AbilityCatalog::definitions()['students.correct-locked'] ?? null;
        if (! $def) {
            return;
        }

        $ability = Ability::firstOrCreate(
            ['name' => 'students.correct-locked'],
            [
                'group' => $def['group'],
                'description' => $def['description'],
                'is_sensitive' => $def['sensitive'],
            ]
        );

        $role = Role::where('slug', 'admin-sistem')->first();
        if ($role && ! $role->abilities()->where('abilities.id', $ability->id)->exists()) {
            $role->abilities()->attach($ability->id);
        }
    }

    public function down(): void
    {
        $ability = Ability::where('name', 'students.correct-locked')->first();
        if ($ability) {
            $ability->roles()->detach();
            $ability->delete();
        }
    }
};
