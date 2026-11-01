<?php

namespace App\Services\Notifications;

use App\Data\Notifications\NightScopeDigest;
use App\Notifications\NightScopeDigestNotification;
use App\Settings\NotificationSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Traits\Localizable;

class NightScopeNotificationDispatcher
{
    use Localizable;

    public function __construct(private NotificationSettings $settings) {}

    public function send(NightScopeDigest $digest, bool $force = false): bool
    {
        if (! $this->settings->hasReadyChannel()) {
            return false;
        }

        if (! $force && ! $this->shouldSend($digest->dedupeKey)) {
            return false;
        }

        Notification::route('nightscope', 'admin')
            ->notify((new NightScopeDigestNotification($digest, $this->settings))->locale($this->settings->locale));

        if (! $force) {
            Cache::put($this->cacheKey($digest->dedupeKey), true, now()->addDays(2));
        }

        return true;
    }

    public function sendTest(): void
    {
        $this->withLocale($this->settings->locale, fn () => Notification::route('nightscope', 'admin')
            ->notify((new NightScopeDigestNotification(
                new NightScopeDigest(
                    title: __('✅ Deepsky — test notifications'),
                    lines: [
                        __('🚀 <b>Canal NightScope opérationnel.</b>'),
                        __('Configure les alertes quotidiennes ou hebdomadaires dans les paramètres.'),
                    ],
                    dedupeKey: 'test:'.now()->format('Y-m-d-H-i-s'),
                    ntfyTags: 'white_check_mark,rocket',
                ),
                $this->settings,
            ))->locale($this->settings->locale)));
    }

    private function shouldSend(string $dedupeKey): bool
    {
        return ! Cache::has($this->cacheKey($dedupeKey));
    }

    private function cacheKey(string $dedupeKey): string
    {
        return 'nightscope:notifications:'.$dedupeKey;
    }
}
