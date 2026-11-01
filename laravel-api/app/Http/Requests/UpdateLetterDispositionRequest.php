<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLetterDispositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('letters.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['note', 'follow_up_note', 'cancel_reason'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(config('letters.disposition_statuses'))],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:'.(int) config('letters.disposition_note_max_length')],
            'follow_up_note' => [
                'nullable',
                'string',
                'min:'.(int) config('letters.disposition_follow_up_min_length'),
                'max:'.(int) config('letters.disposition_follow_up_max_length'),
            ],
            'cancel_reason' => [
                'nullable',
                'string',
                'min:'.(int) config('letters.disposition_cancel_reason_min_length'),
                'max:'.(int) config('letters.disposition_cancel_reason_max_length'),
            ],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'The selected disposition status is not valid.',
            'due_date.date_format' => 'The due date must use the YYYY-MM-DD format.',
            'note.max' => 'The disposition note may not exceed 2000 characters.',
            'follow_up_note.min' => 'The follow-up note must be at least 10 characters.',
            'follow_up_note.max' => 'The follow-up note may not exceed 2000 characters.',
            'cancel_reason.min' => 'The cancellation reason must be at least 10 characters.',
            'cancel_reason.max' => 'The cancellation reason may not exceed 255 characters.',
            'expected_version.required' => 'Expected version is required for concurrency control.',
            'expected_version.min' => 'Expected version must be at least 1.',
        ];
    }
}
