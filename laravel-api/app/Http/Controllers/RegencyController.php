<?php

namespace App\Http\Controllers;

use App\Models\Regency;
use App\Http\Requests\StoreRegencyRequest;
use App\Http\Requests\UpdateRegencyRequest;
use App\Http\Resources\StatusResource;

class RegencyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all Regency
        $Regency = Regency::with(['province'])->latest()->get();

        //return collection of Regency as a resource
        return new StatusResource(true, 'List Data Regency', $Regency);
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
    public function store(StoreRegencyRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Regency $regency)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Regency $regency)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRegencyRequest $request, Regency $regency)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Regency $regency)
    {
        //
    }
}
