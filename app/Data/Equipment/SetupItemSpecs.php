<?php

namespace App\Data\Equipment;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** @implements Arrayable<string, bool|float|int|string|null> */
final readonly class SetupItemSpecs implements Arrayable, JsonSerializable
{
    /** @param array<string, bool|float|int|string|null> $values */
    private function __construct(private array $values) {}

    /** @param array<string, bool|float|int|string|null>|null $values */
    public static function from(?array $values): self
    {
        return new self(array_filter($values ?? [], fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    public function sensorWidthMm(): ?float
    {
        return $this->float('sensor_width_mm');
    }

    public function sensorHeightMm(): ?float
    {
        return $this->float('sensor_height_mm');
    }

    public function resolutionX(): ?int
    {
        return $this->integer('resolution_x');
    }

    public function resolutionY(): ?int
    {
        return $this->integer('resolution_y');
    }

    public function pixelSizeUm(): ?float
    {
        return $this->float('pixel_size_um');
    }

    public function cooled(): ?bool
    {
        return $this->boolean('cooled');
    }

    public function preferredGain(): ?int
    {
        return $this->integer('preferred_gain');
    }

    public function typicalSubExposureSeconds(): ?int
    {
        return $this->integer('typical_sub_exposure_seconds');
    }

    public function apertureMm(): ?float
    {
        return $this->float('aperture_mm');
    }

    public function focalLengthMm(): ?float
    {
        return $this->float('focal_length_mm');
    }

    public function focalRatio(): ?float
    {
        return $this->float('focal_ratio');
    }

    public function factor(): ?float
    {
        return $this->float('factor');
    }

    public function filterType(): ?string
    {
        return $this->string('filter_type');
    }

    public function bandwidthNm(): ?float
    {
        return $this->float('bandwidth_nm');
    }

    public function centralWavelengthNm(): ?float
    {
        return $this->float('central_wavelength_nm');
    }

    public function goto(): ?bool
    {
        return $this->boolean('goto');
    }

    public function equatorial(): ?bool
    {
        return $this->boolean('equatorial');
    }

    public function meridianFlip(): ?bool
    {
        return $this->boolean('meridian_flip');
    }

    /** @return array<string, bool|float|int|string|null> */
    public function toArray(): array
    {
        return $this->values;
    }

    /** @return array<string, bool|float|int|string|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function float(string $key): ?float
    {
        return isset($this->values[$key]) ? (float) $this->values[$key] : null;
    }

    private function integer(string $key): ?int
    {
        return isset($this->values[$key]) ? (int) $this->values[$key] : null;
    }

    private function boolean(string $key): ?bool
    {
        return isset($this->values[$key]) ? (bool) $this->values[$key] : null;
    }

    private function string(string $key): ?string
    {
        return isset($this->values[$key]) ? (string) $this->values[$key] : null;
    }
}
