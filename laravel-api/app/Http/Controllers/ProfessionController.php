<?php

namespace App\Http\Controllers;

use App\Models\Profession;
// use App\Http\Requests\StoreProfessionRequest;
// use App\Http\Requests\UpdateProfessionRequest;
use App\Http\Resources\StatusResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all Professions
        $professions = Profession::latest()->get();

        //return collection of Professions as a resource
        return new StatusResource(true, 'List Professions', $professions);
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
                Rule::unique('professions')->whereNull('deleted_at'),
            ],
        ]);

        //check if validation fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // ── STEP 1: Check name against soft-deleted records ────────────────────────
        $existingFirst = Profession::withTrashed()
            ->where('name', $request->name)
            ->whereNotNull('deleted_at')
            ->first();

        if ($existingFirst) {
            // No conflict — safe to restore and update
            $existingFirst->restore();
            $existingFirst->update([
                'name'           => $request->name,
            ]);

            return new StatusResource(true, 'Profession successfully restored!', $existingFirst);
        }

        // ── STEP 2: All clear — create fresh user ────────────────────────────────
        $profession = Profession::create([
            'name'              => $request->name,
        ]);

        return new StatusResource(true, 'New Profession successfully added!', $profession);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //find Profession by ID
        $profession = Profession::find($id);

        //return single Profession as a resource
        return new StatusResource(true, 'Profession detail found!', $profession);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Profession $profession)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //find Profession by ID
        $profession = Profession::firstWhere('id', $id);

        if (isset($profession)) {

            //define validation rules
            $validator = Validator::make($request->all(), [
                'name'              => ['required', 'min:3', 'max:100',
                    Rule::unique('professions')->ignore($id)],
            ]);

            //check if validation fails
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            //update Profession
            $profession->update([
                'name'              => $request->name,
            ]);

            //return response
            return new StatusResource(true, 'Profession successfully updated!', $profession);

        } else {
            return response()->json(['message' => 'Profession not found!'], 404);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, Request $request)
    {
        //find Profession by ID
        $profession = Profession::withCount('studentsProfession', 'fathersProfession', 'mothersProfession', 'guardiansProfession')->findOrFail($id);

        // Build a list of all relations that still have data
        $conflicts = [];

        if ($profession->students_profession_count > 0)
            $conflicts[] = "{$profession->students_profession_count} students profession";
        if ($profession->fathers_profession_count > 0)
            $conflicts[] = "{$profession->fathers_profession_count} fathers profession";
        if ($profession->mothers_profession_count > 0)
            $conflicts[] = "{$profession->mothers_profession_count} mothers profession";
        if ($profession->guardians_profession_count > 0)
            $conflicts[] = "{$profession->guardians_profession_count} guardians profession";

        // If any relation has data, block the delete
        if (!empty($conflicts)) {
            $conflictList = count($conflicts) > 1
                ? implode(', ', array_slice($conflicts, 0, -1)) . ' and ' . end($conflicts)
                : $conflicts[0];
            return response()->json([
                'message' => "Cannot delete! This record is still being referenced by {$conflictList}.",
            ], 422);
        }

        if (isset($profession)) {
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

                //delete Profession
                $profession->delete();

                //return response
                return new StatusResource(true, 'Profession successfully deleted!', null);
            } else {
                return response()->json(['message' => 'You are not allowed to delete this data!'], 400);
            }

        } else {
            return response()->json(['message' => 'Profession not found!'], 404);
        }
    }
}
