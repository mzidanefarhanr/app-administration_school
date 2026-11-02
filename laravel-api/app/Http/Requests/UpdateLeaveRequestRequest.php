<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['leaves.manage', 'leaves.request']) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reason = (array) config('leaves.reason');

        return [
            'subject_type' => ['required', 'string', Rule::in(array_keys((array) config('leaves.subject_types')))],
            'student_id' => ['nullable', 'integer', 'min:1', 'required_if:subject_type,student'],
            'employee_id' => ['nullable', 'integer', 'min:1', 'required_if:subject_type,employee'],
            'type' => ['required', 'string', 'max:30'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:'.(int) $reason['min'], 'max:'.(int) $reason['max']],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_type.required' => 'Select whether this leave is for a student or an employee.',
            'subject_type.in' => 'The selected subject type is not valid.',
            'student_id.required_if' => 'Select a student for this leave request.',
            'student_id.min' => 'The selected student is not valid.',
            'employee_id.required_if' => 'Select an employee for this leave request.',
            'employee_id.min' => 'The selected employee is not valid.',
            'type.required' => 'Select a leave type.',
            'start_date.required' => 'The start date is required.',
            'start_date.date_format' => 'The start date must use the YYYY-MM-DD format.',
            'end_date.required' => 'The end date is required.',
            'end_date.date_format' => 'The end date must use the YYYY-MM-DD format.',
            'reason.required' => 'A reason is required.',
            'reason.min' => 'The reason must be at least '.(int) config('leaves.reason.min').' characters.',
            'reason.max' => 'The reason may not exceed '.(int) config('leaves.reason.max').' characters.',
            'expected_version.required' => 'Expected version is required for concurrency control.',
            'expected_version.min' => 'Expected version must be at least 1.',
        ];
    }
}
