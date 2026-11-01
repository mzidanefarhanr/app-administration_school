<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelLetterDispositionRequest;
use App\Http\Requests\StoreLetterDispositionRequest;
use App\Http\Requests\UpdateLetterDispositionRequest;
use App\Http\Resources\StatusResource;
use App\Services\Letters\LetterDispositionService;
use App\Services\Letters\LetterService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LetterDispositionController extends Controller
{
    public function __construct(
        private readonly LetterDispositionService $dispositions,
        private readonly LetterService $letters,
    ) {}

    public function options(): StatusResource
    {
        $this->authorize('letters.view');

        return new StatusResource(true, 'Disposition options retrieved successfully.', $this->dispositions->options());
    }

    public function recipients(Request $request): StatusResource
    {
        $this->authorize('letters.manage');
        $this->authorize('employees.view');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in(config('letters.per_page_options'))],
        ], [
            'search.max' => 'The search term may not exceed 100 characters.',
            'per_page.in' => 'The page size must be 10, 20, 50, or 100.',
        ]);

        $data = $this->dispositions->recipients(
            trim((string) ($validated['search'] ?? '')),
            (int) ($validated['per_page'] ?? config('letters.default_per_page')),
        );

        return new StatusResource(true, 'Disposition recipients retrieved successfully.', $data);
    }

    public function index(Request $request): StatusResource
    {
        $this->authorize('letters.view');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                'string',
                Rule::in(['open', 'all', ...config('letters.disposition_statuses')]),
            ],
            'overdue' => ['nullable', 'string', Rule::in(['true', 'false', '1', '0'])],
            'recipient_employee_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in(config('letters.disposition_per_page_options'))],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'search.max' => 'The search term may not exceed 100 characters.',
            'status.in' => 'The selected disposition status is not valid.',
            'overdue.in' => 'The overdue filter must be true or false.',
            'recipient_employee_id.integer' => 'The recipient filter must be an employee ID.',
            'recipient_employee_id.min' => 'The recipient filter must be an employee ID.',
            'per_page.in' => 'The page size must be 10, 20, 50, or 100.',
            'page.min' => 'The page must be at least 1.',
        ]);

        return new StatusResource(true, 'Dispositions retrieved successfully.', $this->dispositions->list($validated));
    }

    public function store(StoreLetterDispositionRequest $request, int $letter): StatusResource
    {
        $this->authorize('letters.manage');

        $this->dispositions->create($letter, $request->validated(), $request->user());

        return new StatusResource(
            true,
            'Dispositions created successfully.',
            $this->letters->show($letter),
        );
    }

    public function update(UpdateLetterDispositionRequest $request, int $letter, int $disposition): StatusResource
    {
        $this->authorize('letters.manage');

        $validated = $request->validated();

        $this->dispositions->update(
            $letter,
            $disposition,
            $validated,
            (int) $validated['expected_version'],
            $request->user(),
        );

        return new StatusResource(
            true,
            'Disposition updated successfully.',
            $this->letters->show($letter),
        );
    }

    public function cancel(CancelLetterDispositionRequest $request, int $letter, int $disposition): StatusResource
    {
        $this->authorize('letters.manage');

        $validated = $request->validated();

        $this->dispositions->cancel(
            $letter,
            $disposition,
            $validated['reason'],
            (int) $validated['expected_version'],
            $request->user(),
        );

        return new StatusResource(
            true,
            'Disposition cancelled successfully.',
            $this->letters->show($letter),
        );
    }
}
