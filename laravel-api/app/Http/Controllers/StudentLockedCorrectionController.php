<?php

namespace App\Http\Controllers;

use App\Http\Requests\CorrectStudentLockedMutationRequest;
use App\Http\Resources\StatusResource;
use App\Services\Academic\StudentLockedCorrectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentLockedCorrectionController extends Controller
{
    public function __construct(private readonly StudentLockedCorrectionService $service) {}

    /**
     * Read-only locked correction history for a student.
     */
    public function index(Request $request, $id): StatusResource
    {
        $this->authorize('students.view');

        $data = $this->service->index((int) $id);

        return new StatusResource(true, 'Locked corrections retrieved successfully.', $data);
    }

    /**
     * Apply one immutable locked-period correction.
     */
    public function store(CorrectStudentLockedMutationRequest $request, $id): JsonResponse
    {
        $this->authorize('students.manage');
        $this->authorize('students.correct-locked');

        $result = $this->service->store((int) $id, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Locked correction recorded successfully.',
            'data' => $result,
        ], 200);
    }
}
