<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecapLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['leaves.view']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', 'string', Rule::in(array_keys((array) config('leaves.statuses')))],
            'type' => ['nullable', 'string', 'max:30'],
            'subject_type' => ['nullable', 'string', Rule::in(array_keys((array) config('leaves.subject_types')))],
            'school_year_id' => ['nullable', 'integer', 'min:1'],
            'rombel_id' => ['nullable', 'integer', 'min:1'],
            'employee_type_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required' => 'The recap start date is required.',
            'from.date_format' => 'The recap start date must use the YYYY-MM-DD format.',
            'to.required' => 'The recap end date is required.',
            'to.date_format' => 'The recap end date must use the YYYY-MM-DD format.',
            'to.after_or_equal' => 'The recap end date may not be before the start date.',
            'status.in' => 'The selected status is not valid.',
            'subject_type.in' => 'The selected subject type is not valid.',
            'school_year_id.min' => 'The selected school year is not valid.',
            'rombel_id.min' => 'The selected class is not valid.',
            'employee_type_id.min' => 'The selected employee type is not valid.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $subjectType = $this->input('subject_type');
            $type = $this->input('type');
            $rombelId = $this->input('rombel_id');
            $schoolYearId = $this->input('school_year_id');
            $employeeTypeId = $this->input('employee_type_id');

            if ($rombelId !== null && $employeeTypeId !== null) {
                $validator->errors()->add('rombel_id', 'Class and employee type filters cannot be combined.');
            }

            if ($subjectType === 'student' && $employeeTypeId !== null) {
                $validator->errors()->add('employee_type_id', 'Employee type cannot be used to filter student leave.');
            }

            if ($subjectType === 'employee' && ($rombelId !== null || $schoolYearId !== null)) {
                $validator->errors()->add('subject_type', 'Class and school year filters cannot be used to filter employee leave.');
            }

            if ($type !== null && $subjectType !== null) {
                $definition = config('leaves.types.'.$subjectType.'.'.$type);

                if (! is_array($definition)) {
                    $validator->errors()->add('type', 'The selected leave type is not valid for this subject type.');
                }
            }

            if ($type !== null && $subjectType === null) {
                $known = collect((array) config('leaves.types'))
                    ->contains(fn (array $types) => is_array($types) && array_key_exists($type, $types));

                if (! $known) {
                    $validator->errors()->add('type', 'The selected leave type is not valid.');
                }
            }

            $from = Carbon::createFromFormat('Y-m-d', (string) $this->input('from'), 'Asia/Jakarta')->startOfDay();
            $to = Carbon::createFromFormat('Y-m-d', (string) $this->input('to'), 'Asia/Jakarta')->startOfDay();
            $days = (int) $from->diffInDays($to) + 1;

            if ($days > LeaveRequest::RECAP_MAX_RANGE_DAYS) {
                $validator->errors()->add('to', 'The recap period may not exceed '.LeaveRequest::RECAP_MAX_RANGE_DAYS.' days.');
            }
        });
    }
}
