<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeStatusRequest;
use App\Http\Requests\UpdateEmployeeStatusRequest;
use App\Http\Resources\StatusResource;
use App\Models\EmployeeStatus;

class EmployeeStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all Employee Statuses
        $EmployeeStatuses = EmployeeStatus::latest()->get();

        //return collection of Employee Statuses as a resource
        return new StatusResource(true, 'Employee Statuses List', $EmployeeStatuses);
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
    public function store(StoreEmployeeStatusRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(EmployeeStatus $employeeStatus)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EmployeeStatus $employeeStatus)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeStatusRequest $request, EmployeeStatus $employeeStatus)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeeStatus $employeeStatus)
    {
        //
    }
}
