<?php

namespace App\Http\Requests\Admin;

class UpdateAstrophotoRequest extends StoreAstrophotoRequest
{
    public function rules(): array
    {
        return $this->rulesWithImage(['nullable']);
    }
}
