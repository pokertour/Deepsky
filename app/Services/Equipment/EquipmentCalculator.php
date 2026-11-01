<?php

namespace App\Services\Equipment;

use App\Data\Equipment\EquipmentOptics;
use App\Enums\SetupItemType;
use App\Models\EquipmentSetup;
use App\Models\SetupItem;
use DomainException;
use Illuminate\Support\Collection;

class EquipmentCalculator
{
    public function __construct(
        private PixelScaleCalculator $pixelScale,
        private FieldOfViewCalculator $fieldOfView,
    ) {}

    public function calculate(EquipmentSetup $setup): EquipmentOptics
    {
        /** @var Collection<int, SetupItem> $items */
        $items = $setup->setupItems()
            ->wherePivot('enabled', true)
            ->get();
        $tube = $items->first(fn (SetupItem $item): bool => $item->type === SetupItemType::OpticalTube);
        $camera = $items->first(fn (SetupItem $item): bool => $item->type === SetupItemType::Camera);

        if (! $tube || ! $camera) {
            throw new DomainException(__('Le rig doit contenir un tube optique et une caméra actifs.'));
        }

        $nativeFocalLength = $tube->specs->focalLengthMm();
        $aperture = $tube->specs->apertureMm();
        $pixelSize = $camera->specs->pixelSizeUm();
        $sensorWidth = $camera->specs->sensorWidthMm()
            ?? $this->sensorSizeFromResolution($camera->specs->resolutionX(), $pixelSize);
        $sensorHeight = $camera->specs->sensorHeightMm()
            ?? $this->sensorSizeFromResolution($camera->specs->resolutionY(), $pixelSize);

        if (! $nativeFocalLength || ! $aperture || ! $sensorWidth || ! $sensorHeight || ! $pixelSize) {
            throw new DomainException(__('Les caractéristiques optiques du tube ou de la caméra sont incomplètes.'));
        }

        $factor = $items
            ->filter(fn (SetupItem $item): bool => in_array($item->type, [SetupItemType::Reducer, SetupItemType::Corrector], true))
            ->reduce(fn (float $total, SetupItem $item): float => $total * ($item->specs->factor() ?? 1.0), 1.0);
        $effectiveFocalLength = $nativeFocalLength * $factor;
        $diagonal = hypot($sensorWidth, $sensorHeight);

        return new EquipmentOptics(
            nativeFocalLengthMm: $nativeFocalLength,
            effectiveFocalLengthMm: $effectiveFocalLength,
            apertureMm: $aperture,
            focalRatio: $effectiveFocalLength / $aperture,
            sensorWidthMm: $sensorWidth,
            sensorHeightMm: $sensorHeight,
            pixelSizeUm: $pixelSize,
            pixelScaleArcsec: $this->pixelScale->calculate($pixelSize, $effectiveFocalLength),
            horizontalFieldOfViewDeg: $this->fieldOfView->calculateDegrees($sensorWidth, $effectiveFocalLength),
            verticalFieldOfViewDeg: $this->fieldOfView->calculateDegrees($sensorHeight, $effectiveFocalLength),
            diagonalFieldOfViewDeg: $this->fieldOfView->calculateDegrees($diagonal, $effectiveFocalLength),
        );
    }

    private function sensorSizeFromResolution(?int $resolution, ?float $pixelSizeUm): ?float
    {
        return $resolution && $pixelSizeUm ? $resolution * $pixelSizeUm / 1000 : null;
    }
}
