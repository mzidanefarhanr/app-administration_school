<?php

namespace App\Http\Requests;

use App\Models\TeachingScheduleException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeachingScheduleExceptionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxPeriod = (int) config('teaching.max_period', 12);

        return [
            'school_year_id' => ['required', 'integer', 'min:1'],
            'semester_id' => ['required', 'integer', 'min:1'],
            'exception_type' => ['required', 'string', Rule::in([
                TeachingScheduleException::TYPE_HOLIDAY,
                TeachingScheduleException::TYPE_CANCELLED,
                TeachingScheduleException::TYPE_RESCHEDULED,
            ])],
            'exception_date' => ['required', 'date_format:Y-m-d'],
            'school_rombel_id' => ['nullable', 'integer', 'min:1'],
            'teaching_schedule_id' => [
                Rule::requiredIf(fn () => in_array($this->input('exception_type'), [
                    TeachingScheduleException::TYPE_CANCELLED,
                    TeachingScheduleException::TYPE_RESCHEDULED,
                ], true)),
                'nullable',
                'integer',
                'min:1',
            ],
            'rescheduled_date' => [
                Rule::requiredIf(fn () => $this->input('exception_type') === TeachingScheduleException::TYPE_RESCHEDULED),
                'nullable',
                'date_format:Y-m-d',
            ],
            'rescheduled_period_start' => [
                Rule::requiredIf(fn () => $this->input('exception_type') === TeachingScheduleException::TYPE_RESCHEDULED),
                'nullable',
                'integer',
                'min:1',
                "max:{$maxPeriod}",
            ],
            'rescheduled_period_end' => [
                Rule::requiredIf(fn () => $this->input('exception_type') === TeachingScheduleException::TYPE_RESCHEDULED),
                'nullable',
                'integer',
                'min:1',
                "max:{$maxPeriod}",
            ],
            'rescheduled_start_time' => [
                Rule::requiredIf(fn () => $this->input('exception_type') === TeachingScheduleException::TYPE_RESCHEDULED),
                'nullable',
                'string',
                'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            ],
            'rescheduled_end_time' => [
                Rule::requiredIf(fn () => $this->input('exception_type') === TeachingScheduleException::TYPE_RESCHEDULED),
                'nullable',
                'string',
                'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            ],
            'rescheduled_room' => ['nullable', 'string', 'max:60'],
            'substitute_employee_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('exception_type') !== TeachingScheduleException::TYPE_RESCHEDULED) {
                    return;
                }

                $periodStart = (int) $this->input('rescheduled_period_start', 0);
                $periodEnd = (int) $this->input('rescheduled_period_end', 0);
                if ($periodStart > 0 && $periodEnd > 0 && $periodEnd < $periodStart) {
                    $validator->errors()->add('rescheduled_period_end', 'The rescheduled end period must be greater than or equal to the start period.');
                }

                $start = (string) $this->input('rescheduled_start_time', '');
                $end = (string) $this->input('rescheduled_end_time', '');
                if ($start !== '' && $end !== '' && $end <= $start) {
                    $validator->errors()->add('rescheduled_end_time', 'The rescheduled end time must be later than the start time.');
                }
            },
        ];
    }
}
