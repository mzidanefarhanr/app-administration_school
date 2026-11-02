<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLetterSignatoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('letters.sign') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxImageKb = (int) config('letters.document.max_image_kb');

        return [
            'expected_version' => ['nullable', 'integer', 'min:0'],
            'signatory_name' => ['required', 'string', 'max:150'],
            'signatory_title' => ['required', 'string', 'max:150'],
            'signatory_identifier_label' => ['nullable', 'string', 'max:30'],
            'signatory_identifier' => ['nullable', 'string', 'max:50'],
            'signature' => [
                'nullable',
                'file',
                'max:'.$maxImageKb,
                'mimes:png,jpg,jpeg',
                'mimetypes:image/png,image/jpeg',
            ],
            'stamp' => [
                'nullable',
                'file',
                'max:'.$maxImageKb,
                'mimes:png,jpg,jpeg',
                'mimetypes:image/png,image/jpeg',
            ],
            'remove_stamp' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expected_version.integer' => 'The expected version must be a number.',
            'expected_version.min' => 'The expected version may not be negative.',
            'signatory_name.required' => 'The signatory name is required.',
            'signatory_name.max' => 'The signatory name may not exceed 150 characters.',
            'signatory_title.required' => 'The signatory title is required.',
            'signatory_title.max' => 'The signatory title may not exceed 150 characters.',
            'signatory_identifier_label.max' => 'The identifier label may not exceed 30 characters.',
            'signatory_identifier.max' => 'The identifier may not exceed 50 characters.',
            'signature.file' => 'The signature must be a valid uploaded file.',
            'signature.max' => 'The signature may not be larger than 2 MB.',
            'signature.mimes' => 'The signature must be a PNG, JPG, or JPEG image.',
            'signature.mimetypes' => 'The signature content type is not allowed.',
            'stamp.file' => 'The stamp must be a valid uploaded file.',
            'stamp.max' => 'The stamp may not be larger than 2 MB.',
            'stamp.mimes' => 'The stamp must be a PNG, JPG, or JPEG image.',
            'stamp.mimetypes' => 'The stamp content type is not allowed.',
            'remove_stamp.boolean' => 'The remove stamp flag is not valid.',
        ];
    }
}
