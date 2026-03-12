<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionLibraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'key' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('sections_library', 'key')->ignore($this->route('section')),
            ],
            'category' => ['sometimes', 'required', 'string', 'max:120'],
            'schema_json' => ['sometimes', 'nullable'],
            'schema_file' => ['nullable', 'file', 'mimetypes:application/json,text/plain', 'max:2048'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
