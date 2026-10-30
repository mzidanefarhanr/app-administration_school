<?php

namespace App\Http\Requests\Concerns;

use App\Support\EmployeeRules;

/**
 * Shared profile rules for employee create/update (DEC-24 D1/D2/D4).
 *
 * The definitions live in App\Support\EmployeeRules so the CSV import uses
 * exactly the same rules as the form.
 */
trait EmployeeProfileRules
{
    /**
     * @return array<string, mixed>
     */
    protected function profileRules(?int $employeeId = null): array
    {
        return EmployeeRules::rules($employeeId, (int) $this->input('employee_type_id'));
    }

    /**
     * @return array<string, string>
     */
    protected function profileMessages(): array
    {
        return EmployeeRules::messages();
    }
}
