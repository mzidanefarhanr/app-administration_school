<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLetterDispositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('letters.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('note'))) {
            $this->merge(['note' => trim($this->input('note'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipients' => ['required', 'array', 'min:1', 'max:100'],
            'recipients.*' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:'.(int) config('letters.disposition_note_max_length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipients.required' => 'At least one recipient is required.',
            'recipients.array' => 'Recipients must be a list of employee IDs.',
            'recipients.min' => 'At least one recipient is required.',
            'recipients.max' => 'A disposition may have at most 100 recipients.',
            'recipients.*.required' => 'Each recipient must be an employee ID.',
            'recipients.*.integer' => 'Each recipient must be an employee ID.',
            'recipients.*.distinct' => 'The same recipient cannot be selected twice.',
            'recipients.*.exists' => 'One or more recipients were not found.',
            'due_date.required' => 'A due date is required.',
            'due_date.date_format' => 'The due date must use the YYYY-MM-DD format.',
            'note.max' => 'The disposition note may not exceed 2000 characters.',
        ];
    }
}
