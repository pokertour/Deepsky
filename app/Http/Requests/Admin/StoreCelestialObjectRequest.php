<?php

namespace App\Http\Requests\Admin;

use App\Models\CelestialObject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCelestialObjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $celestialObject = $this->route('celestial_object');

        return [
            'catalog' => ['required', 'string', 'max:20'],
            'catalog_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique((new CelestialObject)->getTable(), 'catalog_number')
                    ->where(fn ($query) => $query->where('catalog', $this->string('catalog')->upper()->toString()))
                    ->ignore($celestialObject),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'alternative_names_text' => ['nullable', 'string', 'max:2000'],
            'type' => ['nullable', 'string', 'max:80'],
            'constellation' => ['nullable', 'string', 'max:80'],
            'ra_deg' => ['required', 'numeric', 'gte:0', 'lt:360'],
            'dec_deg' => ['required', 'numeric', 'between:-90,90'],
            'magnitude' => ['nullable', 'numeric', 'between:-40,40'],
            'surface_brightness' => ['nullable', 'numeric', 'between:-40,100'],
            'angular_width_arcmin' => ['nullable', 'numeric', 'gt:0', 'max:21600'],
            'angular_height_arcmin' => ['nullable', 'numeric', 'gt:0', 'max:21600'],
            'distance_ly' => ['nullable', 'numeric', 'gte:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'catalog' => $this->string('catalog')->squish()->upper()->toString(),
            'catalog_number' => $this->string('catalog_number')->squish()->upper()->toString(),
        ]);
    }
}
