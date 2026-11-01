<?php

namespace Tests\Feature;

use App\Console\Commands\ImportCelestialCatalog;
use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_release_is_imported_only_once(): void
    {
        config([
            'nightscope.catalog.release_api_url' => 'https://api.example.test/openngc/latest',
            'nightscope.catalog.release_download_url' => 'https://download.example.test/{version}/NGC.csv',
            'nightscope.catalog.addendum_download_url' => 'https://download.example.test/{version}/addendum.csv',
        ]);
        Cache::forget(ImportCelestialCatalog::VERSION_CACHE_KEY);
        Http::preventStrayRequests();
        Http::fake([
            'api.example.test/openngc/latest' => Http::response(['tag_name' => 'v20260801']),
            'download.example.test/v20260801/NGC.csv' => Http::response($this->catalogCsv()),
            'download.example.test/v20260801/addendum.csv' => Http::response($this->addendumCsv()),
        ]);

        $this->artisan('nightscope:import-catalog --latest')->assertSuccessful();
        $this->artisan('nightscope:import-catalog --latest')
            ->expectsOutput('Catalogue déjà à jour (v20260801).')
            ->assertSuccessful();

        $this->assertSame('v20260801', Cache::get(ImportCelestialCatalog::VERSION_CACHE_KEY));
        $this->assertSame(3, CelestialObject::query()->count());
        $this->assertDatabaseHas('celestial_objects', ['catalog' => 'IC', 'catalog_number' => '405']);
        $northAmerica = CelestialObject::query()->where('catalog', 'NGC')->where('catalog_number', '7000')->firstOrFail();
        $this->assertContains('C 20', $northAmerica->alternative_names);
        $this->assertContains('Sh2-117', $northAmerica->alternative_names);
        Http::assertSentCount(4);
    }

    public function test_catalog_update_is_scheduled_monthly(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('nightscope:import-catalog --latest')
            ->assertSuccessful();
    }

    public function test_weather_forecast_is_warmed_hourly(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('nightscope:warm-weather')
            ->assertSuccessful();
    }

    public function test_admin_catalog_filters_support_aliases_and_photographed_state(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $object = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => '7000',
            'name' => 'North America Nebula',
            'type' => 'nebula',
            'constellation' => 'Cyg',
            'magnitude' => 4.0,
            'angular_width_arcmin' => 120,
            'alternative_names' => ['C 20', 'Caldwell 20', 'Sh2-117'],
        ]);
        CelestialObject::factory()->create([
            'catalog' => 'IC',
            'catalog_number' => '405',
            'type' => 'nebula',
            'constellation' => 'Aur',
            'magnitude' => 6.0,
            'angular_width_arcmin' => 30,
            'alternative_names' => ['Flaming Star Nebula'],
        ]);
        Astrophoto::factory()->create(['celestial_object_id' => $object->id]);

        $this->actingAs($user)
            ->get(route('admin.celestial-objects.index', [
                'search' => 'C 20',
                'type' => 'nebula',
                'photographed' => 'yes',
                'min_size' => 60,
            ]))
            ->assertOk()
            ->assertSee('NGC 7000')
            ->assertDontSee('IC 405');
    }

    private function catalogCsv(): string
    {
        return <<<'CSV'
Name;Type;RA;Dec;Const;MajAx;MinAx;PosAng;B-Mag;V-Mag;J-Mag;H-Mag;K-Mag;SurfBr;Hubble;Pax;Pm-RA;Pm-Dec;RadVel;Redshift;Cstar U-Mag;Cstar B-Mag;Cstar V-Mag;M;NGC;IC;Cstar Names;Identifiers;Common names;NED notes;OpenNGC notes;Sources
NGC7000;HII;20:59:17.14;+44:31:43.6;Cyg;120.00;30.00;;4.00;;;;;;;;;;;;;;;;;;;C 020,LBN 373,Sh2-117;North America Nebula;;;OpenNGC
IC0405;Neb;05:16:05.00;+34:27:49.0;Aur;30.00;20.00;;6.00;;;;;;;;;;;;;;;;;;;LBN 795;Flaming Star Nebula;;;OpenNGC
CSV;
    }

    private function addendumCsv(): string
    {
        return <<<'CSV'
Name;Type;RA;Dec;Const;MajAx;MinAx;PosAng;B-Mag;V-Mag;J-Mag;H-Mag;K-Mag;SurfBr;Hubble;Pax;Pm-RA;Pm-Dec;RadVel;Redshift;Cstar U-Mag;Cstar B-Mag;Cstar V-Mag;M;NGC;IC;Cstar Names;Identifiers;Common names;NED notes;OpenNGC notes;Sources
M045;OCl;03:47:00.00;+24:07:00.0;Tau;110.00;110.00;;;1.60;;;;;;;;;;;;;;45;;;;Melotte 22;Pleiades;;;OpenNGC
CSV;
    }
}
