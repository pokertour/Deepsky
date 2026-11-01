<?php

namespace App\Enums;

enum CaptchaProvider: string
{
    case None = 'none';
    case Turnstile = 'turnstile';
    case Hcaptcha = 'hcaptcha';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::None => __('Aucun'),
            self::Turnstile => 'Cloudflare Turnstile',
            self::Hcaptcha => 'hCaptcha',
        };
    }

    public function verifyUrl(): ?string
    {
        return match ($this) {
            self::None => null,
            self::Turnstile => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            self::Hcaptcha => 'https://api.hcaptcha.com/siteverify',
        };
    }

    public function scriptUrl(): ?string
    {
        return match ($this) {
            self::None => null,
            self::Turnstile => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            self::Hcaptcha => 'https://js.hcaptcha.com/1/api.js',
        };
    }

    public function responseFieldName(): ?string
    {
        return match ($this) {
            self::None => null,
            self::Turnstile => 'cf-turnstile-response',
            self::Hcaptcha => 'h-captcha-response',
        };
    }

    public function widgetClass(): ?string
    {
        return match ($this) {
            self::None => null,
            self::Turnstile => 'cf-turnstile',
            self::Hcaptcha => 'h-captcha',
        };
    }
}
