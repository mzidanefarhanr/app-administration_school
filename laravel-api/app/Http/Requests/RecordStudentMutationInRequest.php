<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordStudentMutationInRequest extends FormRequest
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
            'school_year_id' => [
                'required', 'integer', 'min:1',
                Rule::exists('school_years', 'id')->whereNull('deleted_at'),
            ],
            'school_rombel_id' => ['required', 'integer', 'min:1'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'school_npsn' => ['required', 'string', 'max:20'],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
            'expected_student_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'school_year_id.required' => 'The academic year is required.',
            'school_year_id.exists' => 'The selected academic year is not available.',
            'school_rombel_id.required' => 'The target class is required.',
            'school_rombel_id.integer' => 'The target class must be an integer identifier.',
            'effective_date.required' => 'The effective date is required.',
            'effective_date.date_format' => 'The effective date must use the YYYY-MM-DD format.',
            'school_npsn.required' => 'The origin school is required.',
            'school_npsn.max' => 'The origin school identifier is too long.',
            'reason.required' => 'A reason is required.',
            'reason.min' => 'The reason must be at least 10 characters.',
            'reason.max' => 'The reason may not exceed 255 characters.',
            'expected_student_version.required' => 'Expected student version is required for concurrency control.',
            'expected_student_version.min' => 'Expected student version must be at least 1.',
        ];
    }
}
