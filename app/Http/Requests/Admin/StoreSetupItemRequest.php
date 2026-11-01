<?php

namespace App\Http\Requests\Admin;

use App\Enums\SetupItemType;
use App\Support\Equipment\SetupItemSpecificationSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StoreSetupItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $specKeys = SetupItemSpecificationSchema::keysFor($this->string('type')->toString());

        return [
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(SetupItemType::class)],
            'specs' => $specKeys === []
                ? ['nullable', 'prohibited']
                : ['nullable', 'array:'.implode(',', $specKeys)],
            ...SetupItemSpecificationSchema::rulesFor($this->string('type')->toString()),
        ];
    }

    protected function prepareForValidation(): void
    {
        $specKeys = SetupItemSpecificationSchema::keysFor($this->string('type')->toString());
        $specs = Arr::only((array) $this->input('specs', []), $specKeys);

        $this->merge(['specs' => $specs === [] ? null : $specs]);
    }
}
