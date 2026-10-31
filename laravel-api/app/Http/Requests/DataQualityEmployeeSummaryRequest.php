<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DataQualityEmployeeSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employees.view') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
