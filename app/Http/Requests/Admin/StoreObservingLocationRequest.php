<?php

namespace App\Http\Requests\Admin;

use App\Enums\BortleClass;
use App\Rules\HorizonMaskRule;
use App\Rules\TimezoneMatchesLongitude;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreObservingLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'altitude' => ['nullable', 'numeric', 'between:-500,10000'],
            'bortle_class' => ['nullable', 'integer', Rule::enum(BortleClass::class)],
            'timezone' => ['required', 'timezone:all', new TimezoneMatchesLongitude],
            'is_default' => ['required', 'boolean'],
            'horizon_mask' => ['nullable', 'string', 'max:30000', new HorizonMaskRule],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('bortle_class') === '') {
            $this->merge(['bortle_class' => null]);
        }

        $this->merge(['is_default' => $this->boolean('is_default')]);
    }
}
