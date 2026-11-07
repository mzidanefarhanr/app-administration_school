<?php

namespace App\Support;

use App\Models\EmployeeType;
use Illuminate\Validation\Rule;

/**
 * Satu sumber aturan validasi pegawai untuk form dan impor (DEC-24, DEC-26).
 *
 * Import dan form harus menolak/menerima nilai yang sama; perubahan aturan
 * dilakukan di sini agar tidak menyimpang antar entry point.
 */
final class EmployeeRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?int $ignoreId = null, ?int $selectedTypeId = null): array
    {
        $teacherTypeId = (int) EmployeeType::where('name', 'Pendidik')->value('id');
        $staffTypeId = (int) EmployeeType::where('name', 'Tenaga Kependidikan')->value('id');

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
                Rule::unique('employees', 'nik')->ignore($ignoreId),
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
            'dapodik_status' => ['nullable', 'string', 'max:100'],
            'verval_elementary_school' => ['nullable', 'string', 'max:50'],
            'verval_junior_high_school' => ['nullable', 'string', 'max:50'],
            'verval_senior_high_school' => ['nullable', 'string', 'max:50'],
            'verval_bachelor_degree' => ['nullable', 'string', 'max:50'],
            'verval_diploma' => ['nullable', 'string', 'max:50'],
            'government_certificate' => ['nullable', 'boolean'],
            'certification_status' => ['nullable', 'string', 'max:50'],

            'elementary_school_name' => ['nullable', 'string', 'max:255'],
            'elementary_school_entry' => ['nullable', 'date_format:Y-m-d'],
            'elementary_school_graduation' => ['nullable', 'date_format:Y-m-d'],
            'nisn' => ['nullable', 'string', 'digits:10'],
            'elementary_school_passing_grade' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'junior_high_school_name' => ['nullable', 'string', 'max:255'],
            'junior_high_school_entry' => ['nullable', 'date_format:Y-m-d'],
            'junior_high_school_graduation' => ['nullable', 'date_format:Y-m-d'],
            'junior_high_school_nisn' => ['nullable', 'string', 'max:50'],
            'junior_high_school_passing_grade' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'senior_high_school_name' => ['nullable', 'string', 'max:255'],
            'senior_high_school_major' => ['nullable', 'string', 'max:100'],
            'senior_high_school_entry' => ['nullable', 'date_format:Y-m-d'],
            'senior_high_school_graduation' => ['nullable', 'date_format:Y-m-d'],
            'senior_high_school_nisn' => ['nullable', 'string', 'max:50'],
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
    public static function messages(): array
    {
        return [
            'name.required' => 'Employee name is required.',
            'nik.digits' => 'NIK must be exactly 16 digits.',
            'nik.unique' => 'The NIK has already been used by another employee.',
            'no_kk.digits' => 'The family card number must be exactly 16 digits.',
            'nisn.digits' => 'NISN must be exactly 10 digits.',
            'date_of_birth.before' => 'Date of birth must be before today.',
            'tmt_employee.required' => 'The TMT (employment start date) is required.',
            'subject_id.required' => 'Teachers must have a subject.',
            'subject_id.prohibited' => 'Non-teaching employees must not have a subject.',
        ];
    }
}
