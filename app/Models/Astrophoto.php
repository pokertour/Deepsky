<?php

namespace App\Models;

use App\Enums\AstrometryStatus;
use App\Enums\ConversionStatus;
use App\Observers\AstrophotoObserver;
use App\Support\AcquisitionData;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[ObservedBy([AstrophotoObserver::class])]
class Astrophoto extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $attributes = ['is_published' => true];

    protected $fillable = [
        'title',
        'title_en',
        'is_published',
        'publish_at',
        'target',
        'celestial_object_id',
        'equipment_setup_id',
        'imaging_session_id',
        'observing_location_id',
        'captured_at',
        'exposure',
        'acquisition_sessions',
        'gain',
        'offset',
        'sensor_temp',
        'focal_length',
        'filters',
        'camera_id',
        'mount_id',
        'optical_tube_id',
        'description',
        'description_en',
        'object_description',
        'object_description_en',
        'location_tag',
        'tags',
        'views_count',
        'likes_count',
        'astrometry_status',
        'astrometry_submission_id',
        'ra',
        'dec',
        'pixel_scale',
        'field_radius',
        'orientation',
        'objects_in_field',
        'annotations',
        'astrometry_solved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'newsletter_notified_at' => 'datetime',
            'publish_at' => 'datetime',
            'captured_at' => 'date',
            'gain' => 'integer',
            'offset' => 'integer',
            'focal_length' => 'integer',
            'tags' => 'array',
            'acquisition_sessions' => 'array',
            'astrometry_status' => AstrometryStatus::class,
            'ra' => 'float',
            'dec' => 'float',
            'pixel_scale' => 'float',
            'field_radius' => 'float',
            'orientation' => 'float',
            'objects_in_field' => 'array',
            'annotations' => 'array',
            'astrometry_solved_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Brouillons dont l'heure de publication programmée est passée.
     */
    public function scopeDueForPublication(Builder $query): Builder
    {
        return $query->where('is_published', false)->whereNotNull('publish_at')->where('publish_at', '<=', now());
    }

    /**
     * La miniature est générée en file d'attente : tant qu'elle n'existe pas,
     * son URL pointerait vers un fichier absent.
     */
    /**
     * Texte dans la langue de la requête : la version anglaise (`{champ}_en`)
     * si elle est renseignée, sinon le texte français d'origine.
     */
    public function localized(string $field, ?string $locale = null): ?string
    {
        $translated = ($locale ?? app()->getLocale()) === 'en' ? $this->getAttribute($field.'_en') : null;

        return filled($translated) ? $translated : $this->getAttribute($field);
    }

    public function thumbnailUrl(string $collection = 'astros'): ?string
    {
        $media = $this->getFirstMedia($collection);

        return $media?->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : null;
    }

    public function getConversionStatusAttribute(): ConversionStatus
    {
        $media = $this->getMedia('astros')->merge($this->getMedia('starless'));
        $processingStatus = fn (Media $item): ?ConversionStatus => ConversionStatus::tryFrom((string) $item->getCustomProperty('processing_status'));

        if ($this->getFirstMedia('astros') === null) {
            return ConversionStatus::Missing;
        }

        if ($media->contains(fn (Media $item) => $processingStatus($item) === ConversionStatus::Failed
            || ($processingStatus($item) === ConversionStatus::Converting
                && filled($item->getCustomProperty('processing_started_at'))
                && Carbon::parse($item->getCustomProperty('processing_started_at'))->lt(now()->subMinutes(30))))) {
            return ConversionStatus::Failed;
        }

        if ($media->every(fn (Media $item) => collect(['thumb', 'optimized', 'full_res'])
            ->every(fn (string $conversion) => $item->hasGeneratedConversion($conversion)))) {
            return ConversionStatus::Ready;
        }

        return $media->contains(fn (Media $item) => $processingStatus($item) === ConversionStatus::Converting)
            ? ConversionStatus::Converting : ConversionStatus::Queued;
    }

    public function getConversionStatusLabelAttribute(): string
    {
        return $this->conversion_status->getLabel();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('astros')->useDisk('s3')->singleFile();
        $this->addMediaCollection('starless')->useDisk('s3')->singleFile();
    }

    public function camera(): BelongsTo
    {
        return $this->belongsTo(SetupItem::class, 'camera_id');
    }

    public function mount(): BelongsTo
    {
        return $this->belongsTo(SetupItem::class, 'mount_id');
    }

    public function opticalTube(): BelongsTo
    {
        return $this->belongsTo(SetupItem::class, 'optical_tube_id');
    }

    public function celestialObject(): BelongsTo
    {
        return $this->belongsTo(CelestialObject::class);
    }

    public function equipmentSetup(): BelongsTo
    {
        return $this->belongsTo(EquipmentSetup::class);
    }

    public function imagingSession(): BelongsTo
    {
        return $this->belongsTo(ImagingSession::class);
    }

    public function observingLocation(): BelongsTo
    {
        return $this->belongsTo(ObservingLocation::class);
    }

    public function getFormattedIntegAttribute(): string
    {
        return AcquisitionData::formatDuration((float) $this->exposure);
    }

    public function getFormattedRaAttribute(): ?string
    {
        if ($this->ra === null) {
            return null;
        }

        $hoursDecimal = $this->ra / 15;
        $hours = floor($hoursDecimal);
        $minutesDecimal = ($hoursDecimal - $hours) * 60;
        $minutes = floor($minutesDecimal);
        $seconds = ($minutesDecimal - $minutes) * 60;

        return sprintf('%02dh %02dm %04.1fs', $hours, $minutes, $seconds);
    }

    public function getFormattedDecAttribute(): ?string
    {
        if ($this->dec === null) {
            return null;
        }

        $sign = $this->dec < 0 ? '-' : '+';
        $absolute = abs($this->dec);
        $degrees = floor($absolute);
        $minutesDecimal = ($absolute - $degrees) * 60;
        $minutes = floor($minutesDecimal);
        $seconds = ($minutesDecimal - $minutes) * 60;

        return sprintf('%s%02d° %02d′ %04.1f″', $sign, $degrees, $minutes, $seconds);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Max, 800, 800) // Garde le ratio, max 800px (400 c'est un peu petit pour les écrans Retina)
            ->format('webp')          // Format moderne, plus propre sur les dégradés
            ->quality(90)             // On monte la qualité (par défaut c'est souvent 80)
            ->sharpen(5);             // Un peu de piqué pour les étoiles, mais pas trop

        // Conversions lourdes : on les génère en file d'attente pour ne pas bloquer l'upload.
        $this->addMediaConversion('optimized')
            ->fit(Fit::Max, 2560, 1440)
            ->format('webp')
            ->quality(85)
            ->withResponsiveImages();

        $this->addMediaConversion('full_res')
            ->format('png'); // Le PNG est lisible par Chrome/Firefox
    }
}
