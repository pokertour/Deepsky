<?php

namespace App\Models;

use App\Enums\TaskRunStatus;
use App\Enums\TaskRunType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class TaskRun extends Model
{
    use HasFactory, MassPrunable;

    protected $fillable = [
        'type',
        'name',
        'status',
        'queue',
        'job_uuid',
        'attempts',
        'subject',
        'message',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'type' => TaskRunType::class,
            'status' => TaskRunStatus::class,
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('started_at', '<', now()->subDays((int) config('admin.task_history.retention_days', 30)));
    }

    public function label(): string
    {
        return self::labelFor($this->name);
    }

    public static function labelFor(string $name): string
    {
        return match ($name) {
            'nightscope:warm-weather' => __('Préchauffage météo'),
            'nightscope:import-catalog' => __('Import du catalogue OpenNGC'),
            'nightscope:enrich-distances' => __('Distances SIMBAD (lot)'),
            'nightscope:notify-daily' => __('Alertes NightScope quotidiennes'),
            'nightscope:notify-weekly' => __('Alertes NightScope hebdomadaires'),
            'newsletter:purge-unconfirmed' => __('Purge des abonnés non confirmés'),
            'r2:warm-stats' => __('Statistiques de stockage R2'),
            'model:prune' => __('Purge des données anciennes'),
            'maintenance:recover-processing' => __('Rattrapage des traitements bloqués'),
            'astrophotos:publish-scheduled' => __('Publication programmée'),
            'astrophotos:enrich-field-distances' => __('Distances des objets du champ'),
            'astrophotos:resync-distances' => __('Resynchronisation de toutes les distances'),
            'App\Jobs\EnrichFieldDistancesJob' => __('Distances du champ (photo)'),
            'queue:prune-failed' => __('Purge des jobs échoués'),
            'App\Jobs\PerformAstrophotoConversionsJob' => __('Conversion d’images'),
            'App\Jobs\SolveAstrophotoJob' => __('Astrométrie'),
            'App\Jobs\EnrichCelestialDistanceJob' => __('Distance SIMBAD'),
            'App\Mail\NewAstrophotoPublished' => __('Email · nouvelle photo'),
            'App\Mail\ConfirmNewsletterSubscription' => __('Email · confirmation newsletter'),
            'App\Notifications\NightScopeDigestNotification' => __('Notification NightScope'),
            default => class_basename($name),
        };
    }

    public function formattedDuration(): ?string
    {
        if ($this->duration_ms === null) {
            return null;
        }

        return match (true) {
            $this->duration_ms < 1_000 => $this->duration_ms.' ms',
            $this->duration_ms < 60_000 => number_format($this->duration_ms / 1_000, 1, ',', ' ').' s',
            default => intdiv($this->duration_ms, 60_000).' min '.str_pad((string) intdiv($this->duration_ms % 60_000, 1_000), 2, '0', STR_PAD_LEFT).' s',
        };
    }
}
