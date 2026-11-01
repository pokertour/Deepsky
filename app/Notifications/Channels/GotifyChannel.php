<?php

namespace App\Notifications\Channels;

use App\Settings\NotificationSettings;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class GotifyChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toGotify')) {
            return;
        }

        $settings = app(NotificationSettings::class);

        if (! $settings->gotifyReady()) {
            return;
        }

        $message = $notification->toGotify($notifiable);

        Http::withToken($settings->gotify_token)
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->post(rtrim((string) $settings->gotify_url, '/').'/message', [
                'title' => $message['title'],
                'message' => $message['message'],
                'priority' => 5,
                'extras' => [
                    'client::display' => [
                        'contentType' => 'text/markdown',
                    ],
                ],
            ])
            ->throw();
    }
}
