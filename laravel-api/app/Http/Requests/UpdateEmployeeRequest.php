<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\EmployeeProfileRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    use EmployeeProfileRules;

    public function authorize(): bool
    {
        return $this->user()?->can('employees.manage') ?? false;
    }

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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $employeeParam = $this->route('employee') ?? $this->route('id');
        $employeeId = is_object($employeeParam) ? $employeeParam->id : $employeeParam;

        return ['version' => ['required', 'integer']]
            + $this->profileRules($employeeId !== null ? (int) $employeeId : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['version.required' => 'Expected record version is required for optimistic concurrency control.']
            + $this->profileMessages();
    }
}
