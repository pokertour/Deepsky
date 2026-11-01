<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Dernière version publiée sur GitHub, comparée à la version installée
 * (APP_VERSION, injectée au build de l'image Docker).
 */
class ReleaseChecker
{
    private const string CACHE_KEY = 'deepsky:latest-release';

    public function currentVersion(): string
    {
        return (string) config('app.version', 'local-dev');
    }

    public function repository(): string
    {
        return (string) config('app.repository', 'pokertour/Deepsky');
    }

    /**
     * Dernière release connue, interrogée sur GitHub si elle n'est pas en cache.
     *
     * @return array{tag: ?string, name: ?string, url: ?string, published_at: ?string, notes: ?string, checked_at: string}|null
     */
    public function latest(bool $refresh = false): ?array
    {
        if (! $refresh && Cache::has(self::CACHE_KEY)) {
            return Cache::get(self::CACHE_KEY);
        }

        return $this->fetch() ?? Cache::get(self::CACHE_KEY);
    }

    /** Lecture du cache uniquement : utilisable à chaque page sans appel réseau. */
    public function cached(): ?array
    {
        return Cache::get(self::CACHE_KEY);
    }

    /** Faux pour une version de développement (local-dev), qu'on ne compare pas aux releases. */
    public function isComparable(): bool
    {
        return $this->normalize($this->currentVersion()) !== null;
    }

    public function isUpdateAvailable(?array $latest = null): bool
    {
        $latestVersion = $this->normalize(($latest ?? $this->cached())['tag'] ?? null);
        $currentVersion = $this->normalize($this->currentVersion());

        return $latestVersion !== null && $currentVersion !== null && version_compare($latestVersion, $currentVersion, '>');
    }

    /**
     * @return array{tag: ?string, name: ?string, url: ?string, published_at: ?string, notes: ?string, checked_at: string}|null
     *                                                                                                                          null si GitHub est injoignable (le cache précédent est conservé)
     */
    private function fetch(): ?array
    {
        try {
            $response = Http::timeout(6)->connectTimeout(4)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'Deepsky/'.$this->currentVersion()])
                ->get('https://api.github.com/repos/'.$this->repository().'/releases/latest');
        } catch (Throwable) {
            return null;
        }

        if ($response->status() === 404) {
            $release = ['tag' => null, 'name' => null, 'url' => null, 'published_at' => null, 'notes' => null, 'checked_at' => now()->toIso8601String()];
        } elseif ($response->successful()) {
            $release = [
                'tag' => $response->json('tag_name'),
                'name' => $response->json('name'),
                'url' => $response->json('html_url'),
                'published_at' => $response->json('published_at'),
                'notes' => $response->json('body'),
                'checked_at' => now()->toIso8601String(),
            ];
        } else {
            return null;
        }

        Cache::put(self::CACHE_KEY, $release, now()->addHours(12));

        return $release;
    }

    private function normalize(?string $version): ?string
    {
        return is_string($version) && preg_match('/^v?(\d+\.\d+\.\d+)$/', trim($version), $match) ? $match[1] : null;
    }
}
