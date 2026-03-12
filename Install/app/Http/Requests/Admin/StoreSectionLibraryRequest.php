<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSectionLibraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_-]*$/', 'unique:sections_library,key'],
            'category' => ['required', 'string', 'max:120'],
            'schema_json' => ['nullable'],
            'schema_file' => ['nullable', 'file', 'mimetypes:application/json,text/plain', 'max:2048'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
