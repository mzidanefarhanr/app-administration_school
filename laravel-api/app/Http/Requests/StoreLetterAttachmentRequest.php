<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLetterAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('letters.manage') ?? false;
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
                'max:'.(int) config('letters.max_file_kb'),
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'An attachment file is required.',
            'file.file' => 'The attachment must be a valid uploaded file.',
            'file.max' => 'The attachment may not be larger than 10 MB.',
            'file.mimes' => 'Only PDF, JPG, JPEG, or PNG files are allowed.',
            'file.mimetypes' => 'The attachment content type is not allowed.',
        ];
    }
}
