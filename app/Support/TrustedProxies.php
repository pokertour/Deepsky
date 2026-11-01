<?php

namespace App\Support;

/**
 * Convertit TRUSTED_PROXIES pour TrustProxies. « * » (faire confiance au proxy
 * appelant, typique derrière Pangolin/Traefik dans Docker) doit rester une chaîne :
 * dans un tableau, Symfony le prendrait pour une adresse IP et n'accepterait
 * aucun proxy, si bien que tous les visiteurs auraient l'IP du proxy.
 */
class TrustedProxies
{
    /**
     * @return list<string>|string|null
     */
    public static function from(?string $proxies): array|string|null
    {
        $entries = array_values(array_filter(array_map('trim', explode(',', (string) $proxies))));

        return match (true) {
            $entries === [] => null,
            $entries === ['*'], $entries === ['**'] => $entries[0],
            default => $entries,
        };
    }
}
