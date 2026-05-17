<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Validator;

class FirstLoginController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //find users by ID
        $users = User::firstWhere('id', $id);

        if (isset($users)) {
            //define validation rules
            $validator = Validator::make($request->all(), [
                'first_login_at'    => ['required'],
                'status_active'     => ['required'],
            ]);

            //check if validation fails
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            //update users first_login_at & status_active
            $users->update([
                'first_login_at'    => $request->first_login_at,
                'status_active'     => $request->status_active,
            ]);

            //return response
            return new UserResource(true, 'Data User Berhasil Terautentikasi!', $users);
        } else {
            return response()->json(['message' => 'Data User Tidak Ditemukan!'], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
