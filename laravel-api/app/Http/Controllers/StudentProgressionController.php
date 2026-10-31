<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewStudentProgressionRequest;
use App\Http\Resources\StatusResource;
use App\Services\Academic\StudentProgressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentProgressionController extends Controller
{
    public function __construct(private readonly StudentProgressionService $service) {}

    /**
     * Academic years and classes available for a progression batch (DEC-30).
     */
    public function options(Request $request): StatusResource
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'source_school_year_id' => ['required', 'integer', 'min:1'],
            'target_school_year_id' => ['required', 'integer', 'min:1'],
        ], [
            'source_school_year_id.required' => 'Source academic year is required.',
            'target_school_year_id.required' => 'Target academic year is required.',
        ]);

        $data = $this->service->options(
            (int) $validated['source_school_year_id'],
            (int) $validated['target_school_year_id']
        );

        return new StatusResource(true, 'Progression options retrieved successfully.', $data);
    }

    /**
     * Students with exactly one active membership in the source academic year.
     */
    public function roster(Request $request): StatusResource
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'source_school_year_id' => ['required', 'integer', 'min:1'],
            'target_school_year_id' => ['nullable', 'integer', 'min:1'],
            'rombel_ids' => ['nullable', 'array', 'max:100'],
            'rombel_ids.*' => ['integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
        ], [
            'source_school_year_id.required' => 'Source academic year is required.',
            'rombel_ids.array' => 'The class filter must be a list of class identifiers.',
        ]);

        $data = $this->service->roster(
            (int) $validated['source_school_year_id'],
            isset($validated['target_school_year_id']) ? (int) $validated['target_school_year_id'] : null,
            collect($validated['rombel_ids'] ?? [])->map(fn ($id) => (int) $id)->all(),
            $validated['search'] ?? null
        );

        return new StatusResource(true, 'Progression roster retrieved successfully.', $data);
    }

    /**
     * Build an immutable preview batch without mutating students or memberships.
     */
    public function preview(PreviewStudentProgressionRequest $request): JsonResponse
    {
        $result = $this->service->preview($request->validated(), $request->user());

        $valid = (bool) $result['valid'];

        return response()->json([
            'success' => $valid,
            'message' => $valid
                ? 'Progression preview created successfully.'
                : 'Progression preview contains conflicts. Resolve them before committing.',
            'data' => $result,
        ], $valid ? 200 : 422);
    }

    /**
     * Apply a preview batch atomically; replaying a committed token is idempotent.
     */
    public function commit(Request $request): JsonResponse
    {
        $this->authorize('students.manage');

        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ], [
            'token.required' => 'A progression token is required.',
            'token.size' => 'The progression token is invalid.',
            'token.regex' => 'The progression token is invalid.',
        ]);

        $result = $this->service->commit((string) $validated['token'], $request->user());

        return response()->json([
            'success' => true,
            'message' => $result['replayed']
                ? 'Progression batch was already committed; the saved result is returned.'
                : 'Progression applied successfully.',
            'data' => $result,
        ], 200);
    }

    /**
     * Progression batch history.
     */
    public function batches(Request $request): StatusResource
    {
        $this->authorize('students.view');

        $validated = $request->validate([
            'source_school_year_id' => ['nullable', 'integer', 'min:1'],
            'target_school_year_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $data = $this->service->batches(
            $request->user(),
            isset($validated['source_school_year_id']) ? (int) $validated['source_school_year_id'] : null,
            isset($validated['target_school_year_id']) ? (int) $validated['target_school_year_id'] : null
        );

        return new StatusResource(true, 'Progression batches retrieved successfully.', $data);
    }

    /**
     * Batch detail; a preview batch is only visible to its creator.
     */
    public function showBatch(Request $request, $id): StatusResource
    {
        $this->authorize('students.view');

        $data = $this->service->batchDetail((int) $id, $request->user());

        return new StatusResource(true, 'Progression batch retrieved successfully.', $data);
    }
}
