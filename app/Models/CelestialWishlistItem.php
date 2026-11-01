<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CelestialWishlistItem extends Model
{
    protected $fillable = ['celestial_object_id', 'priority', 'note'];

    protected $attributes = ['priority' => 2];

    public function celestialObject(): BelongsTo
    {
        return $this->belongsTo(CelestialObject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
