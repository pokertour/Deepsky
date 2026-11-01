<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCelestialWishlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'priority' => ['sometimes', 'integer', 'min:1', 'max:3'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
