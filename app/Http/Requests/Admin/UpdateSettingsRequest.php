<?php

namespace App\Http\Requests\Admin;

use App\Enums\CaptchaProvider;
use App\Settings\IntegrationSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'astrometry_api_key' => ['nullable', 'string'],
            'gemini_api_key' => ['nullable', 'string', 'max:500'],
            'gemini_model' => ['sometimes', 'required', 'string', 'max:100', 'regex:/^gemini-[a-z0-9.-]+$/'],
            'captcha_provider' => ['required', Rule::enum(CaptchaProvider::class)],
            'captcha_site_key' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->string('captcha_provider')->value() !== CaptchaProvider::None->value
                    && blank(app(IntegrationSettings::class)->captcha_site_key)),
                'string',
            ],
            'captcha_secret_key' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->string('captcha_provider')->value() !== CaptchaProvider::None->value
                    && blank(app(IntegrationSettings::class)->captcha_secret_key)),
                'string',
            ],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string'],
            'scheme' => ['nullable', 'in:smtp,smtps,tls'],
            'from_address' => ['required', 'email'],
            'from_name' => ['required', 'string', 'max:255'],
        ];
    }
}
