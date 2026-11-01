<?php

namespace App\Http\Requests\Admin;

use App\Settings\NotificationSettings;
use App\Support\Locales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        $settings = app(NotificationSettings::class);

        return [
            'telegram_enabled' => ['sometimes', 'boolean'],
            'telegram_bot_token' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->boolean('telegram_enabled') && blank($settings->telegram_bot_token)),
            ],
            'telegram_chat_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->boolean('telegram_enabled')),
            ],
            'gotify_enabled' => ['sometimes', 'boolean'],
            'gotify_url' => [
                'nullable',
                'url',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->boolean('gotify_enabled')),
            ],
            'gotify_token' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->boolean('gotify_enabled') && blank($settings->gotify_token)),
            ],
            'ntfy_enabled' => ['sometimes', 'boolean'],
            'ntfy_url' => ['nullable', 'url', 'max:255'],
            'ntfy_topic' => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf(fn (): bool => $this->boolean('ntfy_enabled')),
            ],
            'mail_enabled' => ['sometimes', 'boolean'],
            'mail_recipient' => [
                'nullable',
                'email',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->boolean('mail_enabled')),
            ],
            'alert_daily_conditions' => ['sometimes', 'boolean'],
            'alert_best_targets' => ['sometimes', 'boolean'],
            'alert_weekly_report' => ['sometimes', 'boolean'],
            'alert_task_failures' => ['sometimes', 'boolean'],
            'alert_security_events' => ['sometimes', 'boolean'],
            'alert_every_login' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', Rule::in(Locales::codes())],
            'daily_min_score' => ['required', 'integer', 'between:0,100'],
            'daily_time' => ['required', 'date_format:H:i'],
            'weekly_time' => ['required', 'date_format:H:i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'telegram_enabled' => $this->boolean('telegram_enabled'),
            'gotify_enabled' => $this->boolean('gotify_enabled'),
            'ntfy_enabled' => $this->boolean('ntfy_enabled'),
            'mail_enabled' => $this->boolean('mail_enabled'),
            'alert_daily_conditions' => $this->boolean('alert_daily_conditions'),
            'alert_best_targets' => $this->boolean('alert_best_targets'),
            'alert_weekly_report' => $this->boolean('alert_weekly_report'),
            'alert_task_failures' => $this->boolean('alert_task_failures'),
            'alert_security_events' => $this->boolean('alert_security_events'),
            'alert_every_login' => $this->boolean('alert_every_login'),
        ]);
    }
}
