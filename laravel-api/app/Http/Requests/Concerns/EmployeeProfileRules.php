<?php

namespace App\Http\Requests\Concerns;

use App\Models\EmployeeType;
use Illuminate\Validation\Rule;

/**
 * Shared profile rules for employee create/update (DEC-24 D1/D2/D4).
 */
trait EmployeeProfileRules
{
    /**
     * @return array<string, mixed>
     */
    protected function profileRules(?int $employeeId = null): array
    {
        $teacherTypeId = (int) EmployeeType::where('name', 'Pendidik')->value('id');
        $staffTypeId = (int) EmployeeType::where('name', 'Tenaga Kependidikan')->value('id');
        $selectedTypeId = (int) $this->input('employee_type_id');

        $subjectRules = ['nullable', 'integer', 'exists:subjects,id'];
        if ($teacherTypeId && $selectedTypeId === $teacherTypeId) {
            $subjectRules[] = 'required';
        }
        if ($staffTypeId && $selectedTypeId === $staffTypeId) {
            $subjectRules[] = 'prohibited';
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'nik' => [
                'nullable',
                'string',
                'digits:16',
                Rule::unique('employees', 'nik')->ignore($employeeId),
            ],
            'nuptk' => ['nullable', 'string', 'max:50'],
            'employee_type_id' => ['required', 'integer', 'exists:employee_types,id'],
            'employee_status_id' => ['required', 'integer', 'exists:employee_statuses,id'],
            'employment_id' => ['required', 'integer', 'exists:employments,id'],
            'subject_id' => $subjectRules,
            'religion_id' => ['required', 'integer', 'exists:religions,id'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'marital_status_id' => ['required', 'integer', 'exists:marital_statuses,id'],
            'gender_id' => ['required', 'integer', 'exists:genders,id'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'no_kk' => ['nullable', 'string', 'digits:16'],
            'place_of_birth' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'wa_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'appointment_certificate' => ['nullable', 'string', 'max:255'],
            'tmt_employee' => ['required', 'date_format:Y-m-d'],
            'certificate_of_teaching_hours' => ['nullable', 'string', 'max:255'],
            'biological_mothers_name' => ['nullable', 'string', 'max:255'],
            'partners_name' => ['nullable', 'string', 'max:255'],
            'check_dapodik' => ['nullable', 'boolean'],
            'government_certificate' => ['nullable', 'boolean'],

            'elementary_school_name' => ['nullable', 'string', 'max:255'],
            'elementary_school_entry' => ['nullable', 'date_format:Y-m-d'],
            'elementary_school_graduation' => ['nullable', 'date_format:Y-m-d'],
            'nisn' => ['nullable', 'string', 'digits:10'],
            'elementary_school_passing_grade' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'junior_high_school_name' => ['nullable', 'string', 'max:255'],
            'junior_high_school_entry' => ['nullable', 'date_format:Y-m-d'],
            'junior_high_school_graduation' => ['nullable', 'date_format:Y-m-d'],
            'junior_high_school_passing_grade' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'senior_high_school_name' => ['nullable', 'string', 'max:255'],
            'senior_high_school_entry' => ['nullable', 'date_format:Y-m-d'],
            'senior_high_school_graduation' => ['nullable', 'date_format:Y-m-d'],
            'senior_high_school_passing_grade' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'bachelor_campus_name' => ['nullable', 'string', 'max:255'],
            'bachelor_major' => ['nullable', 'string', 'max:255'],
            'bachelor_faculty' => ['nullable', 'string', 'max:255'],
            'bachelor_entry' => ['nullable', 'date_format:Y-m-d'],
            'bachelor_graduation' => ['nullable', 'date_format:Y-m-d'],
            'bachelor_nim' => ['nullable', 'string', 'max:50'],
            'bachelor_passing_grade' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'master_campus_name' => ['nullable', 'string', 'max:255'],
            'master_major' => ['nullable', 'string', 'max:255'],
            'master_faculty' => ['nullable', 'string', 'max:255'],
            'master_entry' => ['nullable', 'date_format:Y-m-d'],
            'master_graduation' => ['nullable', 'date_format:Y-m-d'],
            'master_nim' => ['nullable', 'string', 'max:50'],
            'master_passing_grade' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'exit_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function profileMessages(): array
    {
        return [
            'name.required' => 'Employee name is required.',
            'nik.digits' => 'NIK must be exactly 16 digits.',
            'nik.unique' => 'The NIK has already been used by another employee.',
            'no_kk.digits' => 'The family card number must be exactly 16 digits.',
            'nisn.digits' => 'NISN must be exactly 10 digits.',
            'date_of_birth.before' => 'Date of birth must be before today.',
            'tmt_employee.required' => 'The TMT (employment start date) is required.',
            'subject_id.required' => 'Teaching staff (Pendidik) must have a subject.',
            'subject_id.prohibited' => 'Non-teaching staff (Tenaga Kependidikan) must not have a subject.',
        ];
    }
}
