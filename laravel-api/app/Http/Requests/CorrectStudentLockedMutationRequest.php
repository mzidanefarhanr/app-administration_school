<?php

namespace App\Http\Requests;

use App\Models\StudentLockedCorrection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CorrectStudentLockedMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('students.manage') ?? false)
            && ($this->user()?->can('students.correct-locked') ?? false);
    }

    protected function prepareForValidation(): void
    {
        foreach (['mutation_id', 'student_rombel_id', 'effective_date', 'school_npsn', 'target_status_id', 'approval_reference', 'expected_mutation_version'] as $field) {
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
            'action' => ['required', 'string', Rule::in(StudentLockedCorrection::ACTIONS)],
            'school_year_id' => [
                'required', 'integer', 'min:1',
                Rule::exists('school_years', 'id')->whereNull('deleted_at'),
            ],
            'mutation_id' => ['nullable', 'integer', 'min:1'],
            'student_rombel_id' => ['nullable', 'integer', 'min:1'],
            'effective_date' => ['nullable', 'date_format:Y-m-d'],
            'school_npsn' => ['nullable', 'string', 'max:20'],
            'target_status_id' => ['nullable', 'integer', Rule::in([2, 6])],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
            'approval_reference' => ['nullable', 'string', 'max:100'],
            'expected_student_version' => ['required', 'integer', 'min:1'],
            'expected_membership_version' => ['required', 'integer', 'min:1'],
            'expected_mutation_version' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'A correction action is required.',
            'action.in' => 'The correction action is not supported.',
            'school_year_id.required' => 'The academic year is required.',
            'school_year_id.exists' => 'The selected academic year is not available.',
            'mutation_id.integer' => 'The mutation must be an integer identifier.',
            'student_rombel_id.integer' => 'The class membership must be an integer identifier.',
            'effective_date.date_format' => 'The effective date must use the YYYY-MM-DD format.',
            'school_npsn.max' => 'The school identifier is too long.',
            'target_status_id.in' => 'The target status must be Mutation Out or Graduated.',
            'reason.required' => 'A reason is required.',
            'reason.min' => 'The reason must be at least 10 characters.',
            'reason.max' => 'The reason may not exceed 255 characters.',
            'approval_reference.max' => 'The approval reference may not exceed 100 characters.',
            'expected_student_version.required' => 'Expected student version is required for concurrency control.',
            'expected_student_version.min' => 'Expected student version must be at least 1.',
            'expected_membership_version.required' => 'Expected class membership version is required for concurrency control.',
            'expected_membership_version.min' => 'Expected class membership version must be at least 1.',
            'expected_mutation_version.min' => 'Expected mutation version must be at least 1.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $action = (string) $this->input('action');
                $mutationActions = [
                    StudentLockedCorrection::ACTION_MUTATION_IN,
                    StudentLockedCorrection::ACTION_MUTATION_OUT,
                    StudentLockedCorrection::ACTION_MUTATION_CANCEL,
                ];
                $membershipActions = [
                    StudentLockedCorrection::ACTION_MEMBERSHIP_EXIT,
                    StudentLockedCorrection::ACTION_MEMBERSHIP_REOPEN,
                ];

                if (in_array($action, $mutationActions, true)) {
                    if ($this->input('mutation_id') === null) {
                        $validator->errors()->add('mutation_id', 'The mutation is required for this correction action.');
                    }

                    if ($this->input('expected_mutation_version') === null) {
                        $validator->errors()->add('expected_mutation_version', 'Expected mutation version is required for mutation corrections.');
                    }
                }

                if (in_array($action, [StudentLockedCorrection::ACTION_MUTATION_IN, StudentLockedCorrection::ACTION_MUTATION_OUT], true)
                    && $this->input('effective_date') === null
                    && trim((string) $this->input('school_npsn')) === '') {
                    $validator->errors()->add('effective_date', 'Provide a corrected effective date or school.');
                }

                if (in_array($action, $membershipActions, true) && $this->input('student_rombel_id') === null) {
                    $validator->errors()->add('student_rombel_id', 'The class membership is required for this correction action.');
                }

                if ($action === StudentLockedCorrection::ACTION_MEMBERSHIP_EXIT) {
                    if ($this->input('effective_date') === null) {
                        $validator->errors()->add('effective_date', 'The effective date is required for a membership exit correction.');
                    }

                    if ($this->input('target_status_id') === null) {
                        $validator->errors()->add('target_status_id', 'The target status is required for a membership exit correction.');
                    }
                }
            },
        ];
    }
}
