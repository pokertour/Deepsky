<?php

namespace App\Notifications\Channels;

use App\Settings\NotificationSettings;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        $settings = app(NotificationSettings::class);

        if (! $settings->telegramReady()) {
            return;
        }

        $message = $notification->toTelegram($notifiable);

        Http::asForm()
            ->timeout(10)
            ->post('https://api.telegram.org/bot'.$settings->telegram_bot_token.'/sendMessage', [
                'chat_id' => $settings->telegram_chat_id,
                'text' => $message['text'],
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ])
            ->throw();
    }
}
