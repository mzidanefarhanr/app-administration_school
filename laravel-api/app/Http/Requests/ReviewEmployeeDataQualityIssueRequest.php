<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DataQualityReviewRules;
use App\Services\DataQuality\EmployeeCompletenessProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReviewEmployeeDataQualityIssueRequest extends FormRequest
{
    use DataQualityReviewRules;

    public function authorize(): bool
    {
        return $this->user()?->can('employees.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->baseReviewRules(), [
            'school_year_id' => ['prohibited'],
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
            'school_year_id.prohibited' => 'The school year field is not allowed for employee reviews.',
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function allowedReviewFields(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(EmployeeCompletenessProfile::REQUIRED_FIELDS),
            array_keys(EmployeeCompletenessProfile::TEACHER_REQUIRED_FIELDS),
            EmployeeCompletenessProfile::DUPLICATE_FIELDS
        )));
    }
}
