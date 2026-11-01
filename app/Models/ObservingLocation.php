<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ObservingLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'region',
        'latitude',
        'longitude',
        'altitude',
        'bortle_class',
        'timezone',
        'is_default',
        'horizon_mask',
    ];

    protected $attributes = [
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'altitude' => 'float',
            'bortle_class' => 'integer',
            'is_default' => 'boolean',
            'horizon_mask' => 'array',
        ];
    }

    public function imagingPlans(): HasMany
    {
        return $this->hasMany(ImagingPlan::class);
    }

    public function imagingSessions(): HasMany
    {
        return $this->hasMany(ImagingSession::class);
    }

    public function astrophotos(): HasMany
    {
        return $this->hasMany(Astrophoto::class);
    }
}
