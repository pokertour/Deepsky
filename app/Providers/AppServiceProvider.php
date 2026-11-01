<?php

namespace App\Providers;

use App\Contracts\Weather\WeatherProvider;
use App\Services\Admin\TaskRunRecorder;
use App\Services\Weather\CompositeWeatherProvider;
use App\Services\Weather\NullWeatherProvider;
use App\Services\Weather\OpenMeteoWeatherProvider;
use App\Settings\IntegrationSettings;
use App\Settings\MailSettings;
use App\Support\Locales;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WeatherProvider::class, function ($app): WeatherProvider {
            if (config('nightscope.weather.driver') !== 'open_meteo') {
                return $app->make(NullWeatherProvider::class);
            }

            return config('nightscope.weather.seven_timer.enabled', true)
                ? $app->make(CompositeWeatherProvider::class)
                : $app->make(OpenMeteoWeatherProvider::class);
        });

        $this->app->singleton(TaskRunRecorder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Number::useCurrency('EUR');
        Carbon::setLocale(Locales::default());
        // Les dates relatives et noms de mois suivent la langue de la requête ou de la notification.
        Event::listen(LocaleUpdated::class, fn (LocaleUpdated $event) => Carbon::setLocale($event->locale));
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
        $this->applyDatabaseSettings();

        // Sous Octane, boot() ne tourne qu'une fois par worker : on ré-applique
        // les settings à chaque requête pour prendre en compte les changements
        // faits dans l'admin sans redémarrer les workers.
        Event::listen(RequestReceived::class, fn () => $this->applyDatabaseSettings());

        // Même logique pour les workers de queue (envoi des newsletters) :
        // ré-application des settings avant chaque job.
        Event::listen(JobProcessing::class, fn () => $this->applyDatabaseSettings());

        $this->recordTaskHistory();
        $this->registerHealthChecks();
    }

    /**
     * /up (contrôle de santé Docker) répond 500 si la base ou le cache (Redis en
     * production) ne répondent plus, et pas seulement quand Laravel démarre.
     */
    private function registerHealthChecks(): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::select('select 1');
            cache()->put('health:last-check', now()->getTimestamp(), 60);
        });
    }

    /**
     * Historique admin > Tâches : commandes planifiées suivies et jobs de file.
     */
    private function recordTaskHistory(): void
    {
        Event::listen(CommandStarting::class, [TaskRunRecorder::class, 'commandStarting']);
        Event::listen(CommandFinished::class, [TaskRunRecorder::class, 'commandFinished']);
        Event::listen(JobProcessing::class, [TaskRunRecorder::class, 'jobProcessing']);
        Event::listen(JobProcessed::class, [TaskRunRecorder::class, 'jobProcessed']);
        Event::listen(JobReleasedAfterException::class, [TaskRunRecorder::class, 'jobReleasedAfterException']);
        Event::listen(JobFailed::class, [TaskRunRecorder::class, 'jobFailed']);
    }

    /**
     * Écrase la config .env avec les paramètres gérés dans l'administration.
     * Silencieux si la table settings n'existe pas encore (installation, tests).
     */
    private function applyDatabaseSettings(): void
    {
        if ($this->app->environment('testing')) {
            return;
        }

        try {
            $integrationSettings = app(IntegrationSettings::class);

            if (filled($integrationSettings->astrometry_api_key)) {
                config(['services.astrometry.key' => $integrationSettings->astrometry_api_key]);
            }

            $mailSettings = app(MailSettings::class);

            if (filled($mailSettings->host)) {
                // Des settings SMTP existent en base : elles font foi, même si
                // MAIL_MAILER est absent du .env (défaut "log").
                config(['mail.default' => 'smtp']);

                $smtpConfig = array_merge(
                    config('mail.mailers.smtp', []),
                    $mailSettings->toSmtpMailerConfig(),
                );

                if ($smtpConfig !== config('mail.mailers.smtp')) {
                    config([
                        'mail.mailers.smtp' => $smtpConfig,
                        'mail.from.address' => $mailSettings->from_address,
                        'mail.from.name' => $mailSettings->from_name,
                    ]);

                    // Le MailManager met en cache les transports déjà construits :
                    // purge pour que la nouvelle config SMTP soit prise en compte.
                    app('mail.manager')->purge('smtp');
                }
            }
        } catch (Throwable) {
            // Base non migrée ou settings absents : on garde la config .env.
        }
    }
}
