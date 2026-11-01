<?php

namespace App\Notifications;

use App\Data\Notifications\NightScopeDigest;
use App\Notifications\Channels\GotifyChannel;
use App\Notifications\Channels\NtfyChannel;
use App\Notifications\Channels\TelegramChannel;
use App\Settings\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NightScopeDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private NightScopeDigest $digest,
        private NotificationSettings $settings,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($this->settings->telegramReady()) {
            $channels[] = TelegramChannel::class;
        }

        if ($this->settings->gotifyReady()) {
            $channels[] = GotifyChannel::class;
        }

        if ($this->settings->ntfyReady()) {
            $channels[] = NtfyChannel::class;
        }

        if ($this->settings->mailReady()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** @return array{text: string} */
    public function toTelegram(object $notifiable): array
    {
        return [
            'text' => $this->digest->telegramHtml(),
        ];
    }

    /** @return array{title: string, message: string} */
    public function toGotify(object $notifiable): array
    {
        return [
            'title' => $this->digest->title,
            'message' => $this->digest->body(),
        ];
    }

    /** @return array{title: string, message: string, tags: string} */
    public function toNtfy(object $notifiable): array
    {
        return [
            'title' => $this->digest->title,
            'message' => $this->digest->body(),
            'tags' => $this->digest->ntfyTags,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->digest->title)
            ->line($this->digest->body());
    }
}
