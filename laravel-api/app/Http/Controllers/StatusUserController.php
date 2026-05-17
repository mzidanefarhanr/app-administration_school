<?php

namespace App\Http\Controllers;

use App\Models\StatusUser;
use App\Http\Requests\StoreStatusUserRequest;
use App\Http\Requests\UpdateStatusUserRequest;
use App\Http\Resources\StatusResource;

class StatusUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all StatusUser
        $StatusUser = StatusUser::latest()->get();

        //return collection of StatusUser as a resource
        return new StatusResource(true, 'List Data StatusUser', $StatusUser);
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
    public function store(StoreStatusUserRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(StatusUser $statusUser)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StatusUser $statusUser)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStatusUserRequest $request, StatusUser $statusUser)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StatusUser $statusUser)
    {
        //
    }
}
