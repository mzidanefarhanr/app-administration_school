<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelStudentMutationRequest;
use App\Http\Requests\RecordStudentMutationInRequest;
use App\Http\Requests\RecordStudentMutationOutRequest;
use App\Http\Resources\StatusResource;
use App\Services\Academic\StudentMutationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentMutationController extends Controller
{
    public function __construct(private readonly StudentMutationService $service) {}

    /**
     * Academic years, classes, and reference lists for mutation dialogs.
     */
    public function options(Request $request): StatusResource
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'school_year_id' => ['nullable', 'integer', 'min:1'],
        ], [
            'school_year_id.integer' => 'The academic year must be an integer identifier.',
        ]);

        $data = $this->service->options(
            isset($validated['school_year_id']) ? (int) $validated['school_year_id'] : null
        );

        return new StatusResource(true, 'Mutation options retrieved successfully.', $data);
    }

    /**
     * Read-only education school search by NPSN or name.
     */
    public function schools(Request $request): StatusResource
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ], [
            'search.max' => 'The school search term may not exceed 100 characters.',
        ]);

        $items = $this->service->schools($validated['search'] ?? null);

        return new StatusResource(true, 'Schools retrieved successfully.', [
            'items' => $items,
            'max_results' => StudentMutationService::MAX_SEARCH_RESULTS,
        ]);
    }

    /**
     * Mutation history for a school year.
     */
    public function index(Request $request): StatusResource
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'school_year_id' => ['required', 'integer', 'min:1'],
            'type' => ['nullable', 'in:in,out'],
            'status' => ['nullable', 'in:active,cancelled'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'school_year_id.required' => 'The academic year is required.',
            'type.in' => 'The mutation type must be in or out.',
            'status.in' => 'The mutation status must be active or cancelled.',
            'search.max' => 'The search term may not exceed 100 characters.',
            'page.min' => 'The page must be at least 1.',
        ]);

        $data = $this->service->history([
            'school_year_id' => (int) $validated['school_year_id'],
            'type' => $validated['type'] ?? null,
            'status' => $validated['status'] ?? null,
            'search' => $validated['search'] ?? null,
            'page' => isset($validated['page']) ? (int) $validated['page'] : 1,
        ]);

        return new StatusResource(true, 'Mutation history retrieved successfully.', $data);
    }

    /**
     * Year-level recap: mutation in/out, graduated, retained.
     */
    public function recap(Request $request): StatusResource
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'school_year_id' => ['required', 'integer', 'min:1'],
        ], [
            'school_year_id.required' => 'The academic year is required.',
        ]);

        $data = $this->service->recap((int) $validated['school_year_id']);

        return new StatusResource(true, 'Mutation recap retrieved successfully.', $data);
    }

    /**
     * Per-student mutation timeline.
     */
    public function timeline(Request $request, $id): StatusResource
    {
        $this->authorize('students.view');

        $data = $this->service->timeline((int) $id);

        return new StatusResource(true, 'Student mutations retrieved successfully.', $data);
    }

    /**
     * Record a mutation-in and create a new class membership.
     */
    public function mutationIn(RecordStudentMutationInRequest $request, $id): JsonResponse
    {
        $result = $this->service->mutationIn((int) $id, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Mutation-in recorded successfully.',
            'data' => $result,
        ], 200);
    }

    /**
     * Record a mutation-out and close one class membership.
     */
    public function mutationOut(RecordStudentMutationOutRequest $request, $id): JsonResponse
    {
        $result = $this->service->mutationOut((int) $id, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Mutation-out recorded successfully.',
            'data' => $result,
        ], 200);
    }

    /**
     * Compensating cancellation; no log or membership is deleted.
     */
    public function cancel(CancelStudentMutationRequest $request, $id, $mutation): JsonResponse
    {
        $result = $this->service->cancel(
            (int) $id,
            (int) $mutation,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Mutation cancelled successfully.',
            'data' => $result,
        ], 200);
    }
}
