<?php

namespace App\Support;

use App\Settings\IntegrationSettings;
use Throwable;

/**
 * Mesure d'audience Umami des pages publiques, réglée dans l'administration
 * (aucun identifiant en dur : chaque installation choisit sa propre instance).
 */
class Analytics
{
    /** @return array{script: string, website_id: string, origin: string}|null */
    public static function umami(): ?array
    {
        try {
            $settings = app(IntegrationSettings::class);
            $script = $settings->umami_script_url;
            $websiteId = $settings->umami_website_id;
        } catch (Throwable) {
            return null;
        }

        $parts = is_string($script) ? parse_url($script) : false;

        if (blank($websiteId) || ! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || blank($parts['host'] ?? null)) {
            return null;
        }

        return [
            'script' => $script,
            'website_id' => $websiteId,
            'origin' => 'https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''),
        ];
    }
}
