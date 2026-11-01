<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CelestialObject extends Model
{
    use HasFactory;

    protected $fillable = [
        'catalog',
        'catalog_number',
        'name',
        'alternative_names',
        'type',
        'constellation',
        'ra_deg',
        'dec_deg',
        'magnitude',
        'surface_brightness',
        'angular_width_arcmin',
        'angular_height_arcmin',
        'distance_ly',
        'distance_checked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'alternative_names' => 'array',
            'ra_deg' => 'float',
            'dec_deg' => 'float',
            'magnitude' => 'float',
            'surface_brightness' => 'float',
            'angular_width_arcmin' => 'float',
            'angular_height_arcmin' => 'float',
            'distance_ly' => 'float',
            'distance_checked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function astrophotos(): HasMany
    {
        return $this->hasMany(Astrophoto::class);
    }

    public function imagingPlanTargets(): HasMany
    {
        return $this->hasMany(ImagingPlanTarget::class);
    }

    public function designation(): string
    {
        return trim($this->catalog.' '.$this->catalog_number);
    }

    public function displayName(): string
    {
        return $this->name ? $this->designation().' — '.$this->name : $this->designation();
    }

    public function formattedDistance(): ?string
    {
        return $this->distance_ly === null ? null : self::formatLightYears($this->distance_ly);
    }

    /**
     * Diamètre réel le long du grand axe : D × 2·tan(θ/2), avec θ la plus grande
     * dimension angulaire du catalogue.
     */
    public function physicalSizeLightYears(): ?float
    {
        $majorAxisArcmin = max((float) $this->angular_width_arcmin, (float) $this->angular_height_arcmin);

        if ($this->distance_ly === null || $this->distance_ly <= 0 || $majorAxisArcmin <= 0) {
            return null;
        }

        return $this->distance_ly * 2 * tan(deg2rad($majorAxisArcmin / 60) / 2);
    }

    public function formattedPhysicalSize(): ?string
    {
        $size = $this->physicalSizeLightYears();

        return $size === null ? null : '≈ '.self::formatLightYears($size);
    }

    private static function formatLightYears(float $lightYears): string
    {
        return match (true) {
            $lightYears >= 1_000_000 => number_format($lightYears / 1_000_000, 2, ',', ' ').' M al',
            $lightYears >= 10_000 => number_format($lightYears / 1_000, 1, ',', ' ').' k al',
            $lightYears < 10 => number_format($lightYears, 1, ',', ' ').' al',
            default => number_format($lightYears, 0, ',', ' ').' al',
        };
    }

    public function formattedAngularSize(): ?string
    {
        if (! $this->angular_width_arcmin && ! $this->angular_height_arcmin) {
            return null;
        }

        $width = $this->angular_width_arcmin ? number_format($this->angular_width_arcmin, 1, ',', ' ').'′' : '—';
        $height = $this->angular_height_arcmin ? number_format($this->angular_height_arcmin, 1, ',', ' ').'′' : '—';

        return $width.' × '.$height;
    }

    public function galleryTag(): ?string
    {
        return match ($this->type) {
            'galaxy', 'nebula', 'cluster' => $this->type,
            default => null,
        };
    }

    public function suggestedPhotoTitle(): string
    {
        return $this->name ?: $this->designation();
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        $like = '%'.$search.'%';

        return $query->where(function (Builder $query) use ($like, $search): void {
            $query->where('name', 'like', $like)
                ->orWhere('catalog_number', 'like', $like)
                ->orWhere('catalog', 'like', $like)
                ->orWhere('alternative_names', 'like', $like)
                ->orWhere('constellation', 'like', $like);

            if (preg_match('/^(M|NGC|IC)\s*(\d+)/i', $search, $matches) === 1) {
                $query->orWhere(function (Builder $query) use ($matches): void {
                    $query->where('catalog', strtoupper($matches[1]))
                        ->where('catalog_number', ltrim($matches[2], '0'));
                });
            }
        });
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return filled($type) ? $query->where('type', $type) : $query;
    }

    public function scopeInConstellation(Builder $query, ?string $constellation): Builder
    {
        return filled($constellation) ? $query->where('constellation', $constellation) : $query;
    }

    public function scopeMagnitudeBetween(Builder $query, ?float $max, ?float $min = null): Builder
    {
        if ($max !== null) {
            $query->where('magnitude', '<=', $max);
        }

        if ($min !== null) {
            $query->where('magnitude', '>=', $min);
        }

        return $query;
    }

    public function scopeMinimumSize(Builder $query, ?float $arcmin): Builder
    {
        if ($arcmin === null) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($arcmin): void {
            $query->where('angular_width_arcmin', '>=', $arcmin)
                ->orWhere('angular_height_arcmin', '>=', $arcmin);
        });
    }

    public function scopePhotographed(Builder $query, ?string $photographed): Builder
    {
        return match ($photographed) {
            'yes' => $query->has('astrophotos'),
            'no' => $query->doesntHave('astrophotos'),
            default => $query,
        };
    }
}
