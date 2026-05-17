<?php

namespace App\Http\Controllers;

use App\Models\SchoolRombel;
use App\Http\Requests\StoreSchoolRombelRequest;
use App\Http\Requests\UpdateSchoolRombelRequest;
use App\Http\Resources\StatusResource;

class SchoolRombelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all School Rombels
        $SchoolRombels = SchoolRombel::with(['schoolLevel','schoolYear', 'studentMajor', 'classTeach'])->get();

        //return collection of School Rombels as a resource
        return new StatusResource(true, 'SchoolRombels List', $SchoolRombels);
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
    public function store(StoreSchoolRombelRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(SchoolRombel $schoolRombel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SchoolRombel $schoolRombel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSchoolRombelRequest $request, SchoolRombel $schoolRombel)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SchoolRombel $schoolRombel)
    {
        //
    }
}
