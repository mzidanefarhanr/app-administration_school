<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny(['leaves.manage', 'leaves.request']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('leaves.attachment.max_file_kb');
        $extensions = implode(',', (array) config('leaves.attachment.allowed_extensions'));
        $mimetypes = implode(',', (array) config('leaves.attachment.allowed_mimetypes'));

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimes:'.$extensions,
                'mimetypes:'.$mimetypes,
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
            'file.max' => 'The attachment may not be larger than 5 MB.',
            'file.mimes' => 'Only PDF, JPG, JPEG, or PNG files are allowed.',
            'file.mimetypes' => 'The attachment content type is not allowed.',
        ];
    }
}
