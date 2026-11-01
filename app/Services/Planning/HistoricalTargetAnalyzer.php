<?php

namespace App\Services\Planning;

use App\Data\Planning\TargetHistory;
use App\Models\Astrophoto;
use App\Models\CelestialObject;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class HistoricalTargetAnalyzer
{
    public function analyze(CelestialObject $target, int $recentLimit = 8): TargetHistory
    {
        $stats = Astrophoto::query()
            ->whereBelongsTo($target)
            ->selectRaw('COUNT(*) AS photo_count')
            ->selectRaw('COUNT(DISTINCT captured_at) AS session_count')
            ->selectRaw('COALESCE(SUM(exposure), 0) AS total_integration_seconds')
            ->selectRaw('MAX(captured_at) AS last_captured_at')
            ->first();

        $recentPhotos = $recentLimit > 0
            ? Astrophoto::query()
                ->whereBelongsTo($target)
                ->latest('captured_at')
                ->latest('id')
                ->limit($recentLimit)
                ->get()
                ->all()
            : [];

        return new TargetHistory(
            photoCount: (int) $stats->photo_count,
            sessionCount: (int) $stats->session_count,
            totalIntegrationSeconds: (int) $stats->total_integration_seconds,
            lastCapturedAt: $stats->last_captured_at
                ? CarbonImmutable::parse($stats->last_captured_at)
                : null,
            recentPhotos: $recentPhotos,
        );
    }

    /**
     * @param  Collection<int, CelestialObject>|list<int>  $targets
     * @return array<int, TargetHistory>
     */
    public function analyzeMany(Collection|array $targets): array
    {
        $ids = collect($targets)
            ->map(fn (CelestialObject|int $target): int => $target instanceof CelestialObject ? $target->id : $target)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $rows = Astrophoto::query()
            ->whereIn('celestial_object_id', $ids)
            ->selectRaw('celestial_object_id')
            ->selectRaw('COUNT(*) AS photo_count')
            ->selectRaw('COUNT(DISTINCT captured_at) AS session_count')
            ->selectRaw('COALESCE(SUM(exposure), 0) AS total_integration_seconds')
            ->selectRaw('MAX(captured_at) AS last_captured_at')
            ->groupBy('celestial_object_id')
            ->get()
            ->keyBy('celestial_object_id');

        $histories = [];

        foreach ($ids as $id) {
            $row = $rows->get($id);
            $histories[$id] = new TargetHistory(
                photoCount: (int) ($row->photo_count ?? 0),
                sessionCount: (int) ($row->session_count ?? 0),
                totalIntegrationSeconds: (int) ($row->total_integration_seconds ?? 0),
                lastCapturedAt: isset($row->last_captured_at) && $row->last_captured_at
                    ? CarbonImmutable::parse($row->last_captured_at)
                    : null,
            );
        }

        return $histories;
    }

    /** @return array{photos_by_type: array<string, int>, total_photos: int} */
    public function catalogDiversity(): array
    {
        $rows = Astrophoto::query()
            ->join('celestial_objects', 'celestial_objects.id', '=', 'astrophotos.celestial_object_id')
            ->whereNotNull('astrophotos.celestial_object_id')
            ->selectRaw('LOWER(COALESCE(celestial_objects.type, \'\')) AS object_type')
            ->selectRaw('COUNT(*) AS photo_count')
            ->groupBy('object_type')
            ->pluck('photo_count', 'object_type');

        $photosByType = [];

        foreach ($rows as $type => $count) {
            $photosByType[(string) $type] = (int) $count;
        }

        return [
            'photos_by_type' => $photosByType,
            'total_photos' => array_sum($photosByType),
        ];
    }
}
