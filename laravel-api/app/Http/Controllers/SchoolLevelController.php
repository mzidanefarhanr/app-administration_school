<?php

namespace App\Http\Controllers;

use App\Models\SchoolLevel;
use App\Http\Requests\StoreSchoolLevelRequest;
use App\Http\Requests\UpdateSchoolLevelRequest;
use App\Http\Resources\StatusResource;

class SchoolLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all SchoolLevel
        $SchoolLevel = SchoolLevel::latest()->get();

        //return collection of SchoolLevel as a resource
        return new StatusResource(true, 'List Data SchoolLevel', $SchoolLevel);
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
    public function store(StoreSchoolLevelRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(SchoolLevel $schoolLevel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SchoolLevel $schoolLevel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSchoolLevelRequest $request, SchoolLevel $schoolLevel)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SchoolLevel $schoolLevel)
    {
        //
    }
}
