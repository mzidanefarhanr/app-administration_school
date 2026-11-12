<?php

namespace App\Http\Controllers;

use App\Http\Resources\StatusResource;
use App\Models\Employment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'min:3', 'max:100',
                Rule::unique('employments')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $description = trim((string) $request->input('description', ''));
        $description = $description === '' ? 'None' : $description;

        // Pulihkan jabatan yang pernah dihapus bila namanya sama.
        $trashed = Employment::withTrashed()
            ->where('name', $request->name)
            ->whereNotNull('deleted_at')
            ->first();

        if ($trashed) {
            $trashed->restore();
            $trashed->update([
                'name' => $request->name,
                'description' => $description,
            ]);

            return new StatusResource(true, 'Employment successfully restored!', $trashed);
        }

        $employment = Employment::create([
            'name' => $request->name,
            'description' => $description,
        ]);

        return new StatusResource(true, 'New Employment successfully added!', $employment);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $employment = Employment::find($id);

        if (! $employment) {
            return response()->json(['message' => 'Employment not found!'], 404);
        }

        return new StatusResource(true, 'Employment detail found!', $employment);
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
    public function update(Request $request, $id)
    {
        $employment = Employment::find($id);

        if (! $employment) {
            return response()->json(['message' => 'Employment not found!'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'min:3', 'max:100',
                Rule::unique('employments')->ignore($id)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $description = trim((string) $request->input('description', ''));

        $employment->update([
            'name' => $request->name,
            'description' => $description === '' ? 'None' : $description,
        ]);

        return new StatusResource(true, 'Employment successfully updated!', $employment);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, Request $request)
    {
        $employment = Employment::withCount('employees')->find($id);

        if (! $employment) {
            return response()->json(['message' => 'Employment not found!'], 404);
        }

        if ($employment->employees_count > 0) {
            return response()->json([
                'message' => "This record is still being referenced by {$employment->employees_count} Employees.",
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'delete' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->delete == 'true') {
            $employment->delete();

            return new StatusResource(true, 'Employment successfully deleted!', null);
        }

        return response()->json(['message' => 'You are not allowed to delete this data!'], 400);
    }
}
