<?php

namespace Tests\Feature;

use App\Enums\AstrometryStatus;
use App\Jobs\SolveAstrophotoJob;
use App\Mail\ConfirmNewsletterSubscription;
use App\Models\Astrophoto;
use App\Models\NewsletterSubscriber;
use App\Models\SetupItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
        Storage::fake('s3');
    }

    public function test_guest_is_redirected_to_the_custom_login_page(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))->assertOk()->assertSee('Administration console');
    }

    public function test_whitelisted_user_can_log_in_and_view_dashboard(): void
    {
        User::factory()->create(['email' => 'owner@example.com', 'password' => Hash::make('secret-password')]);
        Astrophoto::factory()->create(['title' => 'Galaxie du Tourbillon']);

        $this->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => 'secret-password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Galaxie du Tourbillon')->assertSee('Temps de pose sur 12 mois');
    }

    public function test_non_admin_account_is_rejected(): void
    {
        User::factory()->create(['email' => 'visitor@example.com', 'password' => Hash::make('secret-password')]);

        $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => 'visitor@example.com',
            'password' => 'secret-password',
        ])->assertRedirect(route('admin.login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_can_manage_equipment(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)->post(route('admin.equipment.store'), [
            'name' => 'ASI2600MM Pro',
            'brand' => 'ZWO',
            'type' => 'camera',
        ])->assertRedirect(route('admin.equipment.index'));

        $item = SetupItem::query()->firstOrFail();
        $this->actingAs($user)->put(route('admin.equipment.update', $item), [
            'name' => 'ASI2600MC Pro',
            'brand' => 'ZWO',
            'type' => 'camera',
        ])->assertRedirect(route('admin.equipment.index'));

        $this->assertSame('ASI2600MC Pro', $item->fresh()->name);
    }

    public function test_all_admin_sections_render_for_an_admin(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)->get(route('admin.astrophotos.index'))->assertOk()->assertSee('Vault céleste');
        $this->actingAs($user)->get(route('admin.astrophotos.create'))->assertOk()->assertSee('Identité céleste');
        $this->actingAs($user)->get(route('admin.equipment.index'))->assertOk()->assertSee('Configuration optique');
        $this->actingAs($user)->get(route('admin.newsletter.index'))->assertOk()->assertSee('Audience');
        $this->actingAs($user)->get(route('admin.settings.edit'))->assertOk()->assertSee('Configuration système');
    }

    public function test_admin_can_filter_and_resend_newsletter_confirmation(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $subscriber = NewsletterSubscriber::factory()->unconfirmed()->create(['email' => 'pending@example.com']);

        $this->actingAs($user)->get(route('admin.newsletter.index', ['status' => 'pending']))
            ->assertOk()->assertSee('pending@example.com');
        $this->actingAs($user)->post(route('admin.newsletter.resend', $subscriber))->assertSessionHas('success');

        Mail::assertQueued(ConfirmNewsletterSubscription::class);
    }

    public function test_admin_can_start_astrometry_when_the_key_is_configured(): void
    {
        Bus::fake();
        config(['services.astrometry.key' => 'test-key']);
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $photo = Astrophoto::factory()->create(['astrometry_status' => AstrometryStatus::Idle]);

        $this->actingAs($user)->post(route('admin.astrophotos.solve', $photo))->assertSessionHas('success');

        $this->assertSame(AstrometryStatus::Pending, $photo->refresh()->astrometry_status);
        Bus::assertDispatched(SolveAstrophotoJob::class);
    }

    public function test_astrometry_is_not_started_without_an_api_key(): void
    {
        Bus::fake();
        config(['services.astrometry.key' => null]);
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $photo = Astrophoto::factory()->create(['astrometry_status' => AstrometryStatus::Idle]);

        $this->actingAs($user)->post(route('admin.astrophotos.solve', $photo))->assertSessionHas('error');

        $this->assertSame(AstrometryStatus::Idle, $photo->refresh()->astrometry_status);
        Bus::assertNotDispatched(SolveAstrophotoJob::class);
    }
}
