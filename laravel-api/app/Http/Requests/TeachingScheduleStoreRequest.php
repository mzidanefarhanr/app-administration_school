<?php

namespace App\Http\Requests;

use App\Models\TeachingSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeachingScheduleStoreRequest extends FormRequest
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
            'teaching_assignment_id' => ['required', 'integer', 'min:1', 'exists:teaching_assignments,id'],
            'day_of_week' => ['required', 'integer', Rule::in($validDays)],
            'period_start' => ['required', 'integer', 'min:1', "max:{$maxPeriod}"],
            'period_end' => ['required', 'integer', 'min:1', "max:{$maxPeriod}", 'gte:period_start'],
            'start_time' => ['required', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'end_time' => ['required', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'room' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'string', Rule::in([
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
                $start = (string) $this->input('start_time', '');
                $end = (string) $this->input('end_time', '');
                if ($start !== '' && $end !== '' && $end <= $start) {
                    $validator->errors()->add('end_time', 'The end time must be later than the start time.');
                }
            },
        ];
    }
}
