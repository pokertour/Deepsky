<?php

namespace App\Console\Commands;

use App\Models\NewsletterSubscriber;
use Illuminate\Console\Command;

class PurgeUnconfirmedNewsletterSubscribers extends Command
{
    protected $signature = 'newsletter:purge-unconfirmed {--days=7 : Ancienneté minimale (en jours) des inscriptions non confirmées à supprimer}';

    protected $description = 'Supprime les abonnés newsletter qui n\'ont pas confirmé leur inscription après le délai donné.';

    public function handle(): int
    {
        $deleted = NewsletterSubscriber::query()
            ->whereNull('confirmed_at')
            ->where(function ($query): void {
                $cutoff = now()->subDays((int) $this->option('days'));
                $query->where('confirmation_sent_at', '<=', $cutoff)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('confirmation_sent_at')->where('created_at', '<=', $cutoff));
            })
            ->delete();

        $this->components->info("{$deleted} inscription(s) non confirmée(s) supprimée(s).");

        return self::SUCCESS;
    }
}
