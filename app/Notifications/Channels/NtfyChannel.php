<?php

namespace App\Notifications\Channels;

use App\Settings\NotificationSettings;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class NtfyChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toNtfy')) {
            return;
        }

        $settings = app(NotificationSettings::class);

        if (! $settings->ntfyReady()) {
            return;
        }

        $message = $notification->toNtfy($notifiable);
        $endpoint = rtrim((string) ($settings->ntfy_url ?: 'https://ntfy.sh'), '/').'/'.$settings->ntfy_topic;

        Http::withHeaders([
            'Title' => $message['title'],
            'Priority' => '3',
            'Tags' => $message['tags'],
        ])
            ->timeout(10)
            ->withBody($message['message'], 'text/plain')
            ->post($endpoint)
            ->throw();
    }
}
