<?php

namespace Tests\Feature;

use App\Console\Commands\WarmR2StorageStats;
use App\Enums\AstrometryStatus;
use App\Enums\ConversionStatus;
use App\Enums\ImagingPlanStatus;
use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\ImagingPlan;
use App\Models\NewsletterSubscriber;
use App\Models\ObservingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    public function test_dashboard_renders_stats_and_chart(): void
    {
        Cache::flush();
        config(['admin.emails' => ['owner@example.com']]);
        $user = User::factory()->create(['email' => 'owner@example.com']);
        Astrophoto::factory()->create(['target' => 'M 31', 'exposure' => '7200', 'captured_at' => now()]);

        $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Cibles uniques')
            ->assertSee('M 31')
            ->assertSee('2h 00')
            ->assertSee('Cette nuit')
            ->assertSee('Santé du catalogue')
            ->assertSee('Photos sans objet lié');
    }

    public function test_dashboard_renders_without_warmed_storage_cache(): void
    {
        Cache::flush();
        config(['admin.emails' => ['owner@example.com']]);

        Storage::shouldReceive('disk')->never();

        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']))
            ->get(route('admin.dashboard'))->assertOk()->assertSee('Statistiques momentanément indisponibles');
        $this->assertNull(Cache::get(WarmR2StorageStats::CACHE_KEY));
    }

    public function test_dashboard_reads_cached_storage_stats(): void
    {
        Cache::put(WarmR2StorageStats::CACHE_KEY, ['size' => 7, 'count' => 2]);
        config(['admin.emails' => ['owner@example.com']]);
        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']))
            ->get(route('admin.dashboard'))->assertOk()->assertSee('7 B · 2 fichiers');
    }

    public function test_dashboard_compares_two_disjoint_thirty_day_periods(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(12, 0));
        config(['admin.emails' => ['owner@example.com']]);
        Astrophoto::factory()->create(['created_at' => now()->subDays(29)->startOfDay(), 'captured_at' => now()->subDays(29), 'exposure' => 7200]);
        Astrophoto::factory()->create(['is_published' => false, 'created_at' => now()->subDays(30)->endOfDay(), 'captured_at' => now()->subDays(30), 'exposure' => 3600]);
        Astrophoto::factory()->create(['created_at' => now()->subDays(60), 'captured_at' => now()->subDays(60), 'exposure' => 90000]);
        NewsletterSubscriber::factory()->unconfirmed()->create(['created_at' => now()->subDays(1)]);
        NewsletterSubscriber::factory()->create(['created_at' => now()->subDays(40), 'confirmed_at' => now()->subDays(1)]);

        $response = $this->actingAs(User::factory()->create(['email' => 'owner@example.com']))
            ->get(route('admin.dashboard'))->assertOk()->assertSee('Les 30 derniers jours')->assertSee('2 publiées · 1 brouillons');
        $stats = $response->viewData('stats');
        $this->assertSame(['photos' => 1, 'exposure' => 7200, 'subscribers' => 1, 'confirmations' => 1], $stats['periods']['current']);
        $this->assertSame(['photos' => 1, 'exposure' => 3600, 'subscribers' => 1, 'confirmations' => 0], $stats['periods']['previous']);
    }

    public function test_dashboard_alerts_and_filters_share_conversion_readiness_rules(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        $ready = Astrophoto::factory()->create(['title' => 'Prête Rosette', 'is_published' => false]);
        $this->addMedia($ready, 'astros', ['thumb' => true, 'optimized' => true, 'full_res' => true]);
        $queued = Astrophoto::factory()->create(['title' => 'Attente Rosette', 'is_published' => false]);
        $this->addMedia($queued, 'astros', ['thumb' => true, 'optimized' => true, 'full_res' => true]);
        $this->addMedia($queued, 'starless', []);
        $failed = Astrophoto::factory()->create(['title' => 'Échec publié', 'is_published' => true]);
        $this->addMedia($failed, 'astros', [], ['processing_status' => ConversionStatus::Converting->value, 'processing_started_at' => now()->subMinutes(31)->toIso8601String()]);
        $astrometry = Astrophoto::factory()->create(['title' => 'Échec astrométrie', 'astrometry_status' => AstrometryStatus::Failed]);
        $subscriber = NewsletterSubscriber::factory()->unconfirmed()->create();

        $response = $this->actingAs(User::factory()->create(['email' => 'owner@example.com']))->get(route('admin.dashboard'))->assertOk();
        $alerts = $response->viewData('alerts');
        $this->assertSame([1, 2, 4, 1], array_column($alerts, 'count'));
        $response->assertSee(e(route('admin.astrophotos.index', ['attention' => 'ready'])), false);
        $this->get($alerts[0]['url'])->assertOk()->assertSee($ready->title)->assertDontSee($queued->title)->assertDontSee($failed->title);
        $this->get($alerts[1]['url'])->assertOk()->assertSee($failed->title)->assertSee($astrometry->title)->assertDontSee($ready->title);
        $this->get($alerts[3]['url'])->assertOk()->assertSee($subscriber->email);
    }

    private function addMedia(Astrophoto $photo, string $collection, array $conversions, array $properties = []): void
    {
        $photo->media()->create([
            'collection_name' => $collection,
            'name' => 'test', 'file_name' => 'test.jpg', 'mime_type' => 'image/jpeg',
            'disk' => 's3', 'conversions_disk' => 's3', 'size' => 1,
            'manipulations' => [], 'custom_properties' => $properties,
            'generated_conversions' => $conversions, 'responsive_images' => [],
        ]);
    }

    public function test_attention_filters_are_applied_before_pagination_and_can_be_empty(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        foreach (Astrophoto::factory()->count(13)->create(['is_published' => false]) as $photo) {
            $this->addMedia($photo, 'astros', ['thumb' => true, 'optimized' => true, 'full_res' => true]);
        }
        Astrophoto::factory()->count(3)->create(['is_published' => false]);

        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']));
        $response = $this->get(route('admin.astrophotos.index', ['attention' => 'ready', 'page' => 2]))->assertOk();
        $this->assertSame(13, $response->viewData('photos')->total());
        $this->assertCount(1, $response->viewData('photos')->items());
        $this->assertSame(0, $this->get(route('admin.astrophotos.index', ['attention' => 'failed']))->assertOk()->viewData('photos')->total());
    }

    public function test_dashboard_shows_tonight_snapshot_and_upcoming_plan(): void
    {
        Cache::flush();
        config([
            'admin.emails' => ['owner@example.com'],
            'nightscope.weather.driver' => 'none',
        ]);

        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $object = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => 'DASH',
            'name' => 'Cible dashboard',
            'type' => 'galaxy',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
            'magnitude' => 5,
        ]);
        $plan = ImagingPlan::factory()->create([
            'date' => now()->toDateString(),
            'status' => ImagingPlanStatus::Planned,
        ]);
        $plan->targets()->create([
            'celestial_object_id' => $object->id,
            'position' => 1,
            'planned_start_at' => now()->setTime(22, 0),
            'planned_end_at' => now()->setTime(23, 30),
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Night score')
            ->assertSee('Cible dashboard')
            ->assertSee('Prochaine session')
            ->assertSee('NGC DASH');
    }
}
