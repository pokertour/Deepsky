<?php

namespace App\Services;

use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\SetupItem;
use Illuminate\Support\Facades\Cache;

class GalleryFilterOptions
{
    public const CACHE_KEY = 'gallery.filter-options.v1';

    /**
     * Options des filtres de la galerie publique. Vidé par AstrophotoObserver ;
     * la durée limitée couvre les renommages de matériel ou de rig.
     *
     * @return array{
     *     constellations: list<string>,
     *     years: list<int>,
     *     rigs: list<array{id: int, name: string}>,
     *     equipment: list<array{id: int, label: string}>
     * }
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), fn (): array => [
            'constellations' => $this->constellations(),
            'years' => $this->years(),
            'rigs' => $this->rigs(),
            'equipment' => $this->equipment(),
        ]);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return list<string> */
    private function constellations(): array
    {
        return CelestialObject::whereHas('astrophotos', fn ($query) => $query->published())
            ->whereNotNull('constellation')->where('constellation', '!=', '')
            ->distinct()->orderBy('constellation')->pluck('constellation')->all();
    }

    /** @return list<int> */
    private function years(): array
    {
        return Astrophoto::published()->whereNotNull('captured_at')->pluck('captured_at')
            ->map(fn ($date): int => $date->year)->unique()->sortDesc()->values()->all();
    }

    /** @return list<array{id: int, name: string}> */
    private function rigs(): array
    {
        return EquipmentSetup::whereHas('astrophotos', fn ($query) => $query->published())
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn (EquipmentSetup $rig): array => ['id' => $rig->id, 'name' => $rig->name])
            ->all();
    }

    /** @return list<array{id: int, label: string}> */
    private function equipment(): array
    {
        return SetupItem::where(function ($query): void {
            $query->whereHas('equipmentSetups', fn ($setups) => $setups->where('equipment_setup_setup_item.enabled', true)
                ->whereHas('astrophotos', fn ($photos) => $photos->published()))
                ->orWhereIn('id', Astrophoto::published()->select('camera_id'))
                ->orWhereIn('id', Astrophoto::published()->select('mount_id'))
                ->orWhereIn('id', Astrophoto::published()->select('optical_tube_id'));
        })->orderBy('name')->get(['id', 'name', 'brand'])
            ->map(fn (SetupItem $item): array => ['id' => $item->id, 'label' => trim($item->brand.' '.$item->name)])
            ->all();
    }
}
