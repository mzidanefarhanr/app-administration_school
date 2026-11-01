<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveOutgoingLetterRequest;
use App\Http\Requests\CancelOutgoingLetterRequest;
use App\Http\Requests\IssueOutgoingLetterRequest;
use App\Http\Requests\RejectOutgoingLetterRequest;
use App\Http\Requests\StoreLetterAttachmentRequest;
use App\Http\Requests\StoreOutgoingLetterRequest;
use App\Http\Requests\SubmitOutgoingLetterRequest;
use App\Http\Requests\UpdateOutgoingLetterRequest;
use App\Http\Resources\StatusResource;
use App\Services\Letters\LetterAttachmentService;
use App\Services\Letters\OutgoingLetterService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OutgoingLetterController extends Controller
{
    public function __construct(
        private readonly OutgoingLetterService $letters,
        private readonly LetterAttachmentService $attachments,
    ) {}

    public function options(): StatusResource
    {
        $this->authorize('letters.view');

        return new StatusResource(true, 'Outgoing letter options retrieved successfully.', $this->letters->options());
    }

    public function index(Request $request): StatusResource
    {
        $this->authorize('letters.view');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:'.(int) config('letters.outgoing_search_max_length')],
            'workflow_status' => [
                'nullable',
                'string',
                Rule::in([...config('letters.outgoing_workflow_statuses'), 'all']),
            ],
            'letter_from' => ['nullable', 'date_format:Y-m-d'],
            'letter_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:letter_from'],
            'per_page' => ['nullable', 'integer', Rule::in(config('letters.per_page_options'))],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'search.max' => 'The search term may not exceed 100 characters.',
            'workflow_status.in' => 'The selected workflow status is not valid.',
            'letter_from.date_format' => 'The start date must use the YYYY-MM-DD format.',
            'letter_to.date_format' => 'The end date must use the YYYY-MM-DD format.',
            'letter_to.after_or_equal' => 'The end date may not be before the start date.',
            'per_page.in' => 'The page size must be 10, 20, 50, or 100.',
            'page.min' => 'The page must be at least 1.',
        ]);

        return new StatusResource(true, 'Outgoing letters retrieved successfully.', $this->letters->list($validated));
    }

    public function show(int $letter): StatusResource
    {
        $this->authorize('letters.view');

        return new StatusResource(true, 'Outgoing letter retrieved successfully.', $this->letters->show($letter));
    }

    public function store(StoreOutgoingLetterRequest $request): StatusResource
    {
        $this->authorize('letters.manage');

        $data = $this->letters->create($request->validated(), $request->user());

        return new StatusResource(true, 'Outgoing letter created successfully.', $data);
    }

    public function update(UpdateOutgoingLetterRequest $request, int $letter): StatusResource
    {
        $this->authorize('letters.manage');

        $validated = $request->validated();

        $data = $this->letters->update(
            $letter,
            $validated,
            (int) $validated['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Outgoing letter updated successfully.', $data);
    }

    public function submit(SubmitOutgoingLetterRequest $request, int $letter): StatusResource
    {
        $this->authorize('letters.manage');

        $data = $this->letters->submit(
            $letter,
            (int) $request->validated()['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Outgoing letter submitted for review.', $data);
    }

    public function approve(ApproveOutgoingLetterRequest $request, int $letter): StatusResource
    {
        $this->authorize('letters.approve');

        $validated = $request->validated();

        $data = $this->letters->approve(
            $letter,
            $validated['notes'] ?? null,
            (int) $validated['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Outgoing letter approved successfully.', $data);
    }

    public function reject(RejectOutgoingLetterRequest $request, int $letter): StatusResource
    {
        $this->authorize('letters.approve');

        $validated = $request->validated();

        $data = $this->letters->reject(
            $letter,
            $validated['reason'],
            (int) $validated['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Outgoing letter rejected and returned to draft.', $data);
    }

    public function issue(IssueOutgoingLetterRequest $request, int $letter): StatusResource
    {
        $this->authorize('letters.sign');

        $data = $this->letters->issue(
            $letter,
            (int) $request->validated()['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Outgoing letter issued successfully.', $data);
    }

    public function cancel(CancelOutgoingLetterRequest $request, int $letter): StatusResource
    {
        $this->authorize('letters.view');

        $validated = $request->validated();

        $data = $this->letters->cancel(
            $letter,
            $validated['reason'],
            (int) $validated['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Outgoing letter cancelled successfully.', $data);
    }

    public function storeAttachment(StoreLetterAttachmentRequest $request, int $letter): StatusResource
    {
        $this->authorize('letters.manage');

        $data = $this->letters->addAttachment($letter, $request->file('file'), $request->user());

        return new StatusResource(true, 'Attachment uploaded successfully.', $data);
    }

    public function downloadAttachment(int $letter, int $attachment): StreamedResponse
    {
        $this->authorize('letters.view');

        $model = $this->letters->attachmentForDownload($letter, $attachment);

        return $this->attachments->downloadResponse($model);
    }

    public function destroyAttachment(Request $request, int $letter, int $attachment): StatusResource
    {
        $this->authorize('letters.manage');

        $data = $this->letters->removeAttachment($letter, $attachment, $request->user());

        return new StatusResource(true, 'Attachment removed successfully.', $data);
    }
}
