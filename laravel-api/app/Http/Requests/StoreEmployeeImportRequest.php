<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employees.import') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxKb = (int) config('imports.employees.max_size_kb', 5120);

        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:'.$maxKb],
            'mode' => ['required', 'string', 'in:insert,update'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a CSV or Excel file to import.',
            'file.mimes' => 'The file must be a CSV (UTF-8) or Excel (.xlsx) file.',
            'file.max' => 'The file is larger than the allowed limit.',
            'mode.in' => 'Import mode must be either insert or update.',
        ];
    }
}
