<?php

namespace App\Services;

use App\Mail\ConfirmNewsletterSubscription;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NewsletterConfirmationService
{
    public function send(NewsletterSubscriber $subscriber): bool
    {
        return DB::transaction(function () use ($subscriber): bool {
            $subscriber = NewsletterSubscriber::query()->lockForUpdate()->findOrFail($subscriber->id);

            if ($subscriber->isConfirmed() || $subscriber->confirmation_sent_at?->gt(now()->subMinutes(5))) {
                return false;
            }

            $subscriber->update([
                'confirmation_token' => $subscriber->confirmation_token ?? str()->random(40),
                'confirmation_sent_at' => now(),
            ]);

            Mail::to($subscriber->email)->locale($subscriber->locale)->queue((new ConfirmNewsletterSubscription($subscriber))->afterCommit());

            return true;
        });
    }
}
