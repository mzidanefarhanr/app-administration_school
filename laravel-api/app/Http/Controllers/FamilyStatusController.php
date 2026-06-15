<?php

namespace App\Http\Controllers;

use App\Models\FamilyStatus;
use App\Http\Requests\StoreFamilyStatusRequest;
use App\Http\Requests\UpdateFamilyStatusRequest;
use App\Http\Resources\StatusResource;

class FamilyStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all FamilyStatus
        $FamilyStatus = FamilyStatus::select('name', 'id')->latest()->get();

        //return collection of FamilyStatus as a resource
        return new StatusResource(true, 'List Data FamilyStatus', $FamilyStatus);
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
    public function store(StoreFamilyStatusRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(FamilyStatus $familyStatus)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FamilyStatus $familyStatus)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFamilyStatusRequest $request, FamilyStatus $familyStatus)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FamilyStatus $familyStatus)
    {
        //
    }
}
