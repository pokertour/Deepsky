<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class PlannerDraftExcludeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, list<Exists|string>> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'celestial_object_id' => ['required', 'integer', Rule::exists('celestial_objects', 'id')],
        ];
    }
}
