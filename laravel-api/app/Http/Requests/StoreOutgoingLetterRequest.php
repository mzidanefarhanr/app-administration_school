<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOutgoingLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('letters.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'received_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'letter_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:received_date'],
            'sender' => ['required', 'string', 'max:150'],
            'recipient' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', Rule::in(config('letters.categories'))],
            'letter_type' => ['nullable', 'string', Rule::in(array_keys(config('letters.letter_types')))],
            'document_subject_id' => ['nullable', 'integer', 'min:1'],
            'document_fields' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'received_date.required' => 'The recorded date is required.',
            'received_date.date_format' => 'The recorded date must use the YYYY-MM-DD format.',
            'received_date.before_or_equal' => 'The recorded date may not be in the future.',
            'letter_date.required' => 'The letter date is required.',
            'letter_date.date_format' => 'The letter date must use the YYYY-MM-DD format.',
            'letter_date.before_or_equal' => 'The letter date may not be after the recorded date.',
            'sender.required' => 'The sender is required.',
            'sender.max' => 'The sender may not exceed 150 characters.',
            'recipient.required' => 'The recipient is required.',
            'recipient.max' => 'The recipient may not exceed 150 characters.',
            'subject.required' => 'The subject is required.',
            'subject.max' => 'The subject may not exceed 200 characters.',
            'category.required' => 'The category is required.',
            'category.in' => 'The selected category is not valid.',
            'letter_type.in' => 'The selected letter type is not valid.',
            'document_subject_id.integer' => 'The document subject must be a valid record ID.',
            'document_subject_id.min' => 'The document subject must be a valid record ID.',
            'document_fields.array' => 'The document fields must be an array.',
            'notes.max' => 'The notes may not exceed 2000 characters.',
        ];
    }
}
