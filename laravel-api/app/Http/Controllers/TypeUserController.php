<?php

namespace App\Http\Controllers;

// use App\Http\Requests\StoreTypeUserRequest;
// use App\Http\Requests\UpdateTypeUserRequest;
use App\Http\Resources\StatusResource;
use App\Models\TypeUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TypeUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all Type Users
        $typeUsers = TypeUser::latest()->get();

        //return collection of Type Users as a resource
        return new StatusResource(true, 'Type Users list', $typeUsers);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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
                Rule::unique('type_users')->whereNull('deleted_at'),
            ],
        ]);

        //check if validation fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // ── STEP 1: Check name against soft-deleted records ────────────────────────
        $existingFirst = TypeUser::withTrashed()
            ->where('name', $request->name)
            ->whereNotNull('deleted_at')
            ->first();

        if ($existingFirst) {
            // No conflict — safe to restore and update
            $existingFirst->restore();
            $existingFirst->update([
                'name'           => $request->name,
            ]);

            return new StatusResource(true, 'Type User successfully restored!', $existingFirst);
        }

        // ── STEP 2: All clear — create fresh user ────────────────────────────────
        $typeUser = TypeUser::create([
            'name'              => $request->name,
        ]);

        return new StatusResource(true, 'New Type User successfully added!', $typeUser);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //find Type User by ID
        $typeUser = TypeUser::find($id);

        //return single Type User as a resource
        return new StatusResource(true, 'Type User detail found!', $typeUser);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TypeUser $typeUser)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //find Type User by ID
        $typeUser = TypeUser::firstWhere('id', $id);

        if (isset($typeUser)) {

            //define validation rules
            $validator = Validator::make($request->all(), [
                'name'              => ['required', 'min:3', 'max:100',
                    Rule::unique('type_users')->ignore($id)],
            ]);

            //check if validation fails
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            //update Type User
            $typeUser->update([
                'name'              => $request->name,
            ]);

            //return response
            return new StatusResource(true, 'Type User successfully updated!', $typeUser);

        } else {
            return response()->json(['message' => 'Type User not found!'], 404);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, Request $request)
    {
        //find Type User by ID
        $typeUser = TypeUser::withCount('users')->findOrFail($id);

        // Build a list of all relations that still have data
        $conflicts = [];

        if ($typeUser->users_count > 0)
            $conflicts[] = "{$typeUser->users_count} users";

        // If any relation has data, block the delete
        if (!empty($conflicts)) {
            $conflictList = count($conflicts) > 1
                ? implode(', ', array_slice($conflicts, 0, -1)) . ' and ' . end($conflicts)
                : $conflicts[0];
            return response()->json([
                'message' => "Cannot delete! This record is still being referenced by {$conflictList}.",
            ], 422);
        }

        if (isset($typeUser)) {
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

                //delete Type User
                $typeUser->delete();

                //return response
                return new StatusResource(true, 'Type User successfully deleted!', null);
            } else {
                return response()->json(['message' => 'You are not allowed to delete this data!'], 400);
            }

        } else {
            return response()->json(['message' => 'Type User not found!!'], 404);
        }
    }
}
