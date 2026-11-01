<?php

namespace App\Console\Commands;

use App\Services\Admin\ReleaseChecker;
use Illuminate\Console\Command;

class CheckLatestRelease extends Command
{
    protected $signature = 'app:check-release';

    protected $description = 'Vérifie sur GitHub si une nouvelle version de Deepsky est publiée.';

    public function handle(ReleaseChecker $releases): int
    {
        $latest = $releases->latest(refresh: true);

        if ($latest === null) {
            $this->components->warn('GitHub injoignable : vérification reportée.');

            return self::SUCCESS;
        }

        $this->components->info($releases->isUpdateAvailable($latest)
            ? "Nouvelle version disponible : {$latest['tag']} (installée : {$releases->currentVersion()})."
            : "Deepsky est à jour ({$releases->currentVersion()}).");

        return self::SUCCESS;
    }
}
