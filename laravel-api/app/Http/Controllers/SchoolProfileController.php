<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolProfileRequest;
use App\Http\Requests\UpdateSchoolProfileRequest;
use App\Http\Resources\StatusResource;
use App\Models\SchoolProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SchoolProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all School Profiles
        $SchoolProfiles = SchoolProfile::with([
            'educationSchool',
            'principal',
            'schoolYear',
            'statusPrincipal'
        ])->latest()->get();

        //return collection of School Profiles as a resource
        return new StatusResource(true, 'School Profiles List', $SchoolProfiles);
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
    public function store(StoreSchoolProfileRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $SchoolProfile = SchoolProfile::with([
            'educationSchool',
            'principal',
            'schoolYear',
            'statusPrincipal'
        ])->where('school_year_id', $id)->get();
        return new StatusResource(true, 'School Profile detail found!', $SchoolProfile);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SchoolProfile $schoolProfile)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $schoolProfile = SchoolProfile::with([
            'educationSchool',
            'principal',
        ])->findOrFail($id);

        // ── Validation ─────────────────────────────────────────────────────
        $validator = Validator::make($request->all(), [
            // FK fields
            'education_school_npsn' => ['sometimes', 'exists:education_schools,npsn'],
            'principal_id'        => ['sometimes', 'exists:users,id'],

            // Direct text fields
            'nds'                    => ['sometimes', 'nullable', 'string', 'max:100'],
            'nss'                    => ['sometimes', 'nullable', 'string', 'max:100'],
            'official_number'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'email'                  => ['sometimes', 'nullable', 'email', 'max:100'],
            'website'                => ['sometimes', 'nullable', 'string', 'max:100'],
            'nrks'                   => ['sometimes', 'nullable', 'string', 'max:100'],
            'nuptk'                  => ['sometimes', 'nullable', 'string', 'max:100'],
            'tmt_principal'          => ['sometimes', 'nullable', 'date'],
            'school_committee_name'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'school_committee_number'=> ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // ── Only update fields that were actually sent ──────────────────────
        // This is important — each inline edit only sends ONE field at a time
        $fillable = [
            'education_school_npsn',
            'principal_id',
            'nds',
            'nss',
            'official_number',
            'email',
            'website',
            'nrks',
            'nuptk',
            'tmt_principal',
            'school_committee_name',
            'school_committee_number',
        ];

        $dataToUpdate = [];

        // if ($request->has('education_school_npsn')) {
        //     $educationSchool = \App\Models\EducationSchool::where('npsn', $request->education_school_npsn)
        //         ->first();

        //     if (!$educationSchool) {
        //         return response()->json([
        //             'message' => 'Education School with NPSN ' . $request->education_school_npsn . ' not found.'
        //         ], 404);
        //     }

        //     $dataToUpdate['education_school_id'] = $educationSchool->id; // ← store the id in DB
        // }

        foreach ($fillable as $field) {
            if ($request->has($field)) {
                $dataToUpdate[$field] = $request->$field;
            }
        }

        $schoolProfile->update($dataToUpdate);

        // Reload relations after update so response reflects new data
        $schoolProfile->load(['educationSchool', 'principal']);

        return new StatusResource(true, 'School Profile successfully updated by Admin!!', $schoolProfile);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SchoolProfile $schoolProfile)
    {
        //
    }
}
