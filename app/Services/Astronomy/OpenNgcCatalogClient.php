<?php

namespace App\Services\Astronomy;

use App\Data\Astronomy\CatalogDownload;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenNgcCatalogClient
{
    public function latestVersion(): string
    {
        $release = $this->request()
            ->acceptJson()
            ->get($this->configString('nightscope.catalog.release_api_url'))
            ->throw()
            ->json();
        $version = is_array($release) ? ($release['tag_name'] ?? null) : null;

        if (! is_string($version) || preg_match('/^v\d{8}$/', $version) !== 1) {
            throw new RuntimeException('La dernière version OpenNGC est invalide.');
        }

        return $version;
    }

    public function downloadVersion(string $version): CatalogDownload
    {
        if (preg_match('/^v\d{8}$/', $version) !== 1) {
            throw new RuntimeException('La version OpenNGC demandée est invalide.');
        }

        $url = str_replace('{version}', $version, $this->configString('nightscope.catalog.release_download_url'));

        return new CatalogDownload($this->request()->get($url)->throw()->body(), $version);
    }

    public function downloadAddendumVersion(string $version): CatalogDownload
    {
        if (preg_match('/^v\d{8}$/', $version) !== 1) {
            throw new RuntimeException('La version OpenNGC demandée est invalide.');
        }

        $url = str_replace('{version}', $version, $this->configString('nightscope.catalog.addendum_download_url'));

        return new CatalogDownload($this->request()->get($url)->throw()->body(), $version);
    }

    public function pinned(): CatalogDownload
    {
        return new CatalogDownload(
            contents: $this->request()
                ->get($this->configString('nightscope.catalog.openngc_url'))
                ->throw()
                ->body(),
            version: $this->configString('nightscope.catalog.version'),
        );
    }

    public function pinnedAddendum(): CatalogDownload
    {
        return new CatalogDownload(
            contents: $this->request()
                ->get($this->configString('nightscope.catalog.addendum_url'))
                ->throw()
                ->body(),
            version: $this->configString('nightscope.catalog.version'),
        );
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders(['User-Agent' => 'DeepSky-NightScope'])
            ->connectTimeout(10)
            ->timeout(45)
            ->retry([500, 1000, 2000]);
    }

    private function configString(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Configuration manquante : {$key}.");
        }

        return $value;
    }
}
