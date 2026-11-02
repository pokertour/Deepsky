<?php

namespace App\Services;

use App\Enums\CaptchaProvider;
use App\Settings\IntegrationSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CaptchaService
{
    public function __construct(private IntegrationSettings $settings) {}

    public function isEnabled(): bool
    {
        return $this->provider() !== CaptchaProvider::None;
    }

    public function provider(): CaptchaProvider
    {
        return $this->settings->captcha_provider;
    }

    public function siteKey(): ?string
    {
        return $this->settings->captcha_site_key;
    }

    public function verify(mixed $token, ?string $ip = null, ?string $action = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $provider = $this->provider();
        $secret = $this->settings->captcha_secret_key;
        $hostnames = $this->allowedHostnames();

        $skipped = match (true) {
            blank($this->siteKey()) || blank($secret) => 'clé de site ou clé secrète manquante dans les paramètres',
            ! is_string($token) || blank($token) => 'aucun jeton reçu avec le formulaire',
            $provider === CaptchaProvider::Turnstile && strlen($token) > 2048 => 'jeton trop long',
            $provider === CaptchaProvider::Turnstile && blank($action) => 'action attendue manquante',
            $provider === CaptchaProvider::Turnstile && $hostnames === [] => 'aucun domaine autorisé (TURNSTILE_HOSTNAMES)',
            default => null,
        };

        if ($skipped !== null) {
            return $this->reject($skipped);
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->connectTimeout(5)
                ->post($provider->verifyUrl(), [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (Throwable $exception) {
            return $this->reject('Siteverify injoignable : '.$exception->getMessage());
        }

        if (! $response->successful() || $response->json('success') !== true) {
            return $this->reject('jeton refusé par '.$provider->getLabel(), (array) $response->json('error-codes', []));
        }

        if ($provider === CaptchaProvider::Turnstile && $response->json('action') !== $action) {
            return $this->reject('action inattendue : '.$response->json('action'));
        }

        if ($provider === CaptchaProvider::Turnstile && ! in_array($response->json('hostname'), $hostnames, true)) {
            return $this->reject('domaine non autorisé : '.$response->json('hostname'));
        }

        return true;
    }

    /**
     * Vérifie la clé secrète enregistrée en appelant Siteverify avec un jeton factice :
     * une clé valide renvoie « jeton invalide », une mauvaise clé « clé secrète invalide ».
     *
     * @return array{ok: bool, message: string}
     */
    public function testSecret(): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => __('Aucun captcha n’est activé.')];
        }

        if (blank($this->settings->captcha_secret_key)) {
            return ['ok' => false, 'message' => __('Aucune clé secrète n’est enregistrée.')];
        }

        try {
            $response = Http::asForm()->timeout(10)->connectTimeout(5)->post($this->provider()->verifyUrl(), [
                'secret' => $this->settings->captcha_secret_key,
                'response' => 'deepsky-secret-check',
            ]);
        } catch (Throwable) {
            return ['ok' => false, 'message' => __(':provider ne répond pas : réessaie plus tard.', ['provider' => $this->provider()->getLabel()])];
        }

        $errors = (array) $response->json('error-codes', []);

        if (array_intersect($errors, ['invalid-input-secret', 'missing-input-secret', 'sitekey-secret-mismatch']) !== []) {
            return ['ok' => false, 'message' => __('Clé secrète refusée par :provider : vérifie qu’elle correspond bien au widget.', ['provider' => $this->provider()->getLabel()])];
        }

        if (! $response->successful() && $errors === []) {
            return ['ok' => false, 'message' => __(':provider a répondu avec une erreur HTTP :status.', ['provider' => $this->provider()->getLabel(), 'status' => $response->status()])];
        }

        return ['ok' => true, 'message' => __('Clé secrète acceptée par :provider : Siteverify fonctionne.', ['provider' => $this->provider()->getLabel()])];
    }

    /** @return list<string> */
    private function allowedHostnames(): array
    {
        $hostnames = config('turnstile.hostnames', []);

        return app()->isProduction()
            ? array_values(array_diff($hostnames, ['localhost', '127.0.0.1', '::1']))
            : $hostnames;
    }

    /** @param  list<string>  $errorCodes */
    private function reject(string $reason, array $errorCodes = []): bool
    {
        // Jamais le jeton ni la clé dans les journaux : seulement la raison du refus.
        Log::warning('Captcha newsletter refusé : '.$reason, ['provider' => $this->provider()->value, 'error_codes' => $errorCodes]);

        return false;
    }
}
