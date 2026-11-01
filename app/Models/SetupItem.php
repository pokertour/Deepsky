<?php

namespace App\Models;

use App\Casts\SetupItemSpecsCast;
use App\Enums\SetupItemType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SetupItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'brand',
        'specs',
    ];

    protected function casts(): array
    {
        return [
            'type' => SetupItemType::class,
            'specs' => SetupItemSpecsCast::class,
        ];
    }

    /**
     * Récupère toutes les photos prises avec cet équipement spécifique.
     * Note : On définit plusieurs relations selon le type pour plus de précision.
     */
    public function photosAsCamera(): HasMany
    {
        return $this->hasMany(Astrophoto::class, 'camera_id');
    }

    public function photosAsMount(): HasMany
    {
        return $this->hasMany(Astrophoto::class, 'mount_id');
    }

    public function photosAsTube(): HasMany
    {
        return $this->hasMany(Astrophoto::class, 'optical_tube_id');
    }

    /**
     * Ajoute photo_uses_count : photos prises avec cet équipement, qu'il soit
     * renseigné directement (caméra, monture, tube) ou actif dans le rig de la
     * photo. Chaque photo n'est comptée qu'une fois.
     */
    public function scopeWithPhotoUsesCount(Builder $query): Builder
    {
        return $query->addSelect(['photo_uses_count' => Astrophoto::query()
            ->selectRaw('count(*)')
            ->where(fn (Builder $photos) => $photos
                ->whereColumn('astrophotos.camera_id', 'setup_items.id')
                ->orWhereColumn('astrophotos.mount_id', 'setup_items.id')
                ->orWhereColumn('astrophotos.optical_tube_id', 'setup_items.id')
                ->orWhereIn('astrophotos.equipment_setup_id', DB::table('equipment_setup_setup_item')
                    ->select('equipment_setup_id')
                    ->whereColumn('equipment_setup_setup_item.setup_item_id', 'setup_items.id')
                    ->where('equipment_setup_setup_item.enabled', true)))]);
    }

    public function equipmentSetups(): BelongsToMany
    {
        return $this->belongsToMany(EquipmentSetup::class)
            ->withPivot(['role', 'position', 'enabled', 'configuration'])
            ->withTimestamps();
    }
}
