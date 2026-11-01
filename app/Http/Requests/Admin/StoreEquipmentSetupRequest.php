<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_default' => ['required', 'boolean'],
            'setup_item_ids' => ['nullable', 'array'],
            'setup_item_ids.*' => ['integer', 'distinct', 'exists:setup_items,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['nullable', 'string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_default' => $this->boolean('is_default')]);
    }
}
