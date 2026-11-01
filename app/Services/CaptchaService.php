<?php

namespace App\Services;

use App\Enums\CaptchaProvider;
use App\Settings\IntegrationSettings;
use Illuminate\Support\Facades\Http;
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
        $hostnames = config('turnstile.hostnames', []);
        if (app()->isProduction()) {
            $hostnames = array_values(array_diff($hostnames, ['localhost', '127.0.0.1', '::1']));
        }

        if (! is_string($token) || blank($token) || blank($this->siteKey()) || blank($secret)) {
            return false;
        }

        if ($provider === CaptchaProvider::Turnstile
            && (strlen($token) > 2048 || blank($action) || $hostnames === [])) {
            return false;
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
        } catch (Throwable) {
            return false;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            return false;
        }

        return $provider !== CaptchaProvider::Turnstile
            || ($response->json('action') === $action && in_array($response->json('hostname'), $hostnames, true));
    }
}
