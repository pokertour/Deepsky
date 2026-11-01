<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateImagingPlanTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'planned_start' => ['required', 'date_format:H:i'],
            'planned_end' => ['required', 'date_format:H:i'],
        ];
    }
}
