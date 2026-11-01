<?php

namespace Tests\Feature;

use App\Console\Commands\WarmR2StorageStats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarmR2StorageStatsTest extends TestCase
{
    public function test_it_caches_storage_size_and_file_count(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('a.jpg', 'xxxxx');
        Storage::disk('s3')->put('nested/b.jpg', 'yy');

        $this->artisan('r2:warm-stats')->assertSuccessful();

        $cached = Cache::get(WarmR2StorageStats::CACHE_KEY);

        $this->assertSame(2, $cached['count']);
        $this->assertSame(7, $cached['size']);
    }
}
