<?php

namespace App\Http\Controllers;

use App\Http\Resources\StatusResource;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Display a listing of activities (DEC-03, DEC-17).
     * Protected by named ability 'audit.view'.
     */
    public function index(Request $request)
    {
        $this->authorize('audit.view');

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);

        $activities = Activity::with(['user:id,name,username'])
            ->latest()
            ->paginate($perPage);

        return new StatusResource(true, 'List Data Activities', $activities);
    }

    /**
     * Display the specified activity (DEC-03, DEC-17).
     * Protected by named ability 'audit.view'.
     */
    public function show($id)
    {
        $this->authorize('audit.view');

        $activity = Activity::with(['user:id,name,username'])->findOrFail($id);

        return new StatusResource(true, 'Detail Activity', $activity);
    }

    /**
     * Client creation is disabled (DEC-17).
     * Audit logs are generated strictly by server-side observers.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Audit logs are immutable and cannot be created by clients.',
        ], 405);
    }

    /**
     * Client modification and rollback are disabled (DEC-17).
     * Audit trail is append-only; data corrections must be done via normal mutations.
     */
    public function update(Request $request, $id): JsonResponse
    {
        return response()->json([
            'message' => 'Audit logs are immutable. Rollback has been disabled.',
        ], 405);
    }

    /**
     * Deletion is disabled (DEC-17).
     */
    public function destroy($id): JsonResponse
    {
        return response()->json([
            'message' => 'Audit logs are immutable and cannot be deleted.',
        ], 405);
    }
}
