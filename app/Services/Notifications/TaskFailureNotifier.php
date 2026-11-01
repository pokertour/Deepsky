<?php

namespace App\Services\Notifications;

use App\Data\Notifications\NightScopeDigest;
use App\Enums\TaskRunStatus;
use App\Models\TaskRun;
use App\Notifications\NightScopeDigestNotification;
use App\Settings\NotificationSettings;
use Illuminate\Support\Traits\Localizable;

/**
 * Prévient l'admin sur les canaux NightScope quand une tâche ou un job échoue,
 * au plus une fois par heure et par tâche (anti-doublon du dispatcher).
 */
class TaskFailureNotifier
{
    use Localizable;

    public function __construct(
        private NotificationSettings $settings,
        private NightScopeNotificationDispatcher $dispatcher,
    ) {}

    public function notify(TaskRun $run): bool
    {
        // L'alerte part elle-même en file : son échec ne doit pas en déclencher une autre.
        if ($run->status !== TaskRunStatus::Failed
            || $run->name === NightScopeDigestNotification::class
            || ! $this->settings->alert_task_failures) {
            return false;
        }

        return $this->dispatcher->send($this->withLocale($this->settings->locale, fn (): NightScopeDigest => new NightScopeDigest(
            title: __('⚠️ Deepsky — échec : :task', ['task' => $run->label()]),
            lines: array_values(array_filter([
                __('<b>:task</b> a échoué le :date.', ['task' => e($run->label()), 'date' => $run->started_at->format(__('d/m/Y à H:i'))]),
                $run->subject ? e($run->subject) : null,
                $run->message ? __('Erreur : :message', ['message' => e(str($run->message)->limit(300))]) : null,
                __('Historique : :url', ['url' => route('admin.tasks.index', ['status' => TaskRunStatus::Failed->value])]),
            ])),
            dedupeKey: 'task-failure:'.$run->name.':'.now()->format('Y-m-d-H'),
            ntfyTags: 'warning',
        )));
    }
}
