<?php

namespace App\Http\Requests;

use App\Support\StudentRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('students.manage') ?? false;
    }

    /**
     * Prepare the data for validation: trim and convert empty strings to null.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                $input[$key] = $trimmed === '' ? null : $trimmed;
            }
        }

        $this->replace($input);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $studentParam = $this->route('student') ?? $this->route('id');
        $studentId = is_object($studentParam) ? $studentParam->id : $studentParam;

        return array_merge(
            ['version' => ['required', 'integer']],
            StudentRules::rules($studentId)
        );
    }

    /**
     * Custom messages for identity validation errors in English.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(StudentRules::messages(), [
            'version.required' => 'Expected record version is required for optimistic concurrency control.',
        ]);
    }
}
