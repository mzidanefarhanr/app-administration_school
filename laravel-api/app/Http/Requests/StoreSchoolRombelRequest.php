<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolRombelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('students.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }

        foreach (['student_major_id', 'class_teach_employee_id', 'class_teach_id'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'school_year_id' => [
                'required', 'integer',
                Rule::exists('school_years', 'id')->whereNull('deleted_at'),
            ],
            'school_level_id' => [
                'required', 'integer',
                Rule::exists('school_levels', 'id')->whereNull('deleted_at'),
            ],
            'student_major_id' => [
                'nullable', 'integer',
                Rule::exists('student_majors', 'id')->whereNull('deleted_at'),
            ],
            'class_teach_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'class_teach_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Class name is required.',
            'name.max' => 'Class name may not be greater than 100 characters.',
            'school_year_id.required' => 'Academic year is required.',
            'school_year_id.exists' => 'The selected academic year is not available.',
            'school_level_id.required' => 'School level is required.',
            'school_level_id.exists' => 'The selected school level is not available.',
            'student_major_id.exists' => 'The selected student major is not available.',
            'class_teach_employee_id.exists' => 'The selected homeroom teacher profile does not exist.',
            'class_teach_id.exists' => 'The selected homeroom teacher account is not available.',
        ];
    }
}
