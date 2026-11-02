<?php

namespace App\Http\Controllers;

use App\Http\Resources\StatusResource;
use App\Services\Letters\LetterSubjectPickerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LetterSubjectController extends Controller
{
    public function __construct(private readonly LetterSubjectPickerService $subjects) {}

    public function students(Request $request): StatusResource
    {
        $this->authorize('letters.manage');
        $this->authorize('students.view');

        $validated = $this->validateFilters($request);

        return new StatusResource(
            true,
            'Letter student options retrieved successfully.',
            $this->subjects->students($validated),
        );
    }

    public function mutations(Request $request): StatusResource
    {
        $this->authorize('letters.manage');
        $this->authorize('students.view');

        $validated = $this->validateFilters($request);

        return new StatusResource(
            true,
            'Letter mutation options retrieved successfully.',
            $this->subjects->mutations($validated),
        );
    }

    public function employees(Request $request): StatusResource
    {
        $this->authorize('letters.manage');
        $this->authorize('employees.view');

        $validated = $this->validateFilters($request);

        return new StatusResource(
            true,
            'Letter employee options retrieved successfully.',
            $this->subjects->employees($validated),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:'.(int) config('letters.subject_picker.search_max_length')],
            'per_page' => ['nullable', 'integer', Rule::in(config('letters.subject_picker.per_page_options'))],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'search.max' => 'The search term may not exceed 100 characters.',
            'per_page.in' => 'The page size must be 10, 20, or 50.',
            'page.min' => 'The page must be at least 1.',
        ]);
    }
}
