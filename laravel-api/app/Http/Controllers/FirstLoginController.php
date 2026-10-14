<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FirstLoginController extends Controller
{
    public function index()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show(string $id)
    {
        //
    }

    /**
     * Proses login pertama.
     *
     * Fase 01a: hanya boleh mengubah akun pengguna yang sedang terautentikasi.
     * ID di URL tidak lagi dipakai untuk menargetkan pengguna lain (mencegah IDOR).
     * `status_active` tidak dapat diubah di sini; itu atribut administratif.
     */
    public function update(Request $request, string $id)
    {
        $actor = $request->user();

        if (! $actor) {
            return response()->json(['message' => 'Tidak terautentikasi.'], 401);
        }

        // Tolak bila ID di URL bukan akun actor sendiri.
        if ((string) $actor->id !== (string) $id) {
            return response()->json([
                'message' => 'Anda hanya dapat memperbarui status login akun Anda sendiri.',
            ], 403);
        }

        $users = User::firstWhere('id', $actor->id);

        if (! isset($users)) {
            return response()->json(['message' => 'Data User Tidak Ditemukan!'], 404);
        }

        // Hanya `first_login_at` yang boleh diubah pengguna sendiri.
        // `status_active`/`status_user_id` adalah atribut administratif.
        $validator = Validator::make($request->all(), [
            'first_login_at' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $users->update([
            'first_login_at' => $request->first_login_at,
        ]);

        return new UserResource(true, 'Data User Berhasil Terautentikasi!', $users);
    }

    public function destroy(string $id)
    {
        //
    }
}
