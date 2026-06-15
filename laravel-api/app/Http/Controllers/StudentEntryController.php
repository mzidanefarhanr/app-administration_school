<?php

namespace App\Http\Controllers;

use App\Models\StudentEntry;
use App\Http\Requests\StoreStudentEntryRequest;
use App\Http\Requests\UpdateStudentEntryRequest;
use App\Http\Resources\StatusResource;

class StudentEntryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all StudentEntry
        $StudentEntry = StudentEntry::select('name', 'id')->latest()->get();

        //return collection of StudentEntry as a resource
        return new StatusResource(true, 'List Data StudentEntry', $StudentEntry);
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
    public function store(StoreStudentEntryRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(StudentEntry $studentEntry)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StudentEntry $studentEntry)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentEntryRequest $request, StudentEntry $studentEntry)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StudentEntry $studentEntry)
    {
        //
    }
}
