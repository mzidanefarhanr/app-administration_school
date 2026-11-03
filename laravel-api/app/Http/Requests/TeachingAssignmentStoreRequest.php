<?php

namespace App\Http\Requests;

use App\Models\TeachingAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeachingAssignmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxHours = (int) config('teaching.max_hours_per_week', 24);

        return [
            'school_year_id' => ['required', 'integer', 'min:1'],
            'semester_id' => ['required', 'integer', 'min:1'],
            'school_rombel_id' => ['required', 'integer', 'min:1'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'employee_id' => ['required', 'integer', 'min:1'],
            'hours_per_week' => ['nullable', 'integer', 'min:1', "max:{$maxHours}"],
            'status' => ['nullable', 'string', Rule::in([
                TeachingAssignment::STATUS_ACTIVE,
                TeachingAssignment::STATUS_INACTIVE,
            ])],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
