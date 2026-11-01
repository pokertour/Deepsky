<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImagingPlanTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'imaging_plan_id',
        'celestial_object_id',
        'planned_start_at',
        'planned_end_at',
        'position',
        'score_snapshot',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_at' => 'immutable_datetime',
            'planned_end_at' => 'immutable_datetime',
            'position' => 'integer',
            'score_snapshot' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function imagingPlan(): BelongsTo
    {
        return $this->belongsTo(ImagingPlan::class);
    }

    public function celestialObject(): BelongsTo
    {
        return $this->belongsTo(CelestialObject::class);
    }
}
