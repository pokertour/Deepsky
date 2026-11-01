<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentSetup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_default',
    ];

    protected $attributes = [
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function setupItems(): BelongsToMany
    {
        return $this->belongsToMany(SetupItem::class)
            ->withPivot(['role', 'position', 'enabled', 'configuration'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function enabledSetupItems(): BelongsToMany
    {
        return $this->setupItems()->wherePivot('enabled', true);
    }

    public function astrophotos(): HasMany
    {
        return $this->hasMany(Astrophoto::class);
    }

    public function imagingPlans(): HasMany
    {
        return $this->hasMany(ImagingPlan::class);
    }

    public function imagingSessions(): HasMany
    {
        return $this->hasMany(ImagingSession::class);
    }
}
