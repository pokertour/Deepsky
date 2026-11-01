<?php

namespace App\Http\Requests\Admin;

use App\Enums\CelestialObjectType;
use App\Models\EquipmentSetup;
use App\Models\ObservingLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NightScopeTonightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('types') && ! is_array($this->input('types'))) {
            $this->merge(['types' => []]);
        }

        $this->merge([
            'never_photographed' => $this->boolean('never_photographed'),
            'favorites_only' => $this->boolean('favorites_only'),
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'observing_location_id' => ['nullable', 'integer', Rule::exists(ObservingLocation::class, 'id')],
            'equipment_setup_id' => ['nullable', 'integer', Rule::exists(EquipmentSetup::class, 'id')],
            'favorites_only' => ['sometimes', 'boolean'],
            'types' => ['sometimes', 'array'],
            'types.*' => ['string', Rule::in(CelestialObjectType::values())],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('nightscope.tonight.recommendation_max', 48)],
            'never_photographed' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<string> */
    public function objectTypes(): array
    {
        if (! $this->has('types')) {
            return (array) config('nightscope.tonight.default_types', CelestialObjectType::values());
        }

        $types = array_values(array_unique($this->validated('types') ?? []));

        return $types !== []
            ? $types
            : (array) config('nightscope.tonight.default_types', CelestialObjectType::values());
    }

    public function recommendationLimit(): ?int
    {
        $limit = $this->validated('limit') ?? null;

        return $limit === null ? null : (int) $limit;
    }

    public function neverPhotographedOnly(): bool
    {
        return (bool) ($this->validated('never_photographed') ?? false);
    }
}
