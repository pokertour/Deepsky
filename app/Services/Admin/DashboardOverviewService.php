<?php

namespace App\Services\Admin;

use App\Console\Commands\WarmR2StorageStats;
use App\Data\Planning\TonightData;
use App\Enums\ImagingPlanStatus;
use App\Enums\TaskRunStatus;
use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\ImagingPlan;
use App\Models\NewsletterSubscriber;
use App\Models\TaskRun;
use App\Services\Planning\TonightService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardOverviewService
{
    public function __construct(private TonightService $tonight, private AstrophotoAttentionService $attention) {}

    /**
     * @return array{
     *     stats: array<string, mixed>,
     *     storage: array{size: int, count: int}|null,
     *     recentPhotos: Collection<int, Astrophoto>,
     *     tonight: TonightData,
     *     nextPlan: ?ImagingPlan,
     *     alerts: list<array{label: string, count: int, url: string}>,
     *     recentTaskFailures: Collection<int, TaskRun>
     * }
     */
    public function build(): array
    {
        return [
            'stats' => $this->stats(),
            'storage' => $this->storageStats(),
            'recentPhotos' => Astrophoto::query()->with('media')->latest()->limit(5)->get(),
            'tonight' => $this->tonightSnapshot(),
            'nextPlan' => $this->nextPlan(),
            'alerts' => $this->alerts(),
            'recentTaskFailures' => TaskRun::query()
                ->where('status', TaskRunStatus::Failed)
                ->where('started_at', '>=', now()->subDay())
                ->latest('started_at')
                ->limit(5)
                ->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        return Cache::remember('admin.dashboard.stats.v3', now()->addMinutes(5), function (): array {
            $months = collect(range(11, 0))->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset));
            $targetExposure = Astrophoto::query()->whereNotNull('target')->where('target', '!=', '')
                ->select('target')->selectRaw('SUM(exposure) as total_exposure')->groupBy('target')
                ->orderByDesc('total_exposure')->pluck('total_exposure', 'target');
            $captures = Astrophoto::query()->where('captured_at', '>=', $months->first())
                ->get(['exposure', 'captured_at'])->groupBy(fn ($photo) => $photo->captured_at->format('Y-m'));
            $published = Astrophoto::published()->count();
            $drafts = Astrophoto::query()->where('is_published', false)->count();

            return [
                'photos' => $published + $drafts,
                'published' => $published,
                'drafts' => $drafts,
                'visits' => (int) DB::table('site_settings')->where('key', 'total_visits')->value('value'),
                'exposure' => (int) Astrophoto::query()->sum('exposure'),
                'targets' => $targetExposure->count(),
                'favorite_target' => $targetExposure->keys()->first(),
                'month_photos' => Astrophoto::query()->whereBetween('captured_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                'newsletter_confirmed' => NewsletterSubscriber::confirmed()->count(),
                'newsletter_pending' => NewsletterSubscriber::query()->whereNull('confirmed_at')->count(),
                'unlinked_photos' => Astrophoto::query()->whereNull('celestial_object_id')->count(),
                'catalog_objects' => CelestialObject::query()->count(),
                'missing_distances' => CelestialObject::query()->whereNull('distance_ly')->count(),
                'periods' => $this->periods(),
                'chart' => $months->map(fn ($month): array => [
                    'label' => $month->translatedFormat('M'),
                    'hours' => round(($captures->get($month->format('Y-m'))?->sum('exposure') ?? 0) / 3600, 1),
                ])->all(),
            ];
        });
    }

    private function periods(): array
    {
        $end = CarbonImmutable::tomorrow();
        $start = $end->subDays(30);
        $previousStart = $start->subDays(30);
        $periods = [];

        foreach (['current' => [$start, $end], 'previous' => [$previousStart, $start]] as $name => [$from, $until]) {
            $periods[$name] = [
                'photos' => Astrophoto::query()->where('created_at', '>=', $from)->where('created_at', '<', $until)->count(),
                'exposure' => (int) Astrophoto::query()->where('captured_at', '>=', $from)->where('captured_at', '<', $until)->sum('exposure'),
                'subscribers' => NewsletterSubscriber::query()->where('created_at', '>=', $from)->where('created_at', '<', $until)->count(),
                'confirmations' => NewsletterSubscriber::confirmed()->where('confirmed_at', '>=', $from)->where('confirmed_at', '<', $until)->count(),
            ];
        }

        return $periods;
    }

    private function alerts(): array
    {
        $groups = $this->attention->groups();

        return [
            ['label' => __('Brouillons prêts à publier'), 'count' => count($groups['ready']), 'url' => route('admin.astrophotos.index', ['attention' => 'ready'])],
            ['label' => __('Traitements en échec'), 'count' => count($groups['failed']), 'url' => route('admin.astrophotos.index', ['attention' => 'failed'])],
            ['label' => __('Photos sans objet lié'), 'count' => Astrophoto::query()->whereNull('celestial_object_id')->count(), 'url' => route('admin.astrophotos.index', ['attention' => 'unlinked'])],
            ['label' => __('Confirmations en attente'), 'count' => NewsletterSubscriber::query()->whereNull('confirmed_at')->count(), 'url' => route('admin.newsletter.index', ['status' => 'pending'])],
        ];
    }

    private function tonightSnapshot(): TonightData
    {
        try {
            return $this->tonight->build(CarbonImmutable::today(), recommendationLimit: 3);
        } catch (Throwable $exception) {
            report($exception);

            return new TonightData(
                date: CarbonImmutable::today(),
                location: null,
                equipmentSetup: null,
                optics: null,
                night: null,
                moon: null,
                weather: null,
                nightScore: null,
                targets: [],
            );
        }
    }

    private function nextPlan(): ?ImagingPlan
    {
        return ImagingPlan::query()
            ->with(['targets.celestialObject', 'equipmentSetup:id,name', 'observingLocation:id,name,region'])
            ->whereIn('status', [ImagingPlanStatus::Planned, ImagingPlanStatus::Active])
            ->whereDate('date', '>=', now()->toDateString())
            ->orderByRaw('status = ? desc', [ImagingPlanStatus::Active->value])
            ->orderBy('date')
            ->first();
    }

    /** @return array{size: int, count: int}|null */
    private function storageStats(): ?array
    {
        try {
            return Cache::get(WarmR2StorageStats::CACHE_KEY);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }
}
