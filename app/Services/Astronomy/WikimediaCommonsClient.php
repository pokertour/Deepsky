<?php

namespace App\Services\Astronomy;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class WikimediaCommonsClient
{
    /**
     * @return list<array{title: string, author: string, thumbUrl: string, pageUrl: string}>
     */
    public function searchImages(string $query, int $limit = 8): array
    {
        $cacheKey = 'commons:images:'.md5(strtolower($query).':'.$limit);

        try {
            return Cache::remember(
                $cacheKey,
                now()->addHours((int) config('nightscope.commons.cache_hours', 24)),
                fn (): array => $this->fetch($query, $limit),
            );
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @return list<array{title: string, author: string, thumbUrl: string, pageUrl: string}>
     */
    private function fetch(string $query, int $limit): array
    {
        $response = Http::withHeaders([
            'User-Agent' => (string) config('nightscope.commons.user_agent', 'DeepskyNightScope/1.0 (https://deepsky.local; nightscope)'),
            'Accept' => 'application/json',
        ])
            ->timeout((int) config('nightscope.commons.timeout', 12))
            ->connectTimeout((int) config('nightscope.commons.connect_timeout', 5))
            ->get((string) config('nightscope.commons.endpoint', 'https://commons.wikimedia.org/w/api.php'), [
                'action' => 'query',
                'format' => 'json',
                'origin' => '*',
                'generator' => 'search',
                'gsrsearch' => 'filetype:bitmap '.$query,
                'gsrnamespace' => 6,
                'gsrlimit' => max(1, min($limit, 20)),
                'prop' => 'imageinfo',
                'iiprop' => 'url|mime|extmetadata|size',
                'iiurlwidth' => 640,
            ])
            ->throw()
            ->json();

        $pages = $response['query']['pages'] ?? [];
        if (! is_array($pages) || $pages === []) {
            return [];
        }

        $results = [];

        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $info = $page['imageinfo'][0] ?? null;
            if (! is_array($info)) {
                continue;
            }

            $mime = (string) ($info['mime'] ?? '');
            if ($mime !== '' && ! str_starts_with($mime, 'image/')) {
                continue;
            }

            $thumb = $info['thumburl'] ?? $info['url'] ?? null;
            $pageUrl = $info['descriptionurl'] ?? null;
            if (! is_string($thumb) || $thumb === '' || ! is_string($pageUrl) || $pageUrl === '') {
                continue;
            }

            $meta = is_array($info['extmetadata'] ?? null) ? $info['extmetadata'] : [];
            $artist = $this->plainText($meta['Artist']['value'] ?? null)
                ?: $this->plainText($meta['Credit']['value'] ?? null)
                ?: 'Wikimedia Commons';
            $title = $this->plainText($meta['ObjectName']['value'] ?? null)
                ?: str_replace('File:', '', (string) ($page['title'] ?? 'Image Commons'));

            $results[] = [
                'title' => $title,
                'author' => $artist,
                'thumbUrl' => $thumb,
                'pageUrl' => $pageUrl,
            ];
        }

        return $results;
    }

    private function plainText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
