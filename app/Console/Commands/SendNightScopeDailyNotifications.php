<?php

namespace App\Console\Commands;

use App\Services\Notifications\NightScopeDigestBuilder;
use App\Services\Notifications\NightScopeNotificationDispatcher;
use App\Settings\NotificationSettings;
use Illuminate\Console\Command;

class SendNightScopeDailyNotifications extends Command
{
    protected $signature = 'nightscope:notify-daily {--force : Ignore deduplication cache}';

    protected $description = 'Send NightScope daily condition and target digests';

    public function handle(
        NightScopeDigestBuilder $builder,
        NightScopeNotificationDispatcher $dispatcher,
        NotificationSettings $settings,
    ): int {
        if (! $this->option('force') && ! $settings->isDailyDigestDue()) {
            return self::SUCCESS;
        }

        if (! $settings->hasReadyChannel() || ! $settings->wantsDailyDigest()) {
            return self::SUCCESS;
        }

        $digests = $builder->daily();

        if ($digests === []) {
            $this->info('Aucune alerte quotidienne à envoyer.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($digests as $digest) {
            if (! $dispatcher->send($digest, (bool) $this->option('force'))) {
                continue;
            }

            $sent++;
            $this->info('Alerte quotidienne envoyée : '.$digest->title);
        }

        if ($sent === 0) {
            $this->warn('Notification ignorée (déjà envoyée).');
        }

        return self::SUCCESS;
    }
}
