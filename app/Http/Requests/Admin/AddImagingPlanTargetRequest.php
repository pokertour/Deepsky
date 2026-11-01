<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class AddImagingPlanTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['planned_start', 'planned_end'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /** @return array<string, list<Exists|string>> */
    public function rules(): array
    {
        return [
            'celestial_object_id' => ['required', 'integer', Rule::exists('celestial_objects', 'id')],
            'planned_start' => ['nullable', 'date_format:H:i'],
            'planned_end' => ['nullable', 'date_format:H:i', 'required_with:planned_start'],
        ];
    }
}
