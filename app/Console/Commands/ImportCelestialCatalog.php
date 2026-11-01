<?php

namespace App\Console\Commands;

use App\Services\Astronomy\OpenNgcCatalogClient;
use App\Services\Astronomy\OpenNgcCatalogImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class ImportCelestialCatalog extends Command
{
    public const VERSION_CACHE_KEY = 'nightscope:catalog:openngc-version';

    public const LOCK_KEY = 'nightscope:catalog:openngc-import';

    protected $signature = 'nightscope:import-catalog
        {--source= : Chemin d\'un fichier OpenNGC local}
        {--addendum= : Chemin d\'un addendum OpenNGC local}
        {--latest : Détecter et importer la dernière release OpenNGC}
        {--force : Réimporter même si cette release est déjà installée}';

    protected $description = 'Importe ou met à jour Messier, NGC, IC et aliases (Caldwell, Sharpless, …) depuis OpenNGC';

    public function handle(OpenNgcCatalogImporter $importer, OpenNgcCatalogClient $client): int
    {
        $lock = Cache::lock(self::LOCK_KEY, 1800);

        if (! $lock->get()) {
            $this->warn('Un autre import du catalogue est déjà en cours.');

            return self::FAILURE;
        }

        try {
            $latestVersion = $this->option('latest') ? $client->latestVersion() : null;

            if ($latestVersion !== null && ! $this->option('force') && Cache::get(self::VERSION_CACHE_KEY) === $latestVersion) {
                $this->info("Catalogue déjà à jour ({$latestVersion}).");

                return self::SUCCESS;
            }

            [$csv, $version] = $this->catalogContents($client, $latestVersion);
            $result = $importer->import($csv);
            $addendumResult = $this->importAddendum($importer, $client, $latestVersion, $version);

            if ($version !== null) {
                Cache::forever(self::VERSION_CACHE_KEY, $version);
            }
        } catch (Throwable $exception) {
            $this->error('Import impossible : '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }

        $this->info(sprintf(
            'Catalogue à jour : %d objets importés, %d lignes ignorées (%d parcourues).',
            $result['imported'] + $addendumResult['imported'],
            $result['skipped'] + $addendumResult['skipped'],
            $result['processed'] + $addendumResult['processed'],
        ));

        if ($addendumResult['processed'] > 0) {
            $this->line(sprintf(
                'Addendum : %d importés, %d ignorés.',
                $addendumResult['imported'],
                $addendumResult['skipped'],
            ));
        }

        return self::SUCCESS;
    }

    /** @return array{string, string|null} */
    private function catalogContents(OpenNgcCatalogClient $client, ?string $latestVersion): array
    {
        $source = $this->option('source');

        if (is_string($source) && $source !== '') {
            if ($this->option('latest')) {
                throw new RuntimeException('Les options --source et --latest sont incompatibles.');
            }

            $contents = file_get_contents($source);

            if ($contents === false) {
                throw new RuntimeException("Impossible de lire le fichier {$source}.");
            }

            return [$contents, null];
        }

        $download = $latestVersion !== null ? $client->downloadVersion($latestVersion) : $client->pinned();

        return [$download->contents, $download->version];
    }

    /** @return array{processed: int, imported: int, skipped: int} */
    private function importAddendum(
        OpenNgcCatalogImporter $importer,
        OpenNgcCatalogClient $client,
        ?string $latestVersion,
        ?string $version,
    ): array {
        $empty = ['processed' => 0, 'imported' => 0, 'skipped' => 0];
        $addendumPath = $this->option('addendum');

        try {
            if (is_string($addendumPath) && $addendumPath !== '') {
                $contents = file_get_contents($addendumPath);

                if ($contents === false) {
                    throw new RuntimeException("Impossible de lire l’addendum {$addendumPath}.");
                }

                return $importer->import($contents);
            }

            if ($this->option('source')) {
                return $empty;
            }

            $download = $latestVersion !== null
                ? $client->downloadAddendumVersion($latestVersion)
                : $client->pinnedAddendum();

            return $importer->import($download->contents);
        } catch (Throwable $exception) {
            $this->warn('Addendum non importé : '.$exception->getMessage());

            return $empty;
        }
    }
}
