<?php

namespace App\Services\Security;

use App\Data\Notifications\NightScopeDigest;
use App\Enums\LoginEventType;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\Notifications\NightScopeNotificationDispatcher;
use App\Settings\NotificationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * Journal des connexions admin (affiché dans Sécurité) et alertes sur les canaux
 * de notification : chaque connexion (ou seulement celles depuis un nouvel
 * appareil, selon les réglages), compte verrouillé.
 */
class LoginActivityRecorder
{
    use Localizable;

    public function __construct(
        private NightScopeNotificationDispatcher $dispatcher,
        private NotificationSettings $settings,
    ) {}

    /**
     * Les numéros de version sont retirés : une mise à jour du navigateur ne doit
     * pas faire passer l'appareil pour nouveau.
     */
    public static function deviceHash(?string $userAgent): string
    {
        return hash('sha256', (string) preg_replace('/\d+/', '', (string) $userAgent));
    }

    public function record(LoginEventType $type, Request $request, ?User $user = null, ?string $email = null): LoginEvent
    {
        $deviceHash = self::deviceHash($request->userAgent());
        $isNewDevice = $type === LoginEventType::Succeeded && $user !== null
            && ! $user->loginEvents()->where('type', LoginEventType::Succeeded)->where('device_hash', $deviceHash)->exists();

        $event = LoginEvent::create([
            'user_id' => $user?->id,
            'email' => $email !== null ? mb_strtolower(mb_substr($email, 0, 255)) : $user?->email,
            'type' => $type,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'device_hash' => $deviceHash,
        ]);

        if ($isNewDevice) {
            $this->alert($event, __('🔐 Deepsky — connexion depuis un nouvel appareil'), __('Nouvelle connexion à l’administration'));
        } elseif ($type === LoginEventType::Succeeded && $this->settings->alert_every_login) {
            $this->alert($event, __('🔐 Deepsky — connexion à l’administration'), __('Connexion depuis un appareil déjà connu'));
        } elseif ($type === LoginEventType::LockedOut) {
            $this->alert($event, __('🚨 Deepsky — compte admin verrouillé'), __('Trop de tentatives : connexion bloquée temporairement'));
        }

        return $event;
    }

    private function alert(LoginEvent $event, string $title, string $headline): void
    {
        if (! $this->settings->alert_security_events) {
            return;
        }

        // Une alerte qui échoue (canal injoignable) ne doit jamais bloquer la connexion.
        try {
            $this->dispatcher->send($this->withLocale($this->settings->locale, fn (): NightScopeDigest => new NightScopeDigest(
                title: $title,
                lines: array_values(array_filter([
                    '<b>'.e($headline).'</b>',
                    $event->email ? __('Compte : :email', ['email' => e($event->email)]) : null,
                    __('Appareil : :device · IP :ip', ['device' => e($event->deviceLabel()), 'ip' => e((string) $event->ip_address)]),
                    __('Le :date.', ['date' => $event->created_at->timezone(config('admin.timezone'))->format(__('d/m/Y à H:i'))]),
                    __('Si ce n’est pas toi : :url', ['url' => route('admin.security.edit')]),
                ])),
                // Chaque connexion réussie est signalée ; les verrouillages répétés, une fois par heure.
                dedupeKey: $event->type === LoginEventType::Succeeded
                    ? 'security:login:'.$event->id
                    : 'security:'.$event->type->value.':'.$event->device_hash.':'.$event->ip_address.':'.now()->format('Y-m-d-H'),
                ntfyTags: 'lock',
            )));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
