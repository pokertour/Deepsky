<?php

namespace App\Settings;

use App\Enums\CaptchaProvider;
use Spatie\LaravelSettings\Settings;
use Spatie\LaravelSettings\SettingsCasts\EnumCast;

class IntegrationSettings extends Settings
{
    public ?string $astrometry_api_key;

    public ?string $astrobin_api_key;

    public ?string $astrobin_api_secret;

    public ?string $gemini_api_key;

    public string $gemini_model;

    public CaptchaProvider $captcha_provider;

    public ?string $captcha_site_key;

    public ?string $captcha_secret_key;

    public ?string $umami_script_url;

    public ?string $umami_website_id;

    public static function group(): string
    {
        return 'integrations';
    }

    public static function casts(): array
    {
        return [
            'captcha_provider' => new EnumCast(CaptchaProvider::class),
        ];
    }

    public static function encrypted(): array
    {
        return [
            'astrometry_api_key',
            'astrobin_api_key',
            'astrobin_api_secret',
            'gemini_api_key',
            'captcha_secret_key',
        ];
    }
}
