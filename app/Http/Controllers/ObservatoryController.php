<?php

namespace App\Http\Controllers;

use App\Enums\SetupItemType;
use App\Models\Astrophoto;
use App\Support\LiveSky;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ObservatoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $availableYears = Astrophoto::published()->whereNotNull('captured_at')
            ->pluck('captured_at')
            ->map(fn ($date): int => $date->year)
            ->unique()
            ->sortDesc()
            ->values();

        $year = $request->integer('year') ?: null;

        if ($year !== null && ! $availableYears->contains($year)) {
            $year = null;
        }

        $photos = Astrophoto::published()->with(['equipmentSetup.enabledSetupItems', 'opticalTube'])
            ->when($year, fn ($query) => $query->whereYear('captured_at', $year))
            ->get(['id', 'target', 'title', 'exposure', 'filters', 'captured_at', 'equipment_setup_id', 'optical_tube_id', 'views_count', 'likes_count']);

        return view('observatory', [
            'sky' => [...LiveSky::config(), 'detailed' => true],
            'availableYears' => $availableYears,
            'selectedYear' => $year,
            'totals' => [
                'photos' => $photos->count(),
                'hours' => round($photos->sum('exposure') / 3600, 1),
                'targets' => $photos->pluck('target')->filter()->unique()->count(),
                'since' => $photos->min('captured_at'),
                'views' => $photos->sum('views_count'),
                'likes' => $photos->sum('likes_count'),
            ],
            'byFilter' => $this->hoursGroupedBy($photos, fn (Astrophoto $photo): string => $photo->filters ?: __('Sans filtre')),
            'byTube' => $this->hoursGroupedBy($photos, fn (Astrophoto $photo): string => $photo->equipmentSetup
                ? ($photo->equipmentSetup->enabledSetupItems->firstWhere('type', SetupItemType::OpticalTube)?->name ?? __('Inconnu'))
                : ($photo->opticalTube?->name ?? __('Inconnu'))),
            'topTargets' => $this->hoursGroupedBy($photos, fn (Astrophoto $photo): string => $photo->target ?: __('Inconnue'))->take(8),
            'heatmap' => $this->buildHeatmap($photos, $year),
        ]);
    }

    /**
     * Heures d'intégration agrégées par clé, triées par volume décroissant.
     *
     * @param  Collection<int, Astrophoto>  $photos
     * @return Collection<string, array{hours: float, count: int, percent: int}>
     */
    private function hoursGroupedBy(Collection $photos, callable $keyResolver): Collection
    {
        $groups = $photos->groupBy($keyResolver)->map(fn (Collection $group): array => [
            'hours' => round($group->sum('exposure') / 3600, 1),
            'count' => $group->count(),
        ])->sortByDesc('hours');

        $maxHours = max($groups->max('hours'), 0.1);

        return $groups->map(fn (array $group): array => [
            ...$group,
            'percent' => (int) round($group['hours'] / $maxHours * 100),
        ]);
    }

    /**
     * Grille type GitHub : pour chaque jour, les heures capturées et un niveau
     * d'intensité 0-4, sur l'année civile (année courante par défaut).
     *
     * @param  Collection<int, Astrophoto>  $photos
     * @return list<array{month: ?string, days: list<array{date: string, hours: float, level: int}>}>
     */
    private function buildHeatmap(Collection $photos, ?int $year = null): array
    {
        $secondsPerDay = $photos
            ->filter(fn (Astrophoto $photo): bool => $photo->captured_at !== null)
            ->groupBy(fn (Astrophoto $photo): string => $photo->captured_at->format('Y-m-d'))
            ->map(fn (Collection $group): int => (int) $group->sum('exposure'));

        $maxSeconds = max($secondsPerDay->max() ?? 0, 1);
        $nightsWithPhotos = $secondsPerDay->keys()->flip();

        // La grille court du 1er janvier au 31 décembre de l'année (année courante par défaut).
        // Les jours avant le 1er janvier dans la première colonne sont marqués level -1 (invisibles).
        $year ??= now()->year;

        $periodStart = now()->setDate($year, 1, 1)->startOfDay();
        $start = $periodStart->copy()->startOfWeek();
        $end = now()->setDate($year, 12, 31)->endOfDay();

        $weeks = [];
        $previousMonth = null;

        for ($week = 0; $week < 54; $week++) {
            $days = [];
            $weekStart = $start->copy()->addWeeks($week);

            for ($day = 0; $day < 7; $day++) {
                $date = $start->copy()->addWeeks($week)->addDays($day);

                if ($date->isFuture() || $date->greaterThan($end)) {
                    break;
                }

                if ($date->lessThan($periodStart)) {
                    $days[] = ['date' => '', 'hours' => 0.0, 'level' => -1];

                    continue;
                }

                $dayKey = $date->format('Y-m-d');
                $seconds = $secondsPerDay->get($dayKey, 0);

                $days[] = [
                    'date' => $date->format('d/m/Y'),
                    'hours' => round($seconds / 3600, 1),
                    'level' => $seconds > 0
                        ? (int) min(4, ceil($seconds / $maxSeconds * 4))
                        : ($nightsWithPhotos->has($dayKey) ? 1 : 0),
                ];
            }

            if ($days === []) {
                break;
            }

            $labelDate = $weekStart->lessThan($periodStart) ? $periodStart : $weekStart;
            $currentMonth = $labelDate->format('Y-m');

            $weeks[] = [
                'month' => $currentMonth !== $previousMonth ? ucfirst($labelDate->translatedFormat('M')) : null,
                'days' => $days,
            ];

            $previousMonth = $currentMonth;
        }

        return $weeks;
    }
}
