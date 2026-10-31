<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait DataQualityReviewRules
{
    /**
     * @return array<int, string>
     */
    abstract protected function allowedReviewFields(): array;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function baseReviewRules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['open', 'acknowledged', 'ignored'])],
            'reason_code' => [
                'nullable',
                'string',
                Rule::in(config('data_quality.review_reasons', [])),
                'required_if:status,ignored',
            ],
            'issue_type' => ['required', 'string', Rule::in(['missing', 'invalid', 'duplicate', 'blocked'])],
            'field' => ['required', 'string', Rule::in($this->allowedReviewFields())],
            'subject_id' => ['required', 'integer'],
            'subject_version' => ['required', 'integer', 'min:1'],
            'related_subject_id' => ['nullable', 'integer', 'required_with:related_version'],
            'related_version' => ['nullable', 'integer', 'min:1', 'required_with:related_subject_id'],
            'version' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function reviewMessages(): array
    {
        return [
            'status.required' => 'Review status is required.',
            'status.in' => 'Review status must be one of: open, acknowledged, ignored.',
            'reason_code.required_if' => 'A reason is required when ignoring an issue.',
            'reason_code.in' => 'The selected reason is not allowed.',
            'issue_type.required' => 'Issue type is required.',
            'issue_type.in' => 'Issue type must be one of: missing, invalid, duplicate, blocked.',
            'field.required' => 'Issue field is required.',
            'field.in' => 'The selected issue field is not valid for this domain.',
            'subject_id.required' => 'Subject ID is required.',
            'subject_version.required' => 'Subject version is required.',
            'version.required' => 'Expected review version is required.',
            'version.min' => 'Expected review version cannot be negative.',
            'related_subject_id.required_with' => 'Related subject ID is required when a related version is provided.',
            'related_version.required_with' => 'Related subject version is required when a related subject is provided.',
            'related_version.min' => 'Related subject version must be at least 1.',
        ];
    }

    protected function enforceReviewSemantics(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        if ($this->input('reason_code') === 'verified_distinct' && $this->input('issue_type') !== 'duplicate') {
            $validator->errors()->add(
                'reason_code',
                'The verified distinct reason is only valid for duplicate issues.'
            );
        }

        if ($this->input('status') !== 'ignored' && $this->input('reason_code') !== null) {
            $validator->errors()->add(
                'reason_code',
                'A reason can only be provided when the issue is ignored.'
            );
        }
    }
}
