<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveDocumentArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAbility('archives.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('archive_reason'))) {
            $this->merge(['archive_reason' => trim($this->input('archive_reason'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'archive_reason' => [
                'required',
                'string',
                'min:'.(int) config('archives.archive_reason.min'),
                'max:'.(int) config('archives.archive_reason.max'),
            ],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archive_reason.required' => 'A reason is required to archive this document.',
            'archive_reason.min' => 'The archive reason must be at least '.(int) config('archives.archive_reason.min').' characters.',
            'archive_reason.max' => 'The archive reason may not exceed '.(int) config('archives.archive_reason.max').' characters.',
            'expected_version.required' => 'The expected version is required.',
            'expected_version.min' => 'The expected version must be at least 1.',
        ];
    }
}
