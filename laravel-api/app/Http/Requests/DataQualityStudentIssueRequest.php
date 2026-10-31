<?php

namespace App\Http\Requests;

use App\Services\DataQuality\StudentCompletenessProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DataQualityStudentIssueRequest extends FormRequest
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
            'type' => ['nullable', 'string', Rule::in(['missing', 'invalid', 'duplicate', 'blocked'])],
            'field' => ['nullable', 'string', Rule::in($this->allowedFields())],
            'status' => ['nullable', 'string', Rule::in(['open', 'acknowledged', 'ignored', 'all'])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in(config('data_quality.per_page', [10, 20, 50, 100]))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'school_year_id.required' => 'A school year is required to list student data quality issues.',
            'school_year_id.exists' => 'The selected school year does not exist.',
            'type.in' => 'Issue type must be one of: missing, invalid, duplicate, blocked.',
            'field.in' => 'The selected issue field is not valid for student data quality.',
            'status.in' => 'Issue status must be one of: open, acknowledged, ignored, all.',
            'per_page.in' => 'The per page value must be one of: 10, 20, 50, 100.',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedFields(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(StudentCompletenessProfile::REQUIRED_FIELDS),
            StudentCompletenessProfile::DUPLICATE_FIELDS
        )));
    }
}
