<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueOutgoingLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('letters.sign') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expected_version.required' => 'Expected version is required for concurrency control.',
            'expected_version.min' => 'Expected version must be at least 1.',
        ];
    }
}
