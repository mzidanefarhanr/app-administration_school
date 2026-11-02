<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLetterHeaderRequest extends FormRequest
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
        $maxImageKb = (int) config('letters.document.max_image_kb');

        return [
            'expected_version' => ['nullable', 'integer', 'min:0'],
            'government_line' => ['nullable', 'string', 'max:150'],
            'school_name' => ['nullable', 'string', 'max:150'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'contact_line' => ['nullable', 'string', 'max:150'],
            'place_of_issue' => ['nullable', 'string', 'max:100'],
            'logo' => [
                'nullable',
                'file',
                'max:'.$maxImageKb,
                'mimes:png,jpg,jpeg',
                'mimetypes:image/png,image/jpeg',
            ],
            'remove_logo' => ['nullable', 'boolean'],
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
            'government_line.max' => 'The government line may not exceed 150 characters.',
            'school_name.max' => 'The school name may not exceed 150 characters.',
            'address_line.max' => 'The address may not exceed 255 characters.',
            'contact_line.max' => 'The contact line may not exceed 150 characters.',
            'place_of_issue.max' => 'The place of issue may not exceed 100 characters.',
            'logo.file' => 'The logo must be a valid uploaded file.',
            'logo.max' => 'The logo may not be larger than 2 MB.',
            'logo.mimes' => 'The logo must be a PNG, JPG, or JPEG image.',
            'logo.mimetypes' => 'The logo content type is not allowed.',
            'remove_logo.boolean' => 'The remove logo flag is not valid.',
        ];
    }
}
