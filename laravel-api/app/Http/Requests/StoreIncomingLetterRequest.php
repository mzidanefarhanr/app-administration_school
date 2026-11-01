<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncomingLetterRequest extends FormRequest
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
            'source_number' => ['nullable', 'string', 'max:100'],
            'sender' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', Rule::in(config('letters.categories'))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'received_date.required' => 'The received date is required.',
            'received_date.date_format' => 'The received date must use the YYYY-MM-DD format.',
            'received_date.before_or_equal' => 'The received date may not be in the future.',
            'letter_date.required' => 'The letter date is required.',
            'letter_date.date_format' => 'The letter date must use the YYYY-MM-DD format.',
            'letter_date.before_or_equal' => 'The letter date may not be after the received date.',
            'source_number.max' => 'The source number may not exceed 100 characters.',
            'sender.required' => 'The sender is required.',
            'sender.max' => 'The sender may not exceed 150 characters.',
            'subject.required' => 'The subject is required.',
            'subject.max' => 'The subject may not exceed 200 characters.',
            'category.required' => 'The category is required.',
            'category.in' => 'The selected category is not valid.',
            'notes.max' => 'The notes may not exceed 2000 characters.',
        ];
    }
}
