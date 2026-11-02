<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['leaves.approve']) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reason = (array) config('leaves.decision_reason');

        return [
            'reason' => ['required', 'string', 'min:'.(int) $reason['min'], 'max:'.(int) $reason['max']],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A rejection reason is required.',
            'reason.min' => 'The rejection reason must be at least '.(int) config('leaves.decision_reason.min').' characters.',
            'reason.max' => 'The rejection reason may not exceed '.(int) config('leaves.decision_reason.max').' characters.',
            'expected_version.required' => 'Expected version is required for concurrency control.',
            'expected_version.min' => 'Expected version must be at least 1.',
        ];
    }
}
