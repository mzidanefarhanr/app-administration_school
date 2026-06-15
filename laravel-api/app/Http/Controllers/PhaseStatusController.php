<?php

namespace App\Http\Controllers;

use App\Models\PhaseStatus;
// use App\Http\Requests\StorePhaseStatusRequest;
// use App\Http\Requests\UpdatePhaseStatusRequest;
use App\Http\Resources\StatusResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PhaseStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //get all Phase Status
        $PhaseStatus = PhaseStatus::select('name', 'id')->latest()->get();

        //return collection of Phase Status as a resource
        return new StatusResource(true, 'Phase Statuses List', $PhaseStatus);
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
    public function store(Request $request)
    {
        //define validation rules
        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'min:3', 'max:100',
                Rule::unique('phase_statuses')->whereNull('deleted_at'),
            ],
        ]);

        //check if validation fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // ── STEP 1: Check name against soft-deleted records ────────────────────────
        $existingFirst = PhaseStatus::withTrashed()
            ->where('name', $request->name)
            ->whereNotNull('deleted_at')
            ->first();

        if ($existingFirst) {
            // No conflict — safe to restore and update
            $existingFirst->restore();
            $existingFirst->update([
                'name'           => $request->name,
            ]);

            return new StatusResource(true, 'Phase Status successfully restored!', $existingFirst);
        }

        // ── STEP 2: All clear — create fresh user ────────────────────────────────
        $phaseStatuses = PhaseStatus::create([
            'name'              => $request->name,
        ]);

        return new StatusResource(true, 'New Phase Status successfully added!', $phaseStatuses);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //find Phase Status by ID
        $phaseStatus = PhaseStatus::find($id);

        //return single Phase Status as a resource
        return new StatusResource(true, 'Phase Status detail found!', $phaseStatus);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PhaseStatus $phaseStatus)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //find Phase Status by ID
        $phaseStatus = PhaseStatus::firstWhere('id', $id);


        if (isset($phaseStatus)) {

            //define validation rules
            $validator = Validator::make($request->all(), [
                'name'              => ['required', 'min:3', 'max:100',
                    Rule::unique('phase_statuses')->ignore($id)],
            ]);

            //check if validation fails
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            //update Phase Status
            $phaseStatus->update([
                'name'              => $request->name,
            ]);

            //return response
            return new StatusResource(true, 'Phase Status successfully updated!', $phaseStatus);

        } else {
            return response()->json(['message' => 'Phase Status not found!'], 404);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, Request $request)
    {
        //find Phase Status by ID
        $phaseStatus = PhaseStatus::findOrFail($id);

        if (isset($phaseStatus)) {
            //define validation rules
            $validator = Validator::make($request->all(), [
                'delete'             => 'required',
            ]);

            //check if validation fails
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            //check if delete is true
            if ($request->delete == 'true') {

                //delete Phase Status
                $phaseStatus->delete();

                //return response
                return new StatusResource(true, 'Phase Status successfully deleted!', null);
            } else {
                return response()->json(['message' => 'You are not allowed to delete this data!'], 400);
            }

        } else {
            return response()->json(['message' => 'Phase Status not found!'], 404);
        }
    }
}
