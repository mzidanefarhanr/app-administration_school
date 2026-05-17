<?php

namespace App\Http\Controllers;

use App\Models\BloodType;
use App\Http\Requests\StoreBloodTypeRequest;
use App\Http\Requests\UpdateBloodTypeRequest;
use App\Http\Resources\StatusResource;

class BloodTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all BloodType
        $BloodType = BloodType::latest()->get();

        //return collection of BloodType as a resource
        return new StatusResource(true, 'List Data BloodType', $BloodType);
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
    public function store(StoreBloodTypeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(BloodType $bloodType)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BloodType $bloodType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBloodTypeRequest $request, BloodType $bloodType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BloodType $bloodType)
    {
        //
    }
}
