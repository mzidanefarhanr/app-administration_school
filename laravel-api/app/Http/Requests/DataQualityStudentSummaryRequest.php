<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DataQualityStudentSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('students.view') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'school_year_id.required' => 'A school year is required to summarize student data quality.',
            'school_year_id.exists' => 'The selected school year does not exist.',
        ];
    }
}
