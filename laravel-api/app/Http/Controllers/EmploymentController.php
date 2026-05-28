<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmploymentRequest;
use App\Http\Requests\UpdateEmploymentRequest;
use App\Http\Resources\StatusResource;
use App\Models\Employment;

class EmploymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all Employments
        $Employments = Employment::latest()->get();

        //return collection of Employments as a resource
        return new StatusResource(true, 'Employments List', $Employments);
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
    public function store(StoreEmploymentRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Employment $employment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employment $employment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmploymentRequest $request, Employment $employment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employment $employment)
    {
        //
    }
}
