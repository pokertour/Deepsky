<?php

namespace App\Http\Requests\Admin;

use App\Enums\MosaicDisplayMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;

class NightScopeTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['observing_location_id', 'equipment_setup_id'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /** @return array<string, list<Enum|Exists|string>> */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'observing_location_id' => ['nullable', 'integer', Rule::exists('observing_locations', 'id')],
            'equipment_setup_id' => ['nullable', 'integer', Rule::exists('equipment_setups', 'id')],
            'mosaic' => ['nullable', Rule::enum(MosaicDisplayMode::class)],
        ];
    }
}
