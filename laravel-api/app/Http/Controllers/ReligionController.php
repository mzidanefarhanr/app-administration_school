<?php

namespace App\Http\Controllers;

use App\Models\Religion;
use App\Http\Requests\StoreReligionRequest;
use App\Http\Requests\UpdateReligionRequest;
use App\Http\Resources\StatusResource;

class ReligionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all Religion
        $Religion = Religion::select('name', 'id')->latest()->get();

        //return collection of Religion as a resource
        return new StatusResource(true, 'List Data Religion', $Religion);
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
    public function store(StoreReligionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Religion $religion)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Religion $religion)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReligionRequest $request, Religion $religion)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Religion $religion)
    {
        //
    }
}
