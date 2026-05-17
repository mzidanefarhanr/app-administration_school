<?php

namespace App\Http\Controllers;

use App\Models\EducationSchool;
// use App\Http\Requests\StoreEducationSchoolRequest;
// use App\Http\Requests\UpdateEducationSchoolRequest;
use App\Http\Resources\StatusResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EducationSchoolController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all Education Schools
        $educationSchools = EducationSchool::with(['educationLevel','district'])->latest()->get();
        // $educationSchools = EducationSchool::with(['educationLevel','district.regency.province'])->latest()->get();

        //return collection of Education Schools as a resource
        return new StatusResource(true, 'Education Schools List', $educationSchools);
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
            'npsn' => [
                'required', 'min:8', 'max:100',
                Rule::unique('education_schools')->whereNull('deleted_at'),
            ],
        ]);

        //check if validation fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // ── STEP 1: Check npsn against soft-deleted records ────────────────────────
        $existingFirst = EducationSchool::withTrashed()->withCount('educationLevel')
            ->where('npsn', $request->npsn)
            ->whereNotNull('deleted_at')
            ->first();

        if ($existingFirst) {
            // if education level not exist anymore, then you couldn't restore
            if ($existingFirst->education_level_count == 0)
                return response()->json(['message' => 'Cannot restore: The associated Education Level no longer exists! Please contact Administrator!'], 422);
            // No conflict — safe to restore and update
            $existingFirst->restore();
            $existingFirst->update($request->only([
                'name',
                'npsn',
                'education_level_id',
                'status_education',
                'address',
                'district_id',
            ]));

            return new StatusResource(true, 'Education School successfully restored!', $existingFirst);
        }

        // ── STEP 2: All clear — create fresh user ────────────────────────────────
        $educationSchool = EducationSchool::create([
            'name'                  => $request->name,
            'npsn'                  => $request->npsn,
            'education_level_id'    => $request->education_level_id,
            'status_education'      => $request->status_education,
            'address'               => $request->address,
            'district_id'           => $request->district_id,
        ]);

        return new StatusResource(true, 'New Education School successfully added!', $educationSchool);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //find Education School by ID
        $educationSchool = EducationSchool::find($id);

        //return single Education School as a resource
        return new StatusResource(true, 'Education School detail found!', $educationSchool);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EducationSchool $educationSchool)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //find Education School by ID
        $educationSchool = EducationSchool::firstWhere('id', $id);


        if (isset($educationSchool)) {
            //define validation rules
            $validator = Validator::make($request->all(), [
                'npsn'              => ['required', 'min:8', 'max:100',
                    Rule::unique('education_schools')->ignore($id)],
            ]);

            //check if validation fails
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            //update Education School
            $educationSchool->update([
                'name'                  => $request->name,
                'npsn'                  => $request->npsn,
                'education_level_id'    => $request->education_level_id,
                'status_education'      => $request->status_education,
                'address'               => $request->address,
                'district_id'           => $request->district_id,
            ]);

            //return response
            return new StatusResource(true, 'Education School successfully updated!', $educationSchool);

        } else {
            return response()->json(['message' => 'Education School not found!'], 404);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, Request $request)
    {
        //find Education School by ID
        $educationSchool = EducationSchool::firstWhere('id', $id);

        if (isset($educationSchool)) {
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

                //delete Education School
                $educationSchool->delete();

                //return response
                return new StatusResource(true, 'Education School successfully deleted!', null);
            } else {
                return response()->json(['message' => 'You are not allowed to delete this data!'], 400);
            }

        } else {
            return response()->json(['message' => 'Education School not found!'], 404);
        }
    }
}
