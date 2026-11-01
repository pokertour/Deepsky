<?php

namespace App\Console\Commands;

use App\Services\Notifications\NightScopeDigestBuilder;
use App\Services\Notifications\NightScopeNotificationDispatcher;
use App\Settings\NotificationSettings;
use Illuminate\Console\Command;

class SendNightScopeWeeklyNotifications extends Command
{
    protected $signature = 'nightscope:notify-weekly {--force : Ignore deduplication cache}';

    protected $description = 'Send NightScope weekly activity digest';

    public function handle(
        NightScopeDigestBuilder $builder,
        NightScopeNotificationDispatcher $dispatcher,
        NotificationSettings $settings,
    ): int {
        if (! $this->option('force') && ! $settings->isWeeklyReportDue()) {
            return self::SUCCESS;
        }

        if (! $settings->hasReadyChannel() || ! $settings->alert_weekly_report) {
            return self::SUCCESS;
        }

        $digest = $builder->weekly();

        if ($digest === null) {
            $this->info('Rapport hebdomadaire désactivé.');

            return self::SUCCESS;
        }

        if (! $dispatcher->send($digest, (bool) $this->option('force'))) {
            $this->warn('Notification ignorée (déjà envoyée).');

            return self::SUCCESS;
        }

        $this->info('Rapport hebdomadaire envoyé.');

        return self::SUCCESS;
    }
}
