<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\EmployeeProfileRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
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
        return $this->profileRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->profileMessages();
    }
}
