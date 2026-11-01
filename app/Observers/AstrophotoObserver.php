<?php

namespace App\Observers;

use App\Jobs\EnrichCelestialDistanceJob;
use App\Mail\NewAstrophotoPublished;
use App\Models\Astrophoto;
use App\Models\NewsletterSubscriber;
use App\Services\GalleryFilterOptions;
use App\Support\AcquisitionData;
use Illuminate\Support\Facades\Mail;

class AstrophotoObserver
{
    public function saving(Astrophoto $astrophoto): void
    {
        if (filled($astrophoto->acquisition_sessions)) {
            $astrophoto->exposure = (string) AcquisitionData::integrationSeconds($astrophoto->acquisition_sessions);
        }
    }

    public function created(Astrophoto $astrophoto): void
    {
        $this->notifyPublication($astrophoto);
    }

    public function updated(Astrophoto $astrophoto): void
    {
        if ($astrophoto->wasChanged('is_published')) {
            $this->notifyPublication($astrophoto);
        }
    }

    /**
     * Pas sur « updated » : increment() des vues et likes le déclenche à chaque visite.
     */
    public function saved(Astrophoto $astrophoto): void
    {
        app(GalleryFilterOptions::class)->forget();

        if ($astrophoto->wasRecentlyCreated || $astrophoto->wasChanged('celestial_object_id')) {
            $this->enrichCelestialDistance($astrophoto);
        }
    }

    public function deleted(): void
    {
        app(GalleryFilterOptions::class)->forget();
    }

    private function enrichCelestialDistance(Astrophoto $astrophoto): void
    {
        $celestialObject = $astrophoto->celestialObject;
        $recheckAfter = now()->subDays((int) config('nightscope.distances.recheck_days', 30));

        if ($celestialObject === null || $celestialObject->distance_ly !== null
            || $celestialObject->distance_checked_at?->isAfter($recheckAfter)) {
            return;
        }

        EnrichCelestialDistanceJob::dispatch($celestialObject)->afterCommit();
    }

    private function notifyPublication(Astrophoto $astrophoto): void
    {
        if (! $astrophoto->is_published || $astrophoto->newsletter_notified_at !== null) {
            return;
        }

        if (Astrophoto::query()->whereKey($astrophoto->id)->whereNull('newsletter_notified_at')
            ->update(['newsletter_notified_at' => now()]) !== 1) {
            return;
        }

        NewsletterSubscriber::confirmed()
            ->lazyById()->each(function (NewsletterSubscriber $subscriber) use ($astrophoto): void {
                Mail::to($subscriber->email)->locale($subscriber->locale)->queue((new NewAstrophotoPublished($astrophoto, $subscriber))->afterCommit());
            });
    }
}
