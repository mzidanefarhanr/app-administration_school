<?php

namespace App\Http\Controllers;

// use App\Http\Requests\StoreEducationLevelRequest;
// use App\Http\Requests\UpdateEducationLevelRequest;
use App\Http\Resources\StatusResource;
use App\Models\EducationLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EducationLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all Education Levels
        $educationLevels = EducationLevel::select('name', 'id')->latest()->get();

        //return collection of Education Levels as a resource
        return new StatusResource(true, 'Education Levels List', $educationLevels);
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
                Rule::unique('education_levels')->whereNull('deleted_at'),
            ],
        ]);

        //check if validation fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // ── STEP 1: Check name against soft-deleted records ────────────────────────
        $existingFirst = EducationLevel::withTrashed()
            ->where('name', $request->name)
            ->whereNotNull('deleted_at')
            ->first();

        if ($existingFirst) {
            // No conflict — safe to restore and update
            $existingFirst->restore();
            $existingFirst->update([
                'name'           => $request->name,
            ]);

            return new StatusResource(true, 'Education Level successfully restored!', $existingFirst);
        }

        // ── STEP 2: All clear — create fresh user ────────────────────────────────
        $educationLevel = EducationLevel::create([
            'name'              => $request->name,
        ]);

        return new StatusResource(true, 'New Education Level successfully added!', $educationLevel);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //find Education Level by ID
        $educationLevel = EducationLevel::find($id);

        //return single Education Level as a resource
        return new StatusResource(true, 'Education Level detail found!', $educationLevel);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EducationLevel $educationLevel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //find Education Level by ID
        $educationLevel = EducationLevel::firstWhere('id', $id);


        if (isset($educationLevel)) {
            //define validation rules
            $validator = Validator::make($request->all(), [
                'name'              => ['required', 'min:3', 'max:100',
                    Rule::unique('education_levels')->ignore($id)],
            ]);

            //check if validation fails
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            //update Education Level
            $educationLevel->update([
                'name'              => $request->name,
            ]);

            //return response
            return new StatusResource(true, 'Education Level successfully updated!', $educationLevel);

        } else {
            return response()->json(['message' => 'Education Level not found!'], 404);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, Request $request)
    {
        //find Education Level by ID
        $educationLevel = EducationLevel::withCount('educationSchools')->findOrFail($id);

        if ($educationLevel->education_schools_count > 0)
            $conflicts[] = "{$educationLevel->education_schools_count} Education Schools";

        // If any relation has data, block the delete
        if (!empty($conflicts)) {
            $conflictList = count($conflicts) > 1
                ? implode(', ', array_slice($conflicts, 0, -1)) . ' and ' . end($conflicts)
                : $conflicts[0];
            return response()->json([
                'message' => "This record is still being referenced by {$conflictList}.",
            ], 422);
        }


        if (isset($educationLevel)) {
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

                //delete Education Level
                $educationLevel->delete();

                //return response
                return new StatusResource(true, 'Education Level successfully deleted!', null);
            } else {
                return response()->json(['message' => 'You are not allowed to delete this data!'], 400);
            }

        } else {
            return response()->json(['message' => 'Education Level not found!'], 404);
        }
    }
}
