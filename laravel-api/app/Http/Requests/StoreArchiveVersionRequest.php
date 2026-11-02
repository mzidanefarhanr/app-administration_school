<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreArchiveVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAbility('archives.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('note'))) {
            $note = trim($this->input('note'));
            $this->merge(['note' => $note === '' ? null : $note]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.(int) config('archives.attachment.max_file_kb'),
                'mimetypes:'.implode(',', (array) config('archives.attachment.allowed_mimetypes')),
            ],
            'note' => ['nullable', 'string', 'max:'.(int) config('archives.version_note.max')],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Select a file for the new archive version.',
            'file.max' => 'The file may not be larger than '.(int) config('archives.attachment.max_file_kb').' KB.',
            'file.mimetypes' => 'Only PDF, JPG, JPEG, or PNG files are allowed.',
            'note.max' => 'The version note may not exceed '.(int) config('archives.version_note.max').' characters.',
            'expected_version.required' => 'The expected version is required.',
            'expected_version.min' => 'The expected version must be at least 1.',
        ];
    }
}
