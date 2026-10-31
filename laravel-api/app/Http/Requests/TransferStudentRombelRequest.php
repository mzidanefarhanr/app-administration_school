<?php

namespace App\Http\Requests;

use App\Models\SchoolRombel;
use App\Models\SchoolYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferStudentRombelRequest extends FormRequest
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
            'school_year_id' => [
                'required', 'integer',
                Rule::exists('school_years', 'id')->whereNull('deleted_at'),
            ],
            'target_rombel_id' => [
                'required', 'integer',
                Rule::exists('school_rombels', 'id')->whereNull('deleted_at'),
            ],
            'expected_student_version' => ['required', 'integer'],
            'expected_membership_version' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $yearId = $this->input('school_year_id');
            $targetRombelId = $this->input('target_rombel_id');

            if ($yearId) {
                $schoolYear = SchoolYear::find($yearId);

                if ($schoolYear && $schoolYear->locked_at !== null) {
                    $validator->errors()->add('school_year_id', 'The selected academic year is locked. Unlock it before moving students.');
                }
            }

            if ($yearId && $targetRombelId) {
                $belongs = SchoolRombel::where('id', $targetRombelId)
                    ->where('school_year_id', $yearId)
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add('target_rombel_id', 'The target class does not belong to the selected academic year.');
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
            'school_year_id.exists' => 'The selected academic year is not available.',
            'target_rombel_id.required' => 'Target class is required.',
            'target_rombel_id.exists' => 'The selected target class does not exist.',
            'target_rombel_id.integer' => 'Target class must be an integer.',
            'expected_student_version.required' => 'Expected student version is required for concurrency control.',
            'expected_student_version.integer' => 'Expected student version must be an integer.',
            'expected_membership_version.required' => 'Expected membership version is required for concurrency control.',
            'expected_membership_version.integer' => 'Expected membership version must be an integer.',
            'reason.max' => 'Transfer reason may not be greater than 255 characters.',
        ];
    }
}
