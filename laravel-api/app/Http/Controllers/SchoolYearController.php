<?php

namespace App\Http\Controllers;

use App\Models\SchoolYear;
// use App\Http\Requests\StoreSchoolYearRequest;
// use App\Http\Requests\UpdateSchoolYearRequest;
use App\Http\Resources\StatusResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SchoolYearController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all School Years
        $schoolYears = SchoolYear::latest()->get();

        //return collection of School Years as a resource
        return new StatusResource(true, 'School Years List', $schoolYears);
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
                Rule::unique('school_years')->whereNull('deleted_at'),
            ],
        ]);

        //check if validation fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // ── STEP 1: Check name against soft-deleted records ────────────────────────
        $existingFirst = SchoolYear::withTrashed()
            ->where('name', $request->name)
            ->whereNotNull('deleted_at')
            ->first();

        if ($existingFirst) {
            // No conflict — safe to restore and update
            $existingFirst->restore();
            $existingFirst->update([
                'name'           => $request->name,
            ]);

            return new StatusResource(true, 'School Year successfully restored!', $existingFirst);
        }

        // ── STEP 2: All clear — create fresh user ────────────────────────────────
        $schoolYear = SchoolYear::create([
            'name'              => $request->name,
        ]);

        return new StatusResource(true, 'New School Year successfully added!', $schoolYear);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //find School Year by ID
        $schoolYear = SchoolYear::find($id);

        //return single School Year as a resource
        return new StatusResource(true, 'School Year detail found!', $schoolYear);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SchoolYear $schoolYear)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //find School Year by ID
        $schoolYear = SchoolYear::firstWhere('id', $id);

        if (isset($schoolYear)) {

            //define validation rules
            $validator = Validator::make($request->all(), [
                'name'              => ['required', 'min:3', 'max:100',
                    Rule::unique('school_years')->ignore($id)],
            ]);

            //check if validation fails
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            //update School Year
            $schoolYear->update([
                'name'              => $request->name,
            ]);

            //return response
            return new StatusResource(true, 'School Year successfully updated!', $schoolYear);

        } else {
            return response()->json(['message' => 'School Year not found!'], 404);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, Request $request)
    {
        //find School Year by ID
        $schoolYear = SchoolYear::withCount('schoolRombels')->findOrFail($id);

        // Build a list of all relations that still have data
        $conflicts = [];

        if ($schoolYear->school_rombels_count > 0)
            $conflicts[] = "{$schoolYear->school_rombels_count} School Rombels";

        // If any relation has data, block the delete
        if (!empty($conflicts)) {
            $conflictList = count($conflicts) > 1
                ? implode(', ', array_slice($conflicts, 0, -1)) . ' and ' . end($conflicts)
                : $conflicts[0];
            return response()->json([
                'message' => "This record is still being referenced by {$conflictList}.",
            ], 422);
        }

        if (isset($schoolYear)) {
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

                //delete School Year
                $schoolYear->delete();

                //return response
                return new StatusResource(true, 'School Year successfully deleted!', null);
            } else {
                return response()->json(['message' => 'You are not allowed to delete this data!'], 400);
            }

        } else {
            return response()->json(['message' => 'School Year not found!'], 404);
        }
    }
}
