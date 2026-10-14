<?php

namespace App\Http\Controllers;

use App\Models\Ability;

/**
 * Fase 01a — katalog kemampuan.
 *
 * Ditegakkan lewat Gate bernama:
 *   - roles.view      : melihat daftar kemampuan (bagian dari pengelolaan akses).
 *   - abilities.manage: kemampuan untuk mengubah katalog (create/update/delete),
 *                       bila suatu saat dibutuhkan. Saat ini katalog di-seed dari
 *                       AbilityCatalog; endpoint hanya menyediakan baca.
 */
class AbilityController extends Controller
{
    public function index()
    {
        $this->authorize('roles.view');

        $abilities = Ability::orderBy('group')->orderBy('name')->get();

        return response()->json(['success' => true, 'message' => 'Abilities list', 'data' => $abilities]);
    }

    public function show($id)
    {
        $this->authorize('roles.view');

        $ability = Ability::findOrFail($id);

        return response()->json(['success' => true, 'message' => 'Ability detail', 'data' => $ability]);
    }
}
