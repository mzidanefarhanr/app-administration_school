<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelLeaveRequestRequest;
use App\Http\Requests\StoreLeaveAttachmentRequest;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Requests\SubmitLeaveRequestRequest;
use App\Http\Requests\UpdateLeaveRequestRequest;
use App\Http\Resources\StatusResource;
use App\Models\LeaveRequest;
use App\Services\Leaves\LeaveRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveController extends Controller
{
    public function __construct(
        private readonly LeaveRequestService $leaves,
    ) {}

    public function options(): StatusResource
    {
        $this->authorize('viewAny', LeaveRequest::class);

        return new StatusResource(true, 'Leave options retrieved successfully.', $this->leaves->options(request()->user()));
    }

    public function index(Request $request): StatusResource
    {
        $this->authorize('leaves.view');

        return new StatusResource(true, 'Leave requests retrieved successfully.', $this->leaves->list($this->filters($request), $request->user()));
    }

    public function mine(Request $request): StatusResource
    {
        $this->authorize('leaves.request');

        return new StatusResource(true, 'Your leave requests retrieved successfully.', $this->leaves->mine($this->filters($request), $request->user()));
    }

    public function show(int $leave): StatusResource
    {
        return new StatusResource(true, 'Leave request retrieved successfully.', $this->leaves->show($leave, request()->user()));
    }

    public function store(StoreLeaveRequestRequest $request): StatusResource
    {
        $this->authorize('create', LeaveRequest::class);

        $data = $this->leaves->create($request->validated(), $request->user());

        return new StatusResource(true, 'Leave request created as draft.', $data);
    }

    public function update(UpdateLeaveRequestRequest $request, int $leave): StatusResource
    {
        $validated = $request->validated();

        $data = $this->leaves->update(
            $leave,
            $validated,
            (int) $validated['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Leave request updated successfully.', $data);
    }

    public function submit(SubmitLeaveRequestRequest $request, int $leave): StatusResource
    {
        $data = $this->leaves->submit(
            $leave,
            (int) $request->validated()['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Leave request submitted successfully.', $data);
    }

    public function cancel(CancelLeaveRequestRequest $request, int $leave): StatusResource
    {
        $validated = $request->validated();

        $data = $this->leaves->cancel(
            $leave,
            $validated['reason'] ?? null,
            (int) $validated['expected_version'],
            $request->user(),
        );

        return new StatusResource(true, 'Leave request cancelled successfully.', $data);
    }

    public function storeAttachment(StoreLeaveAttachmentRequest $request, int $leave): StatusResource
    {
        $data = $this->leaves->addAttachment($leave, $request->file('file'), $request->user());

        return new StatusResource(true, 'Attachment uploaded successfully.', $data);
    }

    public function downloadAttachment(int $leave, int $attachment): StreamedResponse
    {
        $model = $this->leaves->attachmentForDownload($leave, $attachment, request()->user());

        return $this->leaves->downloadResponse($model);
    }

    public function destroyAttachment(int $leave, int $attachment): StatusResource
    {
        $data = $this->leaves->removeAttachment($leave, $attachment, request()->user());

        return new StatusResource(true, 'Attachment removed successfully.', $data);
    }

    public function studentSubjects(Request $request): StatusResource
    {
        $this->authorize('leaves.manage');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:'.(int) config('leaves.search_max_length')],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return new StatusResource(true, 'Students retrieved successfully.', $this->leaves->studentSubjects($validated));
    }

    public function employeeSubjects(Request $request): StatusResource
    {
        $this->authorize('leaves.manage');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:'.(int) config('leaves.search_max_length')],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return new StatusResource(true, 'Employees retrieved successfully.', $this->leaves->employeeSubjects($validated));
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:'.(int) config('leaves.search_max_length')],
            'status' => ['nullable', 'string', Rule::in([...array_keys((array) config('leaves.statuses')), 'all'])],
            'type' => ['nullable', 'string', 'max:30'],
            'subject_type' => ['nullable', Rule::in(array_keys((array) config('leaves.subject_types')))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'school_year_id' => ['nullable', 'integer', 'min:1'],
            'rombel_id' => ['nullable', 'integer', 'min:1'],
            'employee_type_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in(['start_date', 'created_at', 'status', 'type', 'version'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in(config('leaves.per_page_options'))],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'search.max' => 'The search term may not exceed '.(int) config('leaves.search_max_length').' characters.',
            'status.in' => 'The selected status is not valid.',
            'type.max' => 'The selected leave type is not valid.',
            'subject_type.in' => 'The selected subject type is not valid.',
            'date_from.date_format' => 'The start date must use the YYYY-MM-DD format.',
            'date_to.date_format' => 'The end date must use the YYYY-MM-DD format.',
            'date_to.after_or_equal' => 'The end date may not be before the start date.',
            'school_year_id.min' => 'The selected school year is not valid.',
            'rombel_id.min' => 'The selected class is not valid.',
            'employee_type_id.min' => 'The selected employee type is not valid.',
            'sort.in' => 'The selected sort field is not valid.',
            'direction.in' => 'The sort direction must be asc or desc.',
            'per_page.in' => 'The page size must be 10, 20, 50, or 100.',
            'page.min' => 'The page must be at least 1.',
        ]);
    }
}
