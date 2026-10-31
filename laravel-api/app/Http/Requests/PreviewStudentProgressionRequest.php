<?php

namespace App\Http\Requests;

use App\Services\Academic\StudentProgressionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PreviewStudentProgressionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('students.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('graduation_date') === '') {
            $this->merge(['graduation_date' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_school_year_id' => [
                'required', 'integer',
                Rule::exists('school_years', 'id')->whereNull('deleted_at'),
            ],
            'target_school_year_id' => [
                'required', 'integer', 'different:source_school_year_id',
                Rule::exists('school_years', 'id')->whereNull('deleted_at'),
            ],
            'graduation_date' => ['nullable', 'date_format:Y-m-d'],
            'decisions' => ['required', 'array', 'min:1', 'max:'.StudentProgressionService::MAX_BATCH_SIZE],
            'decisions.*.student_id' => ['required', 'integer', 'distinct', 'min:1'],
            'decisions.*.membership_id' => ['required', 'integer', 'distinct', 'min:1'],
            'decisions.*.expected_student_version' => ['required', 'integer', 'min:1'],
            'decisions.*.expected_membership_version' => ['required', 'integer', 'min:1'],
            'decisions.*.decision' => ['required', 'string', Rule::in(StudentProgressionService::DECISIONS)],
            'decisions.*.target_rombel_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_school_year_id.required' => 'Source academic year is required.',
            'source_school_year_id.exists' => 'The selected source academic year is not available.',
            'target_school_year_id.required' => 'Target academic year is required.',
            'target_school_year_id.different' => 'The target academic year must be different from the source academic year.',
            'target_school_year_id.exists' => 'The selected target academic year is not available.',
            'graduation_date.date_format' => 'The graduation date must use the YYYY-MM-DD format.',
            'decisions.required' => 'At least one student decision is required.',
            'decisions.min' => 'At least one student decision is required.',
            'decisions.max' => 'A progression batch may contain at most '.StudentProgressionService::MAX_BATCH_SIZE.' decisions.',
            'decisions.*.student_id.required' => 'Each decision needs a student.',
            'decisions.*.student_id.distinct' => 'A student may only appear once in a progression batch.',
            'decisions.*.membership_id.required' => 'Each decision needs a class membership.',
            'decisions.*.membership_id.distinct' => 'A class membership may only appear once in a progression batch.',
            'decisions.*.expected_student_version.required' => 'Expected student version is required for concurrency control.',
            'decisions.*.expected_student_version.min' => 'Expected student version must be at least 1.',
            'decisions.*.expected_membership_version.required' => 'Expected class membership version is required for concurrency control.',
            'decisions.*.expected_membership_version.min' => 'Expected class membership version must be at least 1.',
            'decisions.*.decision.required' => 'Each decision needs a progression decision.',
            'decisions.*.decision.in' => 'The progression decision must be promote, retain, graduate, or skip.',
            'decisions.*.target_rombel_id.integer' => 'The target class must be an integer identifier.',
            'decisions.*.target_rombel_id.min' => 'The target class must be an integer identifier.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $decisions = $this->input('decisions');
                if (! is_array($decisions)) {
                    return;
                }

                $sourceYearId = (int) $this->input('source_school_year_id');
                $membershipIds = collect($decisions)
                    ->pluck('membership_id')
                    ->filter(fn ($id) => is_numeric($id))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $validMemberships = [];

                if ($sourceYearId > 0 && $membershipIds !== []) {
                    $validMemberships = DB::table('student_rombels as sr')
                        ->join('school_rombels as rb', 'rb.id', '=', 'sr.school_rombel')
                        ->whereIn('sr.id', $membershipIds)
                        ->whereNull('sr.deleted_at')
                        ->whereNull('rb.deleted_at')
                        ->where('rb.school_year_id', $sourceYearId)
                        ->pluck('sr.id')
                        ->map(fn ($id) => (int) $id)
                        ->flip()
                        ->all();
                }

                foreach ($decisions as $index => $row) {
                    if (($row['decision'] ?? null) === 'promote' && empty($row['target_rombel_id'])) {
                        $validator->errors()->add(
                            "decisions.{$index}.target_rombel_id",
                            'A target class is required for promoted students.'
                        );
                    }

                    if (isset($row['membership_id']) && ! isset($validMemberships[(int) $row['membership_id']])) {
                        $validator->errors()->add(
                            "decisions.{$index}.membership_id",
                            'The selected class membership does not belong to the source academic year.'
                        );
                    }
                }
            },
        ];
    }
}
