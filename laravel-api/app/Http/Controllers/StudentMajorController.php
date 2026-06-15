<?php

namespace App\Http\Controllers;

use App\Models\StudentMajor;
use App\Http\Requests\StoreStudentMajorRequest;
use App\Http\Requests\UpdateStudentMajorRequest;
use App\Http\Resources\StatusResource;

class StudentMajorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all StudentMajor
        $StudentMajor = StudentMajor::select('name', 'id')->latest()->get();

        //return collection of StudentMajor as a resource
        return new StatusResource(true, 'List Data StudentMajor', $StudentMajor);
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
    public function store(StoreStudentMajorRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(StudentMajor $studentMajor)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StudentMajor $studentMajor)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentMajorRequest $request, StudentMajor $studentMajor)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StudentMajor $studentMajor)
    {
        //
    }
}
