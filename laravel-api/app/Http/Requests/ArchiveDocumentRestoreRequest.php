<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveDocumentRestoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAbility('archives.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'archive_reason' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expected_version.required' => 'The expected version is required.',
            'expected_version.min' => 'The expected version must be at least 1.',
            'archive_reason.prohibited' => 'The archive reason cannot be changed when restoring.',
        ];
    }
}
