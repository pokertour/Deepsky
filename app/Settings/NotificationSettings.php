<?php

namespace App\Settings;

use Carbon\CarbonInterface;
use Spatie\LaravelSettings\Settings;

class NotificationSettings extends Settings
{
    public bool $telegram_enabled;

    public ?string $telegram_bot_token;

    public ?string $telegram_chat_id;

    public bool $gotify_enabled;

    public ?string $gotify_url;

    public ?string $gotify_token;

    public bool $ntfy_enabled;

    public ?string $ntfy_url;

    public ?string $ntfy_topic;

    public bool $mail_enabled;

    public ?string $mail_recipient;

    public bool $alert_daily_conditions;

    public bool $alert_best_targets;

    public bool $alert_weekly_report;

    public bool $alert_task_failures;

    public bool $alert_security_events;

    public bool $alert_every_login;

    public string $locale;

    public int $daily_min_score;

    public string $daily_time;

    public string $weekly_time;

    public static function group(): string
    {
        return 'notifications';
    }

    public static function encrypted(): array
    {
        return [
            'telegram_bot_token',
            'gotify_token',
        ];
    }

    public function telegramReady(): bool
    {
        return $this->telegram_enabled && filled($this->telegram_bot_token) && filled($this->telegram_chat_id);
    }

    public function gotifyReady(): bool
    {
        return $this->gotify_enabled && filled($this->gotify_url) && filled($this->gotify_token);
    }

    public function ntfyReady(): bool
    {
        return $this->ntfy_enabled && filled($this->ntfy_topic);
    }

    public function mailReady(): bool
    {
        return $this->mail_enabled && filled($this->mail_recipient);
    }

    public function hasReadyChannel(): bool
    {
        return $this->telegramReady() || $this->gotifyReady() || $this->ntfyReady() || $this->mailReady();
    }

    public function wantsDailyDigest(): bool
    {
        return $this->alert_daily_conditions || $this->alert_best_targets;
    }

    public function isDailyDigestDue(): bool
    {
        return $this->localNow()->format('H:i') === $this->daily_time;
    }

    public function isWeeklyReportDue(): bool
    {
        $now = $this->localNow();

        return $now->isMonday() && $now->format('H:i') === $this->weekly_time;
    }

    private function localNow(): CarbonInterface
    {
        return now(config('nightscope.notifications.timezone', 'Europe/Paris'));
    }
}
