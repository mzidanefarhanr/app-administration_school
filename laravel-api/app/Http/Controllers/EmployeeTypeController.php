<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeTypeRequest;
use App\Http\Requests\UpdateEmployeeTypeRequest;
use App\Http\Resources\StatusResource;
use App\Models\EmployeeType;

class EmployeeTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all Employee Types
        $EmployeeTypes = EmployeeType::latest()->get();

        //return collection of Employee Types as a resource
        return new StatusResource(true, 'Employee Types List', $EmployeeTypes);
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
    public function store(StoreEmployeeTypeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(EmployeeType $employeeType)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EmployeeType $employeeType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeTypeRequest $request, EmployeeType $employeeType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeeType $employeeType)
    {
        //
    }
}
