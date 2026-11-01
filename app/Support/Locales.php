<?php

namespace App\Support;

class Locales
{
    /** @return array<string, string> code => nom affiché dans sa propre langue */
    public static function all(): array
    {
        return config('app.supported_locales', ['fr' => 'Français']);
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::all());
    }

    /** Langue par défaut : la première proposée (config('app.locale') suit la langue de la requête). */
    public static function default(): string
    {
        return array_key_first(self::all()) ?? 'fr';
    }

    public static function isDefault(?string $locale = null): bool
    {
        return ($locale ?? app()->getLocale()) === self::default();
    }

    /**
     * URL de la page courante dans une langue : la langue par défaut garde l'URL
     * sans paramètre (canonique), les autres ajoutent ?lang=.
     *
     * @param  array<string, mixed>  $query
     */
    public static function url(string $locale, ?string $baseUrl = null, array $query = []): string
    {
        $query = array_filter([...$query, 'lang' => self::isDefault($locale) ? null : $locale], fn ($value): bool => filled($value));
        ksort($query);

        return ($baseUrl ?? url()->current()).($query ? '?'.http_build_query($query) : '');
    }

    /** Lien du sélecteur : conserve les filtres de la page et force la langue choisie. */
    public static function switchUrl(string $locale): string
    {
        return request()->fullUrlWithQuery(['lang' => $locale]);
    }
}
