<?php

namespace App\Services\Planning;

use App\Data\Planning\FilterRecommendation;
use App\Enums\BortleClass;
use App\Enums\FilterType;
use App\Enums\SetupItemType;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\SetupItem;
use Illuminate\Support\Collection;

class FilterRecommendationCalculator
{
    /**
     * @return list<FilterRecommendation>
     */
    public function recommend(
        CelestialObject $target,
        ?EquipmentSetup $setup,
        float $moonIlluminationPercent = 0.0,
        float $moonSeparationDeg = 180.0,
        ?int $bortleClass = null,
    ): array {
        $onRig = $this->filtersOnSetup($setup);
        $inventory = $this->catalogFilters();
        $type = strtolower((string) ($target->type ?? ''));
        $moonlit = $this->isMoonlit($moonIlluminationPercent, $moonSeparationDeg);
        $polluted = $this->isLightPolluted($bortleClass);
        $selectiveSky = $moonlit || $polluted;

        return match ($type) {
            'nebula' => $this->forNebula($onRig, $inventory, $selectiveSky, $moonlit, $polluted, $bortleClass),
            'galaxy' => $this->forContinuum($onRig, $inventory, 'galaxy', $polluted, $bortleClass),
            'cluster' => $this->forContinuum($onRig, $inventory, 'cluster', $polluted, $bortleClass),
            default => $this->forContinuum($onRig, $inventory, 'default', $polluted, $bortleClass),
        };
    }

    /**
     * @param  Collection<int, SetupItem>  $onRig
     * @param  Collection<int, SetupItem>  $inventory
     * @return list<FilterRecommendation>
     */
    private function forNebula(
        Collection $onRig,
        Collection $inventory,
        bool $selectiveSky,
        bool $moonlit,
        bool $polluted,
        ?int $bortleClass,
    ): array {
        $dual = $this->matchPreferred($onRig, $inventory, FilterType::DualBand, [
            'l-ultimate', 'l ultimate', 'lultimate', 'l-enhance', 'l enhance', 'lenhance',
            'l-pro', 'dual-band', 'dual band', 'duo-band', 'duoband', 'ha/oiii', 'ha-oiii',
        ]);
        $narrow = $this->matchPreferred($onRig, $inventory, FilterType::Narrowband, [
            'h-alpha', 'h alpha', 'oiii', 'sii', 'narrowband', 'bande étroite', 'bande etroite',
        ]);
        $uvIr = $this->matchPreferred($onRig, $inventory, FilterType::UvIrCut, [
            'uv/ir', 'uv-ir', 'uv ir', 'uvir', 'uv ir cut', 'ir-cut', 'ircut',
        ]);

        $skyHint = match (true) {
            $moonlit && $polluted => __('Lune + Bortle :class', ['class' => $bortleClass]),
            $moonlit => __('Lune gênante'),
            $polluted => __('Bortle :class', ['class' => $bortleClass]),
            default => null,
        };

        $recommendations = [];

        if ($selectiveSky) {
            $match = $dual['item'] ?? $narrow['item'] ?? null;
            $onRigMatch = (bool) ($dual['on_rig'] ?? $narrow['on_rig'] ?? false);

            $recommendations[] = $this->recommendation(
                preferredType: FilterType::DualBand,
                match: $match,
                onRig: $onRigMatch,
                fallbackLabel: 'L-Ultimate',
                reason: $match
                    ? __(':hint : privilégier un dual-band / bande étroite pour rejeter le continuum.', ['hint' => $skyHint])
                    : __(':hint : un dual-band type L-Ultimate (Ha/OIII) est le meilleur compromis.', ['hint' => $skyHint ?? __('Ciel chargé')]),
                assumedBandwidthNm: 5.0,
            );

            if ($uvIr['item'] ?? null) {
                $recommendations[] = $this->recommendation(
                    preferredType: FilterType::UvIrCut,
                    match: $uvIr['item'],
                    onRig: $uvIr['on_rig'],
                    fallbackLabel: 'UV/IR Cut',
                    reason: __('À éviter ce soir : le continuum (Lune / pollution) dominerait rapidement.'),
                );
            }

            return $recommendations;
        }

        $match = $dual['item'] ?? $narrow['item'] ?? null;
        $recommendations[] = $this->recommendation(
            preferredType: FilterType::DualBand,
            match: $match,
            onRig: (bool) ($dual['on_rig'] ?? $narrow['on_rig'] ?? false),
            fallbackLabel: 'L-Ultimate',
            reason: $match
                ? __('Nébuleuse en émission : dual-band / bande étroite pour maximiser Ha/OIII.')
                : __('Nébuleuse : viser un dual-band type L-Ultimate (Ha/OIII), même sous ciel correct.'),
            assumedBandwidthNm: 5.0,
        );

        $recommendations[] = $this->recommendation(
            preferredType: FilterType::UvIrCut,
            match: $uvIr['item'] ?? null,
            onRig: (bool) ($uvIr['on_rig'] ?? false),
            fallbackLabel: 'UV/IR Cut',
            reason: __('Alternative RGB / continuum si tu veux aussi le fond d’étoiles naturel.'),
        );

        return $recommendations;
    }

    /**
     * @param  Collection<int, SetupItem>  $onRig
     * @param  Collection<int, SetupItem>  $inventory
     * @return list<FilterRecommendation>
     */
    private function forContinuum(
        Collection $onRig,
        Collection $inventory,
        string $kind,
        bool $polluted,
        ?int $bortleClass,
    ): array {
        $uvIr = $this->matchPreferred($onRig, $inventory, FilterType::UvIrCut, [
            'uv/ir', 'uv-ir', 'uv ir', 'uvir', 'uv ir cut', 'ir-cut', 'ircut',
        ]);
        $broadband = $this->matchPreferred($onRig, $inventory, FilterType::Broadband, [
            'broadband', 'lrgb', 'clear', 'cls',
        ]);
        $luminance = $this->matchPreferred($onRig, $inventory, FilterType::Luminance, [
            'luminance', 'lum ',
        ]);
        $dual = $this->matchPreferred($onRig, $inventory, FilterType::DualBand, [
            'l-ultimate', 'l ultimate', 'dual-band', 'dual band',
        ]);

        $primary = $uvIr['item'] ?? $broadband['item'] ?? $luminance['item'] ?? null;
        $primaryOnRig = (bool) ($uvIr['on_rig'] ?? $broadband['on_rig'] ?? $luminance['on_rig'] ?? false);
        $objectLabel = match ($kind) {
            'galaxy' => __('Galaxie'),
            'cluster' => __('Amas'),
            default => __('Cible continuum'),
        };

        $reason = $primary
            ? __(':object : continuum stellaire — UV/IR cut / broadband adapté.', ['object' => $objectLabel])
            : __(':object : privilégier un UV/IR cut (ou L/RGB), pas un dual-band.', ['object' => $objectLabel]);

        if ($polluted && $bortleClass !== null) {
            $reason .= ' '.__('Bortle :class : le fond de ciel restera gris, mais le continuum reste prioritaire.', ['class' => $bortleClass]);
        }

        $recommendations = [
            $this->recommendation(
                preferredType: FilterType::UvIrCut,
                match: $primary,
                onRig: $primaryOnRig,
                fallbackLabel: 'UV/IR Cut',
                reason: $reason,
            ),
        ];

        if ($dual['item'] ?? null) {
            $recommendations[] = $this->recommendation(
                preferredType: FilterType::DualBand,
                match: $dual['item'],
                onRig: $dual['on_rig'],
                fallbackLabel: 'L-Ultimate',
                reason: __('Peu adapté ici : un dual-band coupe trop le continuum de la cible.'),
            );
        }

        return $recommendations;
    }

    /**
     * @param  Collection<int, SetupItem>  $onRig
     * @param  Collection<int, SetupItem>  $inventory
     * @param  list<string>  $nameNeedles
     * @return array{item: ?SetupItem, on_rig: bool}
     */
    private function matchPreferred(
        Collection $onRig,
        Collection $inventory,
        FilterType $type,
        array $nameNeedles,
    ): array {
        $onRigMatch = $this->firstMatch($onRig, $type, $nameNeedles);

        if ($onRigMatch) {
            return ['item' => $onRigMatch, 'on_rig' => true];
        }

        $inventoryMatch = $this->firstMatch($inventory, $type, $nameNeedles);

        return ['item' => $inventoryMatch, 'on_rig' => false];
    }

    /**
     * @param  Collection<int, SetupItem>  $filters
     * @param  list<string>  $nameNeedles
     */
    private function firstMatch(Collection $filters, FilterType $type, array $nameNeedles): ?SetupItem
    {
        $byType = $filters->first(
            fn (SetupItem $item): bool => FilterType::tryFrom((string) $item->specs->filterType()) === $type,
        );

        if ($byType) {
            return $byType;
        }

        return $filters->first(function (SetupItem $item) use ($nameNeedles): bool {
            $haystack = strtolower(trim($item->brand.' '.$item->name));

            foreach ($nameNeedles as $needle) {
                if ($needle !== '' && str_contains($haystack, $needle)) {
                    return true;
                }
            }

            return false;
        });
    }

    private function recommendation(
        FilterType $preferredType,
        ?SetupItem $match,
        bool $onRig,
        string $fallbackLabel,
        string $reason,
        ?float $assumedBandwidthNm = null,
    ): FilterRecommendation {
        if ($match) {
            $matchedType = FilterType::tryFrom((string) $match->specs->filterType()) ?? $preferredType;

            if (! $onRig) {
                $reason .= __(' Présent dans ton inventaire : monte-le sur le Rig.');
            }

            return new FilterRecommendation(
                filterType: $matchedType,
                label: $match->name !== '' ? $match->name : $fallbackLabel,
                reason: $reason,
                inInventory: true,
                onRig: $onRig,
                bandwidthNm: $match->specs->bandwidthNm() ?? $assumedBandwidthNm,
                brand: $match->brand !== '' ? $match->brand : null,
                itemName: $match->name !== '' ? $match->name : null,
            );
        }

        return new FilterRecommendation(
            filterType: $preferredType,
            label: $fallbackLabel,
            reason: $reason,
            inInventory: false,
            onRig: false,
            bandwidthNm: $assumedBandwidthNm,
        );
    }

    private function isMoonlit(float $illuminationPercent, float $separationDeg): bool
    {
        $illuminationThreshold = (float) config('nightscope.filters.moonlit_illumination_percent', 40);
        $separationThreshold = (float) config('nightscope.filters.moonlit_separation_deg', 50);

        return $illuminationPercent >= $illuminationThreshold && $separationDeg <= $separationThreshold;
    }

    private function isLightPolluted(?int $bortleClass): bool
    {
        $class = BortleClass::tryFrom((int) $bortleClass);

        return $class?->isLightPolluted() === true;
    }

    /** @return Collection<int, SetupItem> */
    private function filtersOnSetup(?EquipmentSetup $setup): Collection
    {
        if (! $setup) {
            return collect();
        }

        $items = $setup->relationLoaded('enabledSetupItems')
            ? $setup->enabledSetupItems
            : $setup->setupItems()->wherePivot('enabled', true)->get();

        return $items
            ->filter(fn (SetupItem $item): bool => $item->type === SetupItemType::Filter)
            ->values();
    }

    /** @return Collection<int, SetupItem> */
    private function catalogFilters(): Collection
    {
        return SetupItem::query()
            ->where('type', SetupItemType::Filter)
            ->orderBy('brand')
            ->orderBy('name')
            ->get();
    }
}
