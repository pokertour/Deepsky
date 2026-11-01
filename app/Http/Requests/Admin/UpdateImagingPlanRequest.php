<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class UpdateImagingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('equipment_setup_id') === '') {
            $this->merge(['equipment_setup_id' => null]);
        }
    }

    /** @return array<string, list<Exists|string>> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'observing_location_id' => ['required', 'integer', Rule::exists('observing_locations', 'id')],
            'equipment_setup_id' => ['nullable', 'integer', Rule::exists('equipment_setups', 'id')],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
