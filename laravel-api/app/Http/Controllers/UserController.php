<?php

namespace App\Http\Controllers;

use App\Models\User;
// use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all users
        $users = User::with(['status_user','type_user'])->orderBy('name')->get();

        //return collection of users as a resource
        return new UserResource(true, 'Users List', $users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //define validation rules
        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'min:3', 'max:100',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'email' => [
                'required', 'email:rfc,dns', 'max:60',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'username' => [
                'required', 'min:3', 'max:100',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'nik' => [
                'required', 'min:16',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'type_user_id'   => ['required', 'min:1'],
            'status_user_id' => ['required', 'min:1'],
        ]);

        //check if validation fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
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
                ->where(function($query) use ($request) {
                    $query->where('name',     $request->name)
                        ->orWhere('email',    $request->email)
                        ->orWhere('username', $request->username);
                })
                ->first();

            if ($conflicts) {
                if ($conflicts->name     === $request->name)     $conflictingFields[] = 'name';
                if ($conflicts->email    === $request->email)    $conflictingFields[] = 'email';
                if ($conflicts->username === $request->username) $conflictingFields[] = 'username';

                return response()->json([
                    'message' => 'Failed to create a new user. The following data is already in use by a deleted account: '
                        . implode(', ', $conflictingFields)
                        . '. Please use the NIK associated with that data to restore it, or change the '
                        . implode(', ', $conflictingFields)
                        . ' to something new.',
                ], 422);
            }

            // No conflict — safe to restore and update
            $existingByNik->restore();
            $existingByNik->update([
                'name'           => $request->name,
                'email'          => $request->email,
                'username'       => $request->username,
                'password'       => bcrypt($request->password),
                'nik'            => $request->nik,
                'type_user_id'   => $request->type_user_id,
                'status_user_id' => $request->status_user_id,
                'status_active'  => 0,
                'first_login_at' => 0,
            ]);

            return new UserResource(true, 'Users successfully restored!', $existingByNik);
        }

        // ── STEP 2: NIK is clean — now check name/email/username against soft-deleted ──
        $conflictingFields = [];

        $conflictsByOtherFields = User::withTrashed()
            ->whereNotNull('deleted_at')
            ->where(function($query) use ($request) {
                $query->where('name',     $request->name)
                    ->orWhere('email',    $request->email)
                    ->orWhere('username', $request->username);
            })
            ->first();

        if ($conflictsByOtherFields) {
            if ($conflictsByOtherFields->name     === $request->name)     $conflictingFields[] = 'name';
            if ($conflictsByOtherFields->email    === $request->email)    $conflictingFields[] = 'email';
            if ($conflictsByOtherFields->username === $request->username) $conflictingFields[] = 'username';

            return response()->json([
                'message' => 'Failed to create a new user. The following data is already in use by a deleted account: '
                    . implode(', ', $conflictingFields)
                    . '. Please use that account\'s NIK to restore it, or replace the '
                    . implode(', ', $conflictingFields)
                    . ' with new data.',
            ], 422);
        }

        // ── STEP 3: All clear — create fresh user ────────────────────────────────
        $users = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'email_verified_at' => now(),
            'username'          => $request->username,
            'password'          => bcrypt($request->password),
            'nik'               => $request->nik,
            'first_login_at'    => 0,
            'status_active'     => 0,
            'type_user_id'      => $request->type_user_id,
            'status_user_id'    => $request->status_user_id,
        ]);

        return new UserResource(true, 'New Users successfully added!', $users);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //find users by ID
        $users = User::find($id);

        //return single users as a resource
        return new UserResource(true, 'Users detail found!', $users);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //find users by ID
        $users = User::firstWhere('id', $id);


        if (isset($users)) {
            // ── Optimistic Lock check ─────────────────────────────────────────────
            if ((int) $request->version !== (int) $users->version) {
                return response()->json([
                    'message' => 'This record has been updated by someone else. Please refresh the page to see the latest changes.',
                    'latest'  => new UserResource(true, 'Latest data', $users),
                ], 409); // 409 Conflict
            }

            //check if type_editor is 1 or admin
            $editor = User::firstWhere('id', auth()->id());
            if ($editor['type_user_id'] == 1 && $editor['id'] != $users['id']) {
                //define validation rules
                $validator = Validator::make($request->all(), [
                    'name'              => ['required', 'min:3', 'max:100',
                        Rule::unique('users')->ignore($id)->whereNull('deleted_at')],
                    'email'             => ['required', 'email:rfc,dns', 'max:60',
                        Rule::unique('users')->ignore($id)->whereNull('deleted_at')],
                    'username'          => ['required', 'min:3', 'max:100',
                        Rule::unique('users')->ignore($id)->whereNull('deleted_at')],
                    'nik'               => ['required', 'min:16',
                        Rule::unique('users')->ignore($id)->whereNull('deleted_at')],
                    'type_user_id'      => ['required'],
                    'status_user_id'    => ['required'],
                    // 'remember_token'    => ['required'],
                ]);

                //check if validation fails
                if ($validator->fails()) {
                    return response()->json($validator->errors(), 422);
                }

                if ($request->password_new) {
                    //update users with new password # user
                    //Hash the password_new
                    //$password_new = bcrypt($request->password_new);
                    $password_new = Hash::make($request->password_new);
                    $users->update([
                        'name'              => $request->name,
                        'email'             => $request->email,
                        'email_verified_at' => now(),
                        'username'          => $request->username,
                        'password'          => $password_new,
                        'nik'               => $request->nik,
                        'type_user_id'      => $request->type_user_id,
                        'status_user_id'    => $request->status_user_id,
                        'version'           => $users->version + 1,
                    ]);

                    //return response
                    return new UserResource(true, 'User & Password successfully updated by Admin!', $users);
                } else {
                    //update users without new password # user
                    $users->update([
                        'name'              => $request->name,
                        'email'             => $request->email,
                        'email_verified_at' => now(),
                        'username'          => $request->username,
                        'nik'               => $request->nik,
                        'type_user_id'      => $request->type_user_id,
                        'status_user_id'    => $request->status_user_id,
                        'version'           => $users->version + 1,
                    ]);

                    //return response
                    return new UserResource(true, 'User successfully updated by Admin!', $users);
                }
            } else {
                //define validation rules
                $validator = Validator::make($request->all(), [
                    'name'              => ['required', 'min:3', 'max:100'],
                    'email'             => ['required', 'email:rfc,dns', 'max:60'],
                    'username'          => ['required', 'min:3', 'max:100'],
                    'nik'               => ['required', 'min:16'],
                    'type_user_id'      => ['required'],
                    'status_user_id'    => ['required'],
                    'password'          => ['required', 'current_password'],
                    // 'remember_token'    => ['required'],
                ]);

                //check if validation fails
                if ($validator->fails()) {
                    return response()->json($validator->errors(), 422);
                }

                if ($request->password_new) {
                    //update users with new password # user
                    $password_new = Hash::make($request->password_new);
                    $users->update([
                        'name'              => $request->name,
                        'email'             => $request->email,
                        'email_verified_at' => now(),
                        'username'          => $request->username,
                        'password'          => bcrypt($request->password_new),
                        'nik'               => $request->nik,
                        'version'           => $users->version + 1,
                    ]);

                    //return response
                    return new UserResource(true, 'User successfully updated!', $users);
                } else {
                    //update users without new password # user
                    $users->update([
                        'name'              => $request->name,
                        'email'             => $request->email,
                        'email_verified_at' => now(),
                        'username'          => $request->username,
                        'nik'               => $request->nik,
                        'version'           => $users->version + 1,
                    ]);

                    //return response
                    return new UserResource(true, 'User successfully updated!', $users);
                }

            }


        } else {
            return response()->json(['message' => 'User not found!'], 404);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, Request $request)
    {
        //find user by ID
        $user = User::firstWhere('id', $id);

        if (isset($user)) {
            //define validation rules
            $validator = Validator::make($request->all(), [
                'delete'             => 'required',
            ]);

            //check if validation fails
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            //check if delete is true
            if ($request->delete == 'true') {

                //delete user
                $user->delete();

                //return response
                return new UserResource(true, 'User successfully deleted!', null);
            } else {
                return response()->json(['message' => 'You are not allowed to delete this data!'], 400);
            }

        } else {
            return response()->json(['message' => 'User not found!'], 404);
        }
    }
}
