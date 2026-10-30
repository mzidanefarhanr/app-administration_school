<?php

namespace App\Http\Requests;

use App\Models\SchoolRombel;
use Illuminate\Foundation\Http\FormRequest;

class AssignStudentRombelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('students.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
            'school_rombel_id' => ['required', 'integer', 'exists:school_rombels,id'],
            'expected_student_version' => ['required', 'integer'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $yearId = $this->input('school_year_id');
            $rombelId = $this->input('school_rombel_id');

            if ($yearId && $rombelId) {
                $belongs = SchoolRombel::where('id', $rombelId)
                    ->where('school_year_id', $yearId)
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add('school_rombel_id', 'The selected class does not belong to the selected academic year.');
                }
            }
        });
    }

    /**
     * Custom message definitions in English.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'school_year_id.required' => 'Academic year is required.',
            'school_year_id.exists' => 'The selected academic year does not exist.',
            'school_rombel_id.required' => 'Target class is required.',
            'school_rombel_id.exists' => 'The selected target class does not exist.',
            'school_rombel_id.integer' => 'Target class must be an integer.',
            'expected_student_version.required' => 'Expected student version is required for concurrency control.',
            'expected_student_version.integer' => 'Expected student version must be an integer.',
        ];
    }
}
