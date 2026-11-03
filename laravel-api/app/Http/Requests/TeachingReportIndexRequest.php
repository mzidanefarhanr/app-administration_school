<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeachingReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return $user->hasAbility('teaching.view');
    }

    public function rules(): array
    {
        return [
            'school_year_id' => ['required', 'integer', 'min:1'],
            'semester_id' => ['required', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'school_rombel_id' => ['nullable', 'integer', 'min:1'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'employee_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(['all', 'missing', 'missing_grace', 'missing_overdue', 'recorded'])],
            'scope' => ['nullable', 'string', Rule::in(['all', 'mine'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
