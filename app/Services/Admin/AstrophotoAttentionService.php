<?php

namespace App\Services\Admin;

use App\Enums\AstrometryStatus;
use App\Enums\ConversionStatus;
use App\Models\Astrophoto;

class AstrophotoAttentionService
{
    public function groups(): array
    {
        $groups = ['ready' => [], 'failed' => []];

        foreach (Astrophoto::query()->with('media')->select(['id', 'is_published', 'astrometry_status'])->lazyById(200) as $photo) {
            $status = $photo->conversion_status;
            if (! $photo->is_published && $status === ConversionStatus::Ready) {
                $groups['ready'][] = $photo->id;
            }
            if ($status === ConversionStatus::Failed || $photo->astrometry_status === AstrometryStatus::Failed) {
                $groups['failed'][] = $photo->id;
            }
        }

        return $groups;
    }
}
