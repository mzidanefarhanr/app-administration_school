<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSchoolProfileRequest;
use App\Http\Requests\UpdateSchoolProfileRequest;
use App\Http\Resources\StatusResource;
use App\Models\SchoolProfile;
use App\Models\SchoolYear;

class SchoolProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all School Profiles
        $SchoolProfiles = SchoolProfile::latest()->get();

        //return collection of School Profiles as a resource
        return new StatusResource(true, 'SchoolProfiles List', $SchoolProfiles);
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
    public function show(SchoolProfile $schoolProfile, $schoolYear)
    {
        //find School Year by name
        $schoolYearFound = SchoolYear::firstWhere('name', $schoolYear)->select('id')->get();

        //find School Profile by ID
        $SchoolProfile = SchoolProfile::firstWhere('school_year_id', $schoolYearFound['id'])->get();

        //return single School Profile as a resource
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
    public function update(UpdateSchoolProfileRequest $request, SchoolProfile $schoolProfile)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SchoolProfile $schoolProfile)
    {
        //
    }
}
