<?php

namespace App\Http\Requests\Admin;

use App\Enums\ConversionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAstrophotoPublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return ['is_published' => ['required', 'boolean']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $photo = $this->route('astrophoto');

            if ($this->boolean('is_published') && ! $photo->is_published && $photo->conversion_status !== ConversionStatus::Ready) {
                $validator->errors()->add('is_published', __('La photo pourra être publiée lorsque toutes les images seront prêtes.'));
            }
        }];
    }
}
