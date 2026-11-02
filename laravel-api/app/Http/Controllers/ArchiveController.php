<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArchiveDocumentRequest;
use App\Http\Requests\StoreArchiveVersionRequest;
use App\Http\Requests\UpdateArchiveDocumentRequest;
use App\Http\Resources\StatusResource;
use App\Models\ArchiveDocument;
use App\Services\Archives\ArchiveDocumentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchiveController extends Controller
{
    public function __construct(
        private readonly ArchiveDocumentService $archives,
    ) {}

    public function options(Request $request): StatusResource
    {
        $this->authorize('viewAny', ArchiveDocument::class);

        return new StatusResource(true, 'Archive options retrieved successfully.', $this->archives->options($request->user()));
    }

    public function subjects(Request $request, string $type): StatusResource
    {
        $this->authorize('viewAny', ArchiveDocument::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:'.(int) config('archives.search_max_length')],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'q.max' => 'The search term may not exceed '.(int) config('archives.search_max_length').' characters.',
            'limit.min' => 'The result limit must be at least 1.',
            'limit.max' => 'The result limit may not exceed 100.',
        ]);

        return new StatusResource(
            true,
            'Archive subjects retrieved successfully.',
            $this->archives->subjects($type, $filters, $request->user()),
        );
    }

    public function index(Request $request): StatusResource
    {
        $this->authorize('viewAny', ArchiveDocument::class);

        return new StatusResource(true, 'Archive documents retrieved successfully.', $this->archives->list($this->filters($request), $request->user()));
    }

    public function store(StoreArchiveDocumentRequest $request): StatusResource
    {
        $data = $this->archives->create($request->validated(), $request->file('file'), $request->user());

        return new StatusResource(true, 'Archive document created successfully.', $data);
    }

    public function show(int $archive): StatusResource
    {
        return new StatusResource(true, 'Archive document retrieved successfully.', $this->archives->show($archive, request()->user()));
    }

    public function update(UpdateArchiveDocumentRequest $request, int $archive): StatusResource
    {
        $validated = $request->validated();

        $data = $this->archives->update(
            $archive,
            $validated,
            (int) $validated['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Archive document updated successfully.', $data);
    }

    public function versions(Request $request, int $archive): StatusResource
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', Rule::in(config('archives.per_page_options'))],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'per_page.in' => 'The page size must be 10, 20, 50, or 100.',
            'page.min' => 'The page must be at least 1.',
        ]);

        return new StatusResource(true, 'Archive versions retrieved successfully.', $this->archives->versions($archive, $filters, $request->user()));
    }

    public function storeVersion(StoreArchiveVersionRequest $request, int $archive): StatusResource
    {
        $validated = $request->validated();

        $data = $this->archives->addVersion(
            $archive,
            $request->file('file'),
            $validated['note'] ?? null,
            (int) $validated['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Archive version created successfully.', $data);
    }

    public function downloadVersion(int $archive, int $version): StreamedResponse
    {
        return $this->archives->download($archive, $version, request()->user());
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:'.(int) config('archives.search_max_length')],
            'category' => ['nullable', 'string', Rule::in(array_keys((array) config('archives.categories')))],
            'subject_type' => [
                'nullable',
                'string',
                Rule::in(array_keys((array) config('archives.subject_types'))),
                'required_with:subject_id',
            ],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'validity' => ['nullable', 'string', Rule::in(array_keys((array) config('archives.validity_statuses')))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', Rule::in(config('archives.per_page_options'))],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'q.max' => 'The search term may not exceed '.(int) config('archives.search_max_length').' characters.',
            'category.in' => 'The selected archive category is not valid.',
            'subject_type.in' => 'The selected subject type is not valid.',
            'subject_type.required_with' => 'Select a subject type when filtering by subject.',
            'subject_id.min' => 'The selected subject is not valid.',
            'validity.in' => 'The selected validity status is not valid.',
            'date_from.date_format' => 'The start date must use the YYYY-MM-DD format.',
            'date_to.date_format' => 'The end date must use the YYYY-MM-DD format.',
            'date_to.after_or_equal' => 'The end date may not be before the start date.',
            'per_page.in' => 'The page size must be 10, 20, 50, or 100.',
            'page.min' => 'The page must be at least 1.',
        ]);
    }
}
