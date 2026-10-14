<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Fase 01a — penetapan peran pengguna (access.assign).
 *
 * Ditegakkan via Gate 'access.assign'. Perubahan peran dicatat ke audit
 * (dibuat server, tanpa rahasia autentikasi).
 */
class AccessAssignmentController extends Controller
{
    /**
     * Menetapkan satu peran ke pengguna.
     */
    public function assignRole(Request $request, $userId)
    {
        $this->authorize('access.assign');

        $user = User::findOrFail($userId);

        $validator = Validator::make($request->all(), [
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $role = Role::findOrFail($request->role_id);

        // Cegah eskalasi diri: pengguna tidak boleh menambah peran ke dirinya sendiri.
        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'message' => 'Anda tidak dapat mengubah peran Anda sendiri.',
            ], 422);
        }

        DB::transaction(function () use ($user, $role, $request) {
            $user->roles()->syncWithoutDetaching([
                $role->id => ['assigned_by' => $request->user()->id],
            ]);

            $this->logAccessChange('access.assign', $user, $role, 'assign');
        });

        return response()->json([
            'success' => true,
            'message' => 'Role assigned',
            'data' => $user->fresh()->load('roles:id,name,slug'),
        ]);
    }

    /**
     * Mencabut satu peran dari pengguna.
     */
    public function revokeRole(Request $request, $userId, $roleId)
    {
        $this->authorize('access.assign');

        $user = User::findOrFail($userId);
        $role = Role::findOrFail($roleId);

        // Cegah mengubah peran sendiri (termasuk mencabut).
        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'message' => 'Anda tidak dapat mengubah peran Anda sendiri.',
            ], 422);
        }

        // Perlindungan admin pengelola akses terakhir: jangan biarkan pencabutan
        // menghilangkan kemampuan mengelola akses dari pengelola terakhir.
        if ($this->wouldRemoveLastAccessManager($user, $role)) {
            return response()->json([
                'message' => 'Perubahan ditolak: ini akan menghilangkan akses pengelolaan '
                    .'akun dari admin aktif terakhir.',
            ], 422);
        }

        DB::transaction(function () use ($user, $role) {
            $user->roles()->detach($role->id);
            $this->logAccessChange('access.assign', $user, $role, 'revoke');
        });

        return response()->json([
            'success' => true,
            'message' => 'Role revoked',
            'data' => $user->fresh()->load('roles:id,name,slug'),
        ]);
    }

    private function wouldRemoveLastAccessManager(User $user, Role $role): bool
    {
        // Hanya relevan bila peran yang dicabut adalah peran pengelola akses.
        $managerAbilities = ['roles.manage', 'abilities.manage', 'access.assign'];
        $roleIsManager = $role->abilities()->whereIn('name', $managerAbilities)->exists();

        if (! $roleIsManager) {
            return false;
        }

        if (! $user->status_active || ! $user->canManageAccess()) {
            return false;
        }

        // Setelah pencabutan, apakah masih ada pengelola lain?
        $otherManagers = User::query()
            ->where('status_active', true)
            ->whereKeyNot($user->id)
            ->get()
            ->filter(fn (User $u) => $u->canManageAccess());

        if ($otherManagers->isNotEmpty()) {
            return false;
        }

        // Cek apakah kemampuan kelola user ini masih ada lewat peran lain.
        $remainingAbilities = $user->roles()
            ->whereKeyNot($role->id)
            ->with('abilities')
            ->get()
            ->flatMap(fn (Role $r) => $r->abilities->pluck('name'))
            ->unique();

        return $remainingAbilities->intersect($managerAbilities)->isEmpty();
    }

    private function logAccessChange(string $action, User $target, Role $role, string $change): void
    {
        Activity::create([
            'user_id' => auth()->id(),
            'table_name' => 'role_user',
            'record_id' => $target->id,
            'information' => $change === 'assign' ? 'Created' : 'Deleted',
            'before' => null,
            'after' => null,
            'new' => $change === 'assign'
                ? json_encode(['action' => $action, 'user_id' => $target->id, 'role' => $role->slug])
                : null,
            'delete' => $change === 'revoke'
                ? json_encode(['action' => $action, 'user_id' => $target->id, 'role' => $role->slug])
                : null,
        ]);
    }
}
