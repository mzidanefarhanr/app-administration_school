<?php

namespace App\Http\Requests;

use App\Models\TeachingSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeachingScheduleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxPeriod = (int) config('teaching.max_period', 12);
        $validDays = array_keys(config('teaching.days_of_week', [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday']));

        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'teaching_assignment_id' => ['sometimes', 'required', 'integer', 'min:1', 'exists:teaching_assignments,id'],
            'day_of_week' => ['sometimes', 'required', 'integer', Rule::in($validDays)],
            'period_start' => ['sometimes', 'required', 'integer', 'min:1', "max:{$maxPeriod}"],
            'period_end' => ['sometimes', 'required', 'integer', 'min:1', "max:{$maxPeriod}"],
            'start_time' => ['sometimes', 'required', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'end_time' => ['sometimes', 'required', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'room' => ['nullable', 'string', 'max:60'],
            'status' => ['sometimes', 'required', 'string', Rule::in([
                TeachingSchedule::STATUS_ACTIVE,
                TeachingSchedule::STATUS_INACTIVE,
            ])],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $routeSchedule = $this->route('teachingSchedule');
                $periodStart = $this->has('period_start')
                    ? (int) $this->input('period_start')
                    : (int) ($routeSchedule?->period_start ?? 1);
                $periodEnd = $this->has('period_end')
                    ? (int) $this->input('period_end')
                    : (int) ($routeSchedule?->period_end ?? 1);

                if ($periodEnd < $periodStart) {
                    $validator->errors()->add('period_end', 'The end period must be greater than or equal to the start period.');
                }

                $start = $this->has('start_time')
                    ? (string) $this->input('start_time')
                    : (string) ($routeSchedule?->start_time ?? '');
                $end = $this->has('end_time')
                    ? (string) $this->input('end_time')
                    : (string) ($routeSchedule?->end_time ?? '');

                if ($start !== '' && $end !== '' && $end <= $start) {
                    $validator->errors()->add('end_time', 'The end time must be later than the start time.');
                }
            },
        ];
    }
}
