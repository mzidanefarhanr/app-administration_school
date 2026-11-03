<?php

namespace App\Http\Requests;

use App\Models\TeachingScheduleException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeachingScheduleExceptionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxPeriod = (int) config('teaching.max_period', 12);

        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'school_year_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'semester_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'exception_type' => ['sometimes', 'required', 'string', Rule::in([
                TeachingScheduleException::TYPE_HOLIDAY,
                TeachingScheduleException::TYPE_CANCELLED,
                TeachingScheduleException::TYPE_RESCHEDULED,
            ])],
            'exception_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'school_rombel_id' => ['nullable', 'integer', 'min:1'],
            'teaching_schedule_id' => ['nullable', 'integer', 'min:1'],
            'rescheduled_date' => ['nullable', 'date_format:Y-m-d'],
            'rescheduled_period_start' => ['nullable', 'integer', 'min:1', "max:{$maxPeriod}"],
            'rescheduled_period_end' => ['nullable', 'integer', 'min:1', "max:{$maxPeriod}"],
            'rescheduled_start_time' => ['nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'rescheduled_end_time' => ['nullable', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'rescheduled_room' => ['nullable', 'string', 'max:60'],
            'substitute_employee_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['sometimes', 'required', 'string', 'min:10', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $routeExc = $this->route('teachingScheduleException');
                $effectiveType = $this->input('exception_type', $routeExc?->exception_type);

                if ($effectiveType !== TeachingScheduleException::TYPE_RESCHEDULED) {
                    return;
                }

                $rescheduledDate = $this->has('rescheduled_date')
                    ? $this->input('rescheduled_date')
                    : $routeExc?->rescheduled_date?->toDateString();
                $periodStart = $this->has('rescheduled_period_start')
                    ? $this->input('rescheduled_period_start')
                    : $routeExc?->rescheduled_period_start;
                $periodEnd = $this->has('rescheduled_period_end')
                    ? $this->input('rescheduled_period_end')
                    : $routeExc?->rescheduled_period_end;
                $start = $this->has('rescheduled_start_time')
                    ? (string) $this->input('rescheduled_start_time')
                    : (string) ($routeExc?->rescheduled_start_time ?? '');
                $end = $this->has('rescheduled_end_time')
                    ? (string) $this->input('rescheduled_end_time')
                    : (string) ($routeExc?->rescheduled_end_time ?? '');

                if (empty($rescheduledDate)) {
                    $validator->errors()->add('rescheduled_date', 'The rescheduled date field is required when exception type is rescheduled.');
                }
                if ($periodStart === null || $periodStart === '') {
                    $validator->errors()->add('rescheduled_period_start', 'The rescheduled start period is required.');
                }
                if ($periodEnd === null || $periodEnd === '') {
                    $validator->errors()->add('rescheduled_period_end', 'The rescheduled end period is required.');
                }
                if ($start === '') {
                    $validator->errors()->add('rescheduled_start_time', 'The rescheduled start time is required.');
                }
                if ($end === '') {
                    $validator->errors()->add('rescheduled_end_time', 'The rescheduled end time is required.');
                }

                if ((int) $periodStart > 0 && (int) $periodEnd > 0 && (int) $periodEnd < (int) $periodStart) {
                    $validator->errors()->add('rescheduled_period_end', 'The rescheduled end period must be greater than or equal to the start period.');
                }

                if ($start !== '' && $end !== '' && $end <= $start) {
                    $validator->errors()->add('rescheduled_end_time', 'The rescheduled end time must be later than the start time.');
                }
            },
        ];
    }
}
