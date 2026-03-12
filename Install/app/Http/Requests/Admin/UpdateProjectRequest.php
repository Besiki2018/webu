<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'owner_user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'is_public' => ['sometimes', 'boolean'],
            'template_id' => ['sometimes', 'nullable', 'integer', 'exists:templates,id'],
            'theme_preset' => ['sometimes', 'nullable', 'string', 'in:default,arctic,summer,fragrant,slate,feminine,forest,midnight,coral,mocha,ocean,ruby,luxury_minimal,corporate_clean,bold_startup,soft_pastel,dark_modern,creative_portfolio'],
        ];
    }
}
