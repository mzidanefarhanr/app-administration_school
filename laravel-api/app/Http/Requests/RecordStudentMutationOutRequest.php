<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordStudentMutationOutRequest extends FormRequest
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
            'student_rombel_id' => ['required', 'integer', 'min:1'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'school_npsn' => ['required', 'string', 'max:20'],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
            'expected_student_version' => ['required', 'integer', 'min:1'],
            'expected_membership_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_rombel_id.required' => 'The class membership is required.',
            'student_rombel_id.integer' => 'The class membership must be an integer identifier.',
            'effective_date.required' => 'The effective date is required.',
            'effective_date.date_format' => 'The effective date must use the YYYY-MM-DD format.',
            'school_npsn.required' => 'The destination school is required.',
            'school_npsn.max' => 'The destination school identifier is too long.',
            'reason.required' => 'A reason is required.',
            'reason.min' => 'The reason must be at least 10 characters.',
            'reason.max' => 'The reason may not exceed 255 characters.',
            'expected_student_version.required' => 'Expected student version is required for concurrency control.',
            'expected_student_version.min' => 'Expected student version must be at least 1.',
            'expected_membership_version.required' => 'Expected class membership version is required for concurrency control.',
            'expected_membership_version.min' => 'Expected class membership version must be at least 1.',
        ];
    }
}
