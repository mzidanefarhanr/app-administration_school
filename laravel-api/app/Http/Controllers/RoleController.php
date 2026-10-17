<?php

namespace App\Http\Controllers;

use App\Models\Ability;
use App\Models\Activity;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Fase 01a — pengelolaan peran dan pemetaan kemampuan per peran.
 *
 * Ditegakkan lewat Gate bernama:
 *   - roles.view   : melihat daftar/detail peran.
 *   - roles.manage : membuat/mengubah/menghapus peran.
 *   - abilities.manage : mengubah pemetaan kemampuan peran.
 */
class RoleController extends Controller
{
    public function index()
    {
        $this->authorize('roles.view');

        $roles = Role::with('abilities:id,name,group,is_sensitive')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'message' => 'Roles list', 'data' => $roles]);
    }

    public function show($id)
    {
        $this->authorize('roles.view');

        $role = Role::with('abilities:id,name,group,is_sensitive')->findOrFail($id);

        return response()->json(['success' => true, 'message' => 'Role detail', 'data' => $role]);
    }

    public function store(Request $request)
    {
        $this->authorize('roles.manage');

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'min:3', 'max:100', Rule::unique('roles')->whereNull('deleted_at')],
            'slug' => ['required', 'min:3', 'max:100', 'alpha_dash', Rule::unique('roles')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'exists:abilities,name'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $role = DB::transaction(function () use ($request) {
            $role = Role::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'is_protected' => false,
            ]);

            if (is_array($request->abilities)) {
                $abilityIds = Ability::whereIn('name', $request->abilities)->pluck('id')->all();
                $role->abilities()->sync($abilityIds);

                $abilityNames = Ability::whereIn('id', $abilityIds)->pluck('name')->all();
                Activity::create([
                    'user_id' => auth()->id(),
                    'table_name' => 'ability_role',
                    'record_id' => $role->id,
                    'information' => 'Created',
                    'before' => null,
                    'after' => null,
                    'new' => ['role' => $role->slug, 'abilities' => $abilityNames],
                    'delete' => null,
                ]);
            }

            return $role->load('abilities:id,name,group,is_sensitive');
        });

        return response()->json(['success' => true, 'message' => 'Role created', 'data' => $role], 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('roles.manage');

        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'min:3', 'max:100',
                Rule::unique('roles')->ignore($id)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', 'exists:abilities,name'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Peran terproteksi (mis. Admin Sistem) boleh diubah deskripsi/kemampuan
        // tetapi tidak boleh dikosongkan kemampuannya sampai hilang akses kelola
        // bila itu peran pengelola akses terakhir. Lihat validasi di bawah.
        DB::transaction(function () use ($request, $role) {
            if ($request->filled('name')) {
                $role->name = $request->name;
            }
            if ($request->has('description')) {
                $role->description = $request->description;
            }
            $role->version = ($role->version ?? 1) + 1;
            $role->save();

            if ($request->has('abilities')) {
                $oldAbilities = $role->abilities()->pluck('name')->sort()->values()->all();
                $abilityIds = Ability::whereIn('name', $request->abilities ?? [])->pluck('id')->all();
                $role->abilities()->sync($abilityIds);
                $newAbilities = Ability::whereIn('id', $abilityIds)->pluck('name')->sort()->values()->all();

                if ($oldAbilities !== $newAbilities) {
                    Activity::create([
                        'user_id' => auth()->id(),
                        'table_name' => 'ability_role',
                        'record_id' => $role->id,
                        'information' => 'Updated',
                        'before' => ['abilities' => $oldAbilities],
                        'after' => ['abilities' => $newAbilities],
                        'new' => null,
                        'delete' => null,
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Role updated',
            'data' => $role->fresh()->load('abilities:id,name,group,is_sensitive'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorize('roles.manage');

        $role = Role::withCount('users')->findOrFail($id);

        // Peran terproteksi tidak boleh dihapus.
        if ($role->is_protected) {
            return response()->json([
                'message' => 'Peran ini dilindungi dan tidak dapat dihapus.',
            ], 422);
        }

        // Tolak bila masih dipakai pengguna aktif.
        if ($role->users_count > 0) {
            return response()->json([
                'message' => "Tidak dapat menghapus: masih digunakan oleh {$role->users_count} pengguna.",
            ], 422);
        }

        $role->delete();

        return response()->json(['success' => true, 'message' => 'Role deleted', 'data' => null]);
    }
}
