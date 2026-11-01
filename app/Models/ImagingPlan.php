<?php

namespace App\Models;

use App\Enums\ImagingPlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ImagingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'observing_location_id',
        'equipment_setup_id',
        'status',
        'notes',
    ];

    protected $attributes = [
        'status' => 'planned',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => ImagingPlanStatus::class,
        ];
    }

    public function observingLocation(): BelongsTo
    {
        return $this->belongsTo(ObservingLocation::class);
    }

    public function equipmentSetup(): BelongsTo
    {
        return $this->belongsTo(EquipmentSetup::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(ImagingPlanTarget::class)->orderBy('position');
    }

    public function imagingSession(): HasOne
    {
        return $this->hasOne(ImagingSession::class);
    }
}
