<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeachingJournalUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->hasAbility('teaching.manage')) {
            return true;
        }

        if (! $user->hasAbility('teaching.view')) {
            return false;
        }

        return Employee::query()
            ->eligibleTeacher()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function rules(): array
    {
        $methods = array_keys(config('teaching.journal_methods', []));

        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'topic' => ['sometimes', 'required', 'string', 'min:3', 'max:200'],
            'method' => ['sometimes', 'required', 'string', Rule::in($methods)],
            'summary' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'actual_start_time' => ['nullable', 'date_format:H:i'],
            'actual_end_time' => ['nullable', 'date_format:H:i'],
            'teacher_employee_id' => ['nullable', 'integer', 'min:1'],
            'correction_reason' => ['nullable', 'string', 'min:10', 'max:255'],
        ];
    }
}
