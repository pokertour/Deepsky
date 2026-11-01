<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('notifications.telegram_enabled', false);
        $this->migrator->addEncrypted('notifications.telegram_bot_token', null);
        $this->migrator->add('notifications.telegram_chat_id', null);
        $this->migrator->add('notifications.gotify_enabled', false);
        $this->migrator->add('notifications.gotify_url', null);
        $this->migrator->addEncrypted('notifications.gotify_token', null);
        $this->migrator->add('notifications.ntfy_enabled', false);
        $this->migrator->add('notifications.ntfy_url', 'https://ntfy.sh');
        $this->migrator->add('notifications.ntfy_topic', null);
        $this->migrator->add('notifications.mail_enabled', false);
        $this->migrator->add('notifications.mail_recipient', null);
        $this->migrator->add('notifications.alert_daily_conditions', false);
        $this->migrator->add('notifications.alert_best_targets', false);
        $this->migrator->add('notifications.alert_weekly_report', false);
        $this->migrator->add('notifications.daily_min_score', 65);
        $this->migrator->add('notifications.daily_time', '16:00');
        $this->migrator->add('notifications.weekly_time', '09:00');
    }

    public function down(): void
    {
        foreach ([
            'telegram_enabled',
            'telegram_bot_token',
            'telegram_chat_id',
            'gotify_enabled',
            'gotify_url',
            'gotify_token',
            'ntfy_enabled',
            'ntfy_url',
            'ntfy_topic',
            'mail_enabled',
            'mail_recipient',
            'alert_daily_conditions',
            'alert_best_targets',
            'alert_weekly_report',
            'daily_min_score',
            'daily_time',
            'weekly_time',
        ] as $name) {
            $this->migrator->deleteIfExists('notifications.'.$name);
        }
    }
};
