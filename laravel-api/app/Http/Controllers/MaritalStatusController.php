<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaritalStatusRequest;
use App\Http\Requests\UpdateMaritalStatusRequest;
use App\Http\Resources\StatusResource;
use App\Models\MaritalStatus;

class MaritalStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all Marital Status
        $MaritalStatus = MaritalStatus::latest()->get();

        //return collection of Marital Status as a resource
        return new StatusResource(true, 'Marital Statuses List', $MaritalStatus);
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
    public function store(StoreMaritalStatusRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(MaritalStatus $maritalStatus)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MaritalStatus $maritalStatus)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMaritalStatusRequest $request, MaritalStatus $maritalStatus)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MaritalStatus $maritalStatus)
    {
        //
    }
}
