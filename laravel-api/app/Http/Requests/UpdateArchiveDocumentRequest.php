<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArchiveDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAbility('archives.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['title', 'document_number', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = trim($this->input($field));
            }
        }

        foreach (['document_number', 'valid_from', 'valid_until', 'description'] as $field) {
            if (is_string($this->input($field)) && trim($this->input($field)) === '') {
                $merge[$field] = null;
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'required', 'string', Rule::in(array_keys((array) config('archives.categories')))],
            'title' => [
                'sometimes',
                'required',
                'string',
                'min:'.(int) config('archives.title.min'),
                'max:'.(int) config('archives.title.max'),
            ],
            'document_number' => ['nullable', 'string', 'max:'.(int) config('archives.document_number.max')],
            'document_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'valid_from' => ['nullable', 'date_format:Y-m-d', 'required_with:valid_until'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'required_with:valid_from', 'after_or_equal:valid_from'],
            'description' => ['nullable', 'string', 'max:'.(int) config('archives.description.max')],
            'expected_version' => ['required', 'integer', 'min:1'],
            'subject_type' => ['prohibited'],
            'subject_id' => ['prohibited'],
            'file' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => 'Select an archive category.',
            'category.in' => 'The selected archive category is not valid.',
            'title.required' => 'A title is required.',
            'title.min' => 'The title must be at least '.(int) config('archives.title.min').' characters.',
            'title.max' => 'The title may not exceed '.(int) config('archives.title.max').' characters.',
            'document_date.required' => 'The document date is required.',
            'document_date.date_format' => 'The document date must use the YYYY-MM-DD format.',
            'valid_from.date_format' => 'The valid from date must use the YYYY-MM-DD format.',
            'valid_from.required_with' => 'Provide both valid from and valid until, or neither.',
            'valid_until.date_format' => 'The valid until date must use the YYYY-MM-DD format.',
            'valid_until.required_with' => 'Provide both valid from and valid until, or neither.',
            'valid_until.after_or_equal' => 'The valid until date may not be before the valid from date.',
            'expected_version.required' => 'The expected version is required.',
            'expected_version.min' => 'The expected version must be at least 1.',
            'subject_type.prohibited' => 'The subject link cannot be changed after creation.',
            'subject_id.prohibited' => 'The subject link cannot be changed after creation.',
            'file.prohibited' => 'Use the add version action to upload a new file.',
        ];
    }
}
