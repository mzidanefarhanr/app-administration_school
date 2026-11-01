<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidIncomingLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('letters.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:255'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A void reason is required.',
            'reason.min' => 'The void reason must be at least 10 characters.',
            'reason.max' => 'The void reason may not exceed 255 characters.',
            'expected_version.required' => 'Expected version is required for concurrency control.',
            'expected_version.min' => 'Expected version must be at least 1.',
        ];
    }
}
