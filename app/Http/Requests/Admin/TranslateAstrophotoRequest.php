<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TranslateAstrophotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255', 'required_without_all:description,object_description'],
            'description' => ['nullable', 'string', 'max:10000'],
            'object_description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['title.required_without_all' => __('Renseigne au moins le titre ou une description en français.')];
    }
}
