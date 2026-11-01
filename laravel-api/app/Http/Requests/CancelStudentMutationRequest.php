<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelStudentMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('students.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:255'],
            'expected_student_version' => ['required', 'integer', 'min:1'],
            'expected_membership_version' => ['required', 'integer', 'min:1'],
            'expected_mutation_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A cancellation reason is required.',
            'reason.min' => 'The cancellation reason must be at least 10 characters.',
            'reason.max' => 'The cancellation reason may not exceed 255 characters.',
            'expected_student_version.required' => 'Expected student version is required for concurrency control.',
            'expected_student_version.min' => 'Expected student version must be at least 1.',
            'expected_membership_version.required' => 'Expected class membership version is required for concurrency control.',
            'expected_membership_version.min' => 'Expected class membership version must be at least 1.',
            'expected_mutation_version.required' => 'Expected mutation version is required for concurrency control.',
            'expected_mutation_version.min' => 'Expected mutation version must be at least 1.',
        ];
    }
}
