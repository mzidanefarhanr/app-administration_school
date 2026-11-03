<?php

use App\Models\Ability;
use App\Support\AbilityCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Fase 09b (DEC-38 D6 Opsi A): kemampuan archives.purge dibuat tetapi
     * TIDAK diberikan ke peran mana pun. Eksekusi pemusnahan data tidak
     * diotorisasi; penugasan ability ini memerlukan keputusan pemilik terpisah.
     */
    public function up(): void
    {
        $def = AbilityCatalog::definitions()['archives.purge'] ?? null;

        if ($def === null) {
            return;
        }

        Ability::firstOrCreate(
            ['name' => 'archives.purge'],
            [
                'group' => $def['group'],
                'description' => $def['description'],
                'is_sensitive' => $def['sensitive'],
            ]
        );
    }

    public function down(): void
    {
        $ability = Ability::where('name', 'archives.purge')->first();

        if ($ability) {
            $ability->roles()->detach();
            $ability->delete();
        }
    }
};
