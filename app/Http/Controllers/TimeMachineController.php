<?php

namespace App\Http\Controllers;

use App\Models\Astrophoto;
use App\Support\LightTravel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class TimeMachineController extends Controller
{
    /**
     * Frise des photos publiées rangées par âge de leur lumière, entrecoupée des
     * repères de l'histoire de la Terre compris dans cet intervalle.
     */
    public function __invoke(): View
    {
        $photos = Astrophoto::published()
            ->with(['celestialObject', 'media'])
            ->whereHas('celestialObject', fn ($query) => $query->whereNotNull('distance_ly')->where('distance_ly', '>', 0))
            ->get()
            ->sortBy(fn (Astrophoto $photo): float => $photo->celestialObject->distance_ly)
            ->values();

        return view('time-machine', [
            'entries' => $this->timeline($photos),
            'photoCount' => $photos->count(),
            'farthest' => $photos->last(),
        ]);
    }

    /**
     * @param  Collection<int, Astrophoto>  $photos
     * @return list<array{years: float, gap: int, photo?: Astrophoto, light?: array{duration: string, epoch: string}, milestone?: string}>
     */
    private function timeline(Collection $photos): array
    {
        if ($photos->isEmpty()) {
            return [];
        }

        $nearest = $photos->first()->celestialObject->distance_ly;
        $farthest = $photos->last()->celestialObject->distance_ly;

        $entries = collect(LightTravel::MILESTONES)
            ->filter(fn (array $milestone): bool => $milestone[0] > $nearest / 3 && $milestone[0] < $farthest * 3)
            ->map(fn (array $milestone): array => ['years' => (float) $milestone[0], 'milestone' => __($milestone[1]), 'light' => LightTravel::describe($milestone[0])])
            ->merge($photos->map(fn (Astrophoto $photo): array => [
                'years' => (float) $photo->celestialObject->distance_ly,
                'photo' => $photo,
                'light' => LightTravel::describe($photo->celestialObject->distance_ly),
            ]))
            ->sortBy('years')
            ->values();

        // Espacement proportionnel au logarithme du temps écoulé entre deux étapes.
        return $entries->map(function (array $entry, int $index) use ($entries): array {
            $previous = $entries[$index - 1]['years'] ?? $entry['years'];

            return [...$entry, 'gap' => (int) min(220, round(log10(max(1, $entry['years'] / max(1, $previous))) * 90))];
        })->all();
    }
}
