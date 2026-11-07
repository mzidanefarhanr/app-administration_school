<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncomingLetterRequest;
use App\Http\Requests\StoreLetterAttachmentRequest;
use App\Http\Requests\UpdateIncomingLetterRequest;
use App\Http\Requests\VoidIncomingLetterRequest;
use App\Http\Resources\StatusResource;
use App\Services\Letters\LetterAttachmentService;
use App\Services\Letters\LetterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomingLetterController extends Controller
{
    public function __construct(
        private readonly LetterService $letters,
        private readonly LetterAttachmentService $attachments,
    ) {}

    public function options(): StatusResource
    {
        $this->authorize('letters.view');

        return new StatusResource(true, 'Letter options retrieved successfully.', $this->letters->options());
    }

    public function index(Request $request): StatusResource
    {
        $this->authorize('letters.view');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:'.(int) config('letters.search_max_length')],
            'category' => ['nullable', 'string', Rule::in(config('letters.categories'))],
            'status' => ['nullable', 'in:active,void,all'],
            'received_from' => ['nullable', 'date_format:Y-m-d'],
            'received_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:received_from'],
            'per_page' => ['nullable', 'integer', Rule::in(config('letters.per_page_options'))],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'search.max' => 'The search term may not exceed 100 characters.',
            'category.in' => 'The selected category is not valid.',
            'status.in' => 'The status must be active, void, or all.',
            'received_from.date_format' => 'The start date must use the YYYY-MM-DD format.',
            'received_to.date_format' => 'The end date must use the YYYY-MM-DD format.',
            'received_to.after_or_equal' => 'The end date may not be before the start date.',
            'per_page.in' => 'The page size must be 10, 20, 50, or 100.',
            'page.min' => 'The page must be at least 1.',
        ]);

        return new StatusResource(true, 'Letters retrieved successfully.', $this->letters->list($validated));
    }

    public function show(int $letter): StatusResource
    {
        $this->authorize('letters.view');

        return new StatusResource(true, 'Letter retrieved successfully.', $this->letters->show($letter));
    }

    public function store(StoreIncomingLetterRequest $request): StatusResource
    {
        $this->authorize('letters.manage');

        $data = $this->letters->create($request->validated(), $request->user());

        return new StatusResource(true, 'Letter created successfully.', $data);
    }

    public function update(UpdateIncomingLetterRequest $request, int $letter): StatusResource
    {
        $this->authorize('letters.manage');

        $validated = $request->validated();

        $data = $this->letters->update(
            $letter,
            $validated,
            (int) $validated['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Letter updated successfully.', $data);
    }

    public function void(VoidIncomingLetterRequest $request, int $letter): StatusResource
    {
        $this->authorize('letters.manage');

        $validated = $request->validated();

        $data = $this->letters->void(
            $letter,
            $validated['reason'],
            (int) $validated['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Letter voided successfully.', $data);
    }

    public function batchVoid(Request $request): StatusResource
    {
        $this->authorize('letters.manage');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.expected_version' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
        ], [
            'items.required' => 'Select at least one letter.',
            'items.min' => 'Select at least one letter.',
            'reason.required' => 'The void reason is required.',
            'reason.min' => 'The void reason must be at least 10 characters.',
            'reason.max' => 'The void reason may not exceed 255 characters.',
        ]);

        $actor = $request->user();
        $reason = $validated['reason'];
        $items = $validated['items'];

        $count = DB::transaction(function () use ($items, $reason, $actor) {
            $processed = 0;
            foreach ($items as $item) {
                $this->letters->void(
                    (int) $item['id'],
                    $reason,
                    (int) $item['expected_version'],
                    $actor
                );
                $processed++;
            }

            return $processed;
        });

        return new StatusResource(true, "Successfully voided {$count} letter(s).", ['voided_count' => $count]);
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
