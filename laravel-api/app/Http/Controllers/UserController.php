<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Atribut administratif (peran/status tipe) yang hanya boleh diubah
     * oleh pengguna dengan kemampuan users.manage.
     *
     * @var array<int, string>
     */
    private const ADMIN_FIELDS = ['type_user_id', 'status_user_id', 'status_active'];

    public function index()
    {
        $this->authorize('users.view');

        $users = User::with(['status_user', 'type_user', 'roles:id,name,slug'])
            ->orderBy('name')
            ->get();

        return new UserResource(true, 'Users List', $users);
    }

    public function store(Request $request)
    {
        $this->authorize('users.manage');

        // define validation rules
        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'min:3', 'max:100',
            ],
            'email' => [
                'required', 'email:rfc', 'max:60',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'username' => [
                'required', 'string', 'min:3', 'max:100',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'nik' => [
                'required', 'string', 'digits:16',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', Password::defaults()],
            'type_user_id' => ['required', 'exists:type_users,id'],
            'status_user_id' => ['required', 'exists:status_users,id'],
        ]);

        // check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // ── STEP 1: Check NIK against soft-deleted records ────────────────────────
        $existingByNik = User::withTrashed()
            ->where('nik', $request->nik)
            ->whereNotNull('deleted_at')
            ->first();

        if ($existingByNik) {
            // NIK belongs to a soft-deleted record — check other fields for conflicts
            $conflictingFields = [];

            $conflicts = User::withTrashed()
                ->whereNotNull('deleted_at')
                ->where('id', '!=', $existingByNik->id)
                ->where(function ($query) use ($request) {
                    $query->where('email', $request->email)
                        ->orWhere('username', $request->username);
                })
                ->first();

            if ($conflicts) {
                if ($conflicts->email === $request->email) {
                    $conflictingFields[] = 'email';
                }
                if ($conflicts->username === $request->username) {
                    $conflictingFields[] = 'username';
                }

                return response()->json([
                    'message' => 'Failed to create a new user. The following data is already in use by a deleted account: '
                        .implode(', ', $conflictingFields)
                        .'. Please use the NIK associated with that data to restore it, or change the '
                        .implode(', ', $conflictingFields)
                        .' to something new.',
                ], 422);
            }

            // No conflict — safe to restore and update
            DB::transaction(function () use ($existingByNik, $request) {
                $existingByNik->restore();
                $existingByNik->update([
                    'name' => $request->name,
                    'email' => $request->email,
                    'username' => $request->username,
                    'password' => bcrypt($request->password),
                    'nik' => $request->nik,
                    'type_user_id' => $request->type_user_id,
                    'status_user_id' => $request->status_user_id,
                    'status_active' => 0,
                    'first_login_at' => 0,
                ]);
            });

            return new UserResource(true, 'Users successfully restored!', $existingByNik);
        }

        // ── STEP 2: NIK is clean — now check email/username against soft-deleted ──
        $conflictingFields = [];

        $conflictsByOtherFields = User::withTrashed()
            ->whereNotNull('deleted_at')
            ->where(function ($query) use ($request) {
                $query->where('email', $request->email)
                    ->orWhere('username', $request->username);
            })
            ->first();

        if ($conflictsByOtherFields) {
            if ($conflictsByOtherFields->email === $request->email) {
                $conflictingFields[] = 'email';
            }
            if ($conflictsByOtherFields->username === $request->username) {
                $conflictingFields[] = 'username';
            }

            return response()->json([
                'message' => 'Failed to create a new user. The following data is already in use by a deleted account: '
                    .implode(', ', $conflictingFields)
                    .'. Please use that account\'s NIK to restore it, or replace the '
                    .implode(', ', $conflictingFields)
                    .' with new data.',
            ], 422);
        }

        // ── STEP 3: All clear — create fresh user ────────────────────────────────
        $users = DB::transaction(function () use ($request) {
            return User::create([
                'name' => $request->name,
                'email' => $request->email,
                'email_verified_at' => now(),
                'username' => $request->username,
                'password' => bcrypt($request->password),
                'nik' => $request->nik,
                'first_login_at' => 0,
                'status_active' => 0,
                'type_user_id' => $request->type_user_id,
                'status_user_id' => $request->status_user_id,
            ]);
        });

        return new UserResource(true, 'New Users successfully added!', $users);
    }

    public function show($id)
    {
        $this->authorize('users.view');

        $users = User::with(['status_user', 'type_user', 'roles:id,name,slug'])->find($id);

        return new UserResource(true, 'Users detail found!', $users);
    }

    /**
     * Update akun.
     *
     * Dua jalur:
     *  - Profil sendiri (actor == target): hanya nama/email/username/nik/password.
     *    Atribut administratif (type_user_id/status_user_id/status_active) DIABAIKAN
     *    dan tidak boleh dipakai untuk menaikkan hak akses sendiri.
     *  - Pengelolaan akun (butuh users.manage): boleh mengubah atribut administratif,
     *    dengan perlindungan admin pengelola akses terakhir.
     */
    public function update(Request $request, $id)
    {
        $users = User::firstWhere('id', $id);

        if (! isset($users)) {
            return response()->json(['message' => 'User not found!'], 404);
        }

        $actor = $request->user();
        $isSelf = (int) $actor->id === (int) $users->id;

        // Izin: mengelola akun orang lain butuh users.manage; memperbarui diri sendiri selalu boleh.
        if (! $isSelf) {
            $this->authorize('users.manage');
        }

        // ── Optimistic Lock check ─────────────────────────────────────────────
        if ($request->filled('version') && (int) $request->version !== (int) $users->version) {
            return response()->json([
                'message' => 'This record has been updated by someone else. Please refresh the page to see the latest changes.',
                'latest' => new UserResource(true, 'Latest data', $users),
            ], 409); // 409 Conflict
        }

        // Tentukan field yang boleh diubah berdasarkan izin.
        $canManageAdminFields = ! $isSelf && $actor->hasAbility('users.manage');

        // Cegah eskalasi diri: jika self, buang atribut administratif.
        $payload = $request->all();
        if (! $canManageAdminFields) {
            foreach (self::ADMIN_FIELDS as $field) {
                unset($payload[$field]);
            }
        }

        $rules = [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:60',
                Rule::unique('users')->ignore($id)->whereNull('deleted_at')],
            'username' => ['required', 'string', 'min:3', 'max:100',
                Rule::unique('users')->ignore($id)->whereNull('deleted_at')],
            'nik' => ['required', 'string', 'digits:16',
                Rule::unique('users')->ignore($id)->whereNull('deleted_at')],
            'type_user_id' => ['sometimes', 'required', 'exists:type_users,id'],
            'status_user_id' => ['sometimes', 'required', 'exists:status_users,id'],
        ];

        if ($request->filled('password_new')) {
            $rules['password_new'] = ['string', Password::defaults()];
        }

        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Untuk self update: wajib password lama (sesuai perilaku lama).
        if ($isSelf) {
            $pwValidator = Validator::make($request->all(), [
                'password' => ['required', 'current_password'],
            ]);
            if ($pwValidator->fails()) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $pwValidator->errors(),
                ], 422);
            }
        }

        // ── Perlindungan admin pengelola akses terakhir ───────────────────────
        $willLoseAccess = $this->wouldRemoveLastAccessManager($users, $payload);
        if ($willLoseAccess) {
            return response()->json([
                'message' => 'Change rejected: this would remove account management access '
                    .'from the last active administrator. Assign another access manager first.',
            ], 422);
        }

        $users = DB::transaction(function () use ($users, $payload, $request, $canManageAdminFields) {
            $updates = [
                'name' => $payload['name'],
                'email' => $payload['email'],
                'email_verified_at' => now(),
                'username' => $payload['username'],
                'nik' => $payload['nik'],
                'version' => $users->version + 1,
            ];

            if ($canManageAdminFields) {
                if (isset($payload['type_user_id'])) {
                    $updates['type_user_id'] = $payload['type_user_id'];
                }
                if (isset($payload['status_user_id'])) {
                    $updates['status_user_id'] = $payload['status_user_id'];
                }
                if (array_key_exists('status_active', $payload)) {
                    $updates['status_active'] = (bool) $payload['status_active'];
                }
            }

            if ($request->filled('password_new')) {
                $updates['password'] = bcrypt($request->password_new);
            }

            $users->update($updates);

            return $users;
        });

        $message = $canManageAdminFields
            ? ($request->filled('password_new')
                ? 'User & Password successfully updated by Admin!'
                : 'User successfully updated by Admin!')
            : ($request->filled('password_new')
                ? 'User & Password successfully updated!'
                : 'User successfully updated!');

        return new UserResource(true, $message, $users);
    }

    public function destroy(Request $request, $id)
    {
        $this->authorize('users.manage');

        $user = User::firstWhere('id', $id);

        if (! isset($user)) {
            return response()->json(['message' => 'User not found!'], 404);
        }

        // Tidak boleh menghapus diri sendiri.
        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        // Perlindungan admin pengelola akses terakhir.
        if ($this->isLastAccessManager($user)) {
            return response()->json([
                'message' => 'Cannot delete the last access administrator.',
            ], 422);
        }

        // define validation rules
        $validator = Validator::make($request->all(), [
            'delete' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->delete == 'true') {
            DB::transaction(fn () => $user->delete());

            return new UserResource(true, 'User successfully deleted!', null);
        }

        return response()->json(['message' => 'You are not allowed to delete this data!'], 400);
    }

    /**
     * Apakah $user adalah satu-satunya admin aktif yang mampu mengelola akses?
     */
    private function isLastAccessManager(User $user): bool
    {
        if (! $user->status_active || ! $user->canManageAccess()) {
            return false;
        }

        return User::query()
            ->where('status_active', true)
            ->whereKeyNot($user->id)
            ->get()
            ->filter(fn (User $u) => $u->canManageAccess())
            ->isEmpty();
    }

    /**
     * Apakah payload update akan membuat target kehilangan status pengelola akses
     * terakhir? Mis. menonaktifkan, atau memindahkan status aktif/tipe akun
     * pengelola terakhir.
     *
     * @param  array<string, mixed>  $payload
     */
    private function wouldRemoveLastAccessManager(User $target, array $payload): bool
    {
        if (! $this->isLastAccessManager($target)) {
            return false;
        }

        // Bila bukan pengelola terakhir, tidak relevan (sudah ditangani di atas).
        $deactivating = array_key_exists('status_active', $payload) && ! $payload['status_active'];
        $typeChanged = array_key_exists('type_user_id', $payload)
            && (int) $payload['type_user_id'] !== (int) $target->type_user_id;

        return $deactivating || $typeChanged;
    }
}
