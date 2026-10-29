<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employee payload (DEC-24 D6).
 *
 * The list form carries operational data only. Sensitive personal data is
 * added solely for callers permitted to manage employees; it is never part of
 * list responses and never part of the CSV export.
 */
class EmployeeResource extends JsonResource
{
    public function __construct($resource, private bool $includeSensitive = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Employee $employee */
        $employee = $this->resource;

        $data = [
            'id' => $employee->id,
            'version' => (int) $employee->version,
            'name' => $employee->name,
            'nuptk' => $employee->nuptk,
            'employee_type' => $this->named($employee->employeeType),
            'employee_status' => $this->named($employee->employeeStatus),
            'employment' => $this->named($employee->employment),
            'subject' => $this->named($employee->subject),
            'gender' => $this->named($employee->gender),
            'tmt_employee' => $employee->tmt_employee,
            'exit_date' => $employee->exit_date,
            'archived_at' => $employee->deleted_at?->toDateTimeString(),
            'account' => [
                'linked' => $employee->user_id !== null,
                'user_id' => $employee->user_id,
            ],
        ];

        if (! $this->includeSensitive) {
            return $data;
        }

        if ($employee->relationLoaded('user')) {
            $data['account']['name'] = $employee->user?->name;
            $data['account']['username'] = $employee->user?->username;
            $data['account']['email'] = $employee->user?->email;
        }

        return $data + [
            'nik' => $employee->nik,
            'npwp' => $employee->npwp,
            'no_kk' => $employee->no_kk,
            'place_of_birth' => $employee->place_of_birth,
            'date_of_birth' => $employee->date_of_birth,
            'address' => $employee->address,
            'rt' => $employee->rt,
            'rw' => $employee->rw,
            'zip_code' => $employee->zip_code,
            'wa_number' => $employee->wa_number,
            'email' => $employee->email,
            'religion' => $this->named($employee->religion),
            'district' => $this->named($employee->district),
            'marital_status' => $this->named($employee->maritalStatus),
            'appointment_certificate' => $employee->appointment_certificate,
            'certificate_of_teaching_hours' => $employee->certificate_of_teaching_hours,
            'biological_mothers_name' => $employee->biological_mothers_name,
            'partners_name' => $employee->partners_name,
            'check_dapodik' => (bool) $employee->check_dapodik,
            'government_certificate' => (bool) $employee->government_certificate,
            'education' => $this->education($employee),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function named(mixed $model): ?array
    {
        if ($model === null) {
            return null;
        }

        return ['id' => $model->id, 'name' => $model->name];
    }

    /**
     * @return array<string, mixed>
     */
    private function education(Employee $employee): array
    {
        return [
            'elementary_school_name' => $employee->elementary_school_name,
            'elementary_school_entry' => $employee->elementary_school_entry,
            'elementary_school_graduation' => $employee->elementary_school_graduation,
            'nisn' => $employee->nisn,
            'elementary_school_passing_grade' => $employee->elementary_school_passing_grade,
            'junior_high_school_name' => $employee->junior_high_school_name,
            'junior_high_school_entry' => $employee->junior_high_school_entry,
            'junior_high_school_graduation' => $employee->junior_high_school_graduation,
            'junior_high_school_passing_grade' => $employee->junior_high_school_passing_grade,
            'senior_high_school_name' => $employee->senior_high_school_name,
            'senior_high_school_entry' => $employee->senior_high_school_entry,
            'senior_high_school_graduation' => $employee->senior_high_school_graduation,
            'senior_high_school_passing_grade' => $employee->senior_high_school_passing_grade,
            'bachelor_campus_name' => $employee->bachelor_campus_name,
            'bachelor_major' => $employee->bachelor_major,
            'bachelor_faculty' => $employee->bachelor_faculty,
            'bachelor_entry' => $employee->bachelor_entry,
            'bachelor_graduation' => $employee->bachelor_graduation,
            'bachelor_nim' => $employee->bachelor_nim,
            'bachelor_passing_grade' => $employee->bachelor_passing_grade,
            'master_campus_name' => $employee->master_campus_name,
            'master_major' => $employee->master_major,
            'master_faculty' => $employee->master_faculty,
            'master_entry' => $employee->master_entry,
            'master_graduation' => $employee->master_graduation,
            'master_nim' => $employee->master_nim,
            'master_passing_grade' => $employee->master_passing_grade,
        ];
    }
}
