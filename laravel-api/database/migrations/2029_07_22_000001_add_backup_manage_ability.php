<?php

use App\Models\Ability;
use App\Models\Role;
use App\Support\AbilityCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fase 02b: Daftarkan kemampuan bernama backup.manage dan pasangkan
     * ke peran admin-sistem tanpa mengubah kemampuan peran lain yang sudah ada.
     */
    public function up(): void
    {
        $def = AbilityCatalog::definitions()['backup.manage'] ?? null;
        if (! $def) {
            return;
        }

        $ability = Ability::firstOrCreate(
            ['name' => 'backup.manage'],
            [
                'group' => $def['group'],
                'description' => $def['description'],
                'is_sensitive' => $def['sensitive'],
            ]
        );

        $adminRole = Role::where('slug', 'admin-sistem')->first();
        if ($adminRole && ! $adminRole->abilities()->where('abilities.id', $ability->id)->exists()) {
            $adminRole->abilities()->attach($ability->id);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $ability = Ability::where('name', 'backup.manage')->first();
        if ($ability) {
            $ability->roles()->detach();
            $ability->delete();
        }
    }
};
