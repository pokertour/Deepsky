<?php

use App\Enums\ConversionStatus;
use App\Models\Astrophoto;
use App\Models\LoginEvent;
use App\Models\TaskRun;
use App\Settings\NotificationSettings;
use Illuminate\Support\Facades\Schedule;

Schedule::command('r2:warm-stats')->hourly()->withoutOverlapping();

Schedule::command('newsletter:purge-unconfirmed')->daily();

Schedule::command('nightscope:import-catalog --latest')
    ->monthlyOn(2, '04:20')
    ->withoutOverlapping(180)
    ->runInBackground()
    ->environments(['production']);

Schedule::command('nightscope:warm-weather')
    ->hourlyAt(15)
    ->withoutOverlapping(30)
    ->runInBackground()
    ->environments(['production']);

Schedule::command('nightscope:enrich-distances --limit='.(int) config('nightscope.distances.enrich_batch', 150))
    ->dailyAt('03:40')
    ->withoutOverlapping(120)
    ->runInBackground()
    ->environments(['production']);

// L'heure d'envoi est réglée dans l'admin : le planificateur ne lance la
// commande qu'à cette minute, sans process ni ligne d'historique le reste du temps.
Schedule::command('nightscope:notify-daily')
    ->everyMinute()
    ->when(fn (): bool => rescue(fn (): bool => app(NotificationSettings::class)->isDailyDigestDue(), false, report: false))
    ->withoutOverlapping(10)
    ->runInBackground()
    ->environments(['production']);

Schedule::command('nightscope:notify-weekly')
    ->everyMinute()
    ->when(fn (): bool => rescue(fn (): bool => app(NotificationSettings::class)->isWeeklyReportDue(), false, report: false))
    ->withoutOverlapping(10)
    ->runInBackground()
    ->environments(['production']);

Schedule::command('model:prune', ['--model' => [TaskRun::class, LoginEvent::class]])
    ->dailyAt('04:50')
    ->withoutOverlapping(30);

// Ne lance la commande (et ne crée une ligne d'historique) que si une photo due est publiable :
// une photo dont les conversions ne sont pas prêtes ne relance pas la commande chaque minute.
Schedule::command('astrophotos:publish-scheduled')
    ->everyMinute()
    ->when(fn (): bool => rescue(fn (): bool => Astrophoto::query()->dueForPublication()->with('media')->get()
        ->contains(fn (Astrophoto $photo): bool => $photo->conversion_status === ConversionStatus::Ready), false, report: false))
    ->withoutOverlapping(10);

// Rattrapage des distances des objets du champ (le job part déjà à chaque résolution).
Schedule::command('astrophotos:enrich-field-distances')
    ->dailyAt('04:05')
    ->withoutOverlapping(60)
    ->runInBackground()
    ->environments(['production']);

Schedule::command('maintenance:recover-processing')
    ->hourlyAt(35)
    ->withoutOverlapping(30);

// Les jobs échoués restent consultables un mois (php artisan queue:failed), puis sont purgés.
Schedule::command('queue:prune-failed', ['--hours' => 24 * 30])
    ->weeklyOn(1, '05:10')
    ->withoutOverlapping(30);
