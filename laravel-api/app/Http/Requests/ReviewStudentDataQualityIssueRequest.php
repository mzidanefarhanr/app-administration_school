<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DataQualityReviewRules;
use App\Services\DataQuality\StudentCompletenessProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReviewStudentDataQualityIssueRequest extends FormRequest
{
    use DataQualityReviewRules;

    public function authorize(): bool
    {
        return $this->user()?->can('students.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->baseReviewRules(), [
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
        ]);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->enforceReviewSemantics($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->reviewMessages(), [
            'school_year_id.required' => 'A school year is required for student reviews.',
            'school_year_id.exists' => 'The selected school year does not exist.',
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function allowedReviewFields(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(StudentCompletenessProfile::REQUIRED_FIELDS),
            StudentCompletenessProfile::DUPLICATE_FIELDS
        )));
    }
}
