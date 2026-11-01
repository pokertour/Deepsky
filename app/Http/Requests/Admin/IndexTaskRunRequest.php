<?php

namespace App\Http\Requests\Admin;

use App\Enums\TaskRunStatus;
use App\Enums\TaskRunType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTaskRunRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(TaskRunType::class)],
            'status' => ['nullable', Rule::enum(TaskRunStatus::class)],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
