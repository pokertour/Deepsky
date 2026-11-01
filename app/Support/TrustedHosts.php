<?php

namespace App\Support;

/**
 * Convertit TRUSTED_HOSTS en motifs pour TrustHosts. Symfony les utilise comme
 * expressions régulières non ancrées : « deepsky.nexus » accepterait aussi
 * « deepsky.nexus.attaquant.com » ou « deepskyXnexus ». Chaque nom d'hôte est
 * donc échappé et ancré ; une entrée commençant par « ^ » reste une regex libre.
 */
class TrustedHosts
{
    /**
     * @return list<string>
     */
    public static function patterns(?string $hosts): array
    {
        return collect(explode(',', (string) $hosts))
            ->map(fn (string $host): string => trim($host))
            ->filter()
            ->map(fn (string $host): string => str_starts_with($host, '^') ? $host : '^'.preg_quote($host).'$')
            ->values()
            ->all();
    }
}
