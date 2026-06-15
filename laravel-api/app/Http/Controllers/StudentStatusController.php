<?php

namespace App\Http\Controllers;

use App\Models\StudentStatus;
use App\Http\Requests\StoreStudentStatusRequest;
use App\Http\Requests\UpdateStudentStatusRequest;
use App\Http\Resources\StatusResource;

class StudentStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all StudentStatus
        $StudentStatus = StudentStatus::select('name', 'id')->latest()->get();

        //return collection of StudentStatus as a resource
        return new StatusResource(true, 'List Data StudentStatus', $StudentStatus);
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
    public function store(StoreStudentStatusRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(StudentStatus $studentStatus)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StudentStatus $studentStatus)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentStatusRequest $request, StudentStatus $studentStatus)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StudentStatus $studentStatus)
    {
        //
    }
}
