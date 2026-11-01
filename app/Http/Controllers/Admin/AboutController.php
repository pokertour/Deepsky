<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReleaseChecker;
use Composer\InstalledVersions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AboutController extends Controller
{
    public function __construct(private ReleaseChecker $releases) {}

    public function show(): View
    {
        $latest = $this->releases->latest();

        return view('admin.about', [
            'currentVersion' => $this->releases->currentVersion(),
            'repository' => $this->releases->repository(),
            'latest' => $latest,
            'updateAvailable' => $this->releases->isUpdateAvailable($latest),
            'isUpToDate' => $this->releases->isComparable() && filled($latest['tag'] ?? null) && ! $this->releases->isUpdateAvailable($latest),
            'releaseNotes' => filled($latest['notes'] ?? null)
                ? Str::markdown($latest['notes'], ['html_input' => 'strip', 'allow_unsafe_links' => false])
                : null,
            'system' => [
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'Livewire' => ltrim((string) InstalledVersions::getPrettyVersion('livewire/livewire'), 'v'),
                __('Serveur') => (isset($_SERVER['LARAVEL_OCTANE']) || getenv('LARAVEL_OCTANE')) ? 'Octane · '.config('octane.server') : PHP_SAPI,
                __('Base de données') => DB::connection()->getDriverName(),
                __('Cache') => config('cache.default'),
                __('File d’attente') => config('queue.default'),
                __('Fuseau horaire') => config('admin.timezone'),
            ],
        ]);
    }

    public function refresh(): RedirectResponse
    {
        $latest = $this->releases->latest(refresh: true);

        return redirect()->route('admin.about')->with(
            $latest === null ? 'error' : 'success',
            match (true) {
                $latest === null => __('GitHub ne répond pas : réessaie plus tard.'),
                $this->releases->isUpdateAvailable($latest) => __('Nouvelle version disponible : :version.', ['version' => $latest['tag']]),
                default => __('Deepsky est à jour.'),
            },
        );
    }
}
