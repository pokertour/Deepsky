<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImagingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_plan_id',
        'observing_location_id',
        'equipment_setup_id',
        'started_at',
        'ended_at',
        'weather_data',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'weather_data' => 'array',
        ];
    }

    public function imagingPlan(): BelongsTo
    {
        return $this->belongsTo(ImagingPlan::class);
    }

    public function observingLocation(): BelongsTo
    {
        return $this->belongsTo(ObservingLocation::class);
    }

    public function equipmentSetup(): BelongsTo
    {
        return $this->belongsTo(EquipmentSetup::class);
    }

    public function astrophotos(): HasMany
    {
        return $this->hasMany(Astrophoto::class);
    }
}
