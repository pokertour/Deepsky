<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class WarmR2StorageStats extends Command
{
    protected $signature = 'r2:warm-stats';

    protected $description = 'Compute R2 storage usage and cache it so the dashboard never blocks.';

    public const CACHE_KEY = 'r2_storage_stats';

    public function handle(): int
    {
        $stats = self::compute();

        $this->components->info(sprintf(
            'R2 stats cached: %d files, %s.',
            $stats['count'],
            Number::fileSize($stats['size']),
        ));

        return self::SUCCESS;
    }

    /**
     * Compute storage stats and store them in the cache without a TTL.
     * The schedule refreshes this in the background, so the widget only reads.
     *
     * @return array{size: int, count: int}
     */
    public static function compute(): array
    {
        $disk = Storage::disk('s3');

        $size = 0;
        $count = 0;

        foreach ($disk->allFiles() as $file) {
            $size += $disk->size($file);
            $count++;
        }

        $stats = ['size' => $size, 'count' => $count];

        Cache::forever(self::CACHE_KEY, $stats);

        return $stats;
    }
}
