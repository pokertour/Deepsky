<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AstrophotoStarlessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com'], 'services.astrometry.key' => null]);
        Storage::fake('s3');
    }

    public function test_admin_can_store_a_photo_with_a_starless_version(): void
    {
        $this->actingAs($this->admin())->post(route('admin.astrophotos.store'), [
            'title' => 'Nébuleuse de la Rosette',
            'captured_at' => '2026-09-06',
            'image' => UploadedFile::fake()->image('rosette.jpg', 400, 300),
            'starless_image' => UploadedFile::fake()->image('rosette-starless.jpg', 400, 300),
        ])->assertRedirect(route('admin.astrophotos.index'));

        $photo = Astrophoto::query()->firstOrFail();

        $this->assertTrue($photo->hasMedia('astros'));
        $this->assertTrue($photo->hasMedia('starless'));
        $this->assertSame('rosette-starless.jpg', $photo->getFirstMedia('starless')->file_name);
    }

    public function test_starless_version_is_optional(): void
    {
        $this->actingAs($this->admin())->post(route('admin.astrophotos.store'), [
            'title' => 'M 31',
            'captured_at' => '2026-09-06',
            'image' => UploadedFile::fake()->image('m31.jpg', 400, 300),
        ])->assertRedirect(route('admin.astrophotos.index'));

        $this->assertFalse(Astrophoto::query()->firstOrFail()->hasMedia('starless'));
    }

    public function test_starless_upload_rejects_non_image_files(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.astrophotos.create'))
            ->post(route('admin.astrophotos.store'), [
                'title' => 'M 31',
                'captured_at' => '2026-09-06',
                'image' => UploadedFile::fake()->image('m31.jpg', 400, 300),
                'starless_image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.astrophotos.create'))
            ->assertSessionHasErrors('starless_image')
            ->assertSessionHasInput('title', 'M 31');

        $this->assertSame(0, Astrophoto::query()->count());
    }

    public function test_updating_replaces_the_starless_version_without_touching_the_main_image(): void
    {
        $photo = $this->photoWithStarless();
        $mainMediaId = $photo->getFirstMedia('astros')->id;

        $this->actingAs($this->admin())->put(route('admin.astrophotos.update', $photo), [
            'title' => $photo->title,
            'captured_at' => '2026-09-06',
            'starless_image' => UploadedFile::fake()->image('new-starless.jpg', 400, 300),
        ])->assertRedirect(route('admin.astrophotos.index'));

        $photo->refresh();

        $this->assertCount(1, $photo->getMedia('starless'));
        $this->assertSame('new-starless.jpg', $photo->getFirstMedia('starless')->file_name);
        $this->assertSame($mainMediaId, $photo->getFirstMedia('astros')->id);
    }

    public function test_admin_can_remove_the_starless_version(): void
    {
        $photo = $this->photoWithStarless();

        $this->actingAs($this->admin())->put(route('admin.astrophotos.update', $photo), [
            'title' => $photo->title,
            'captured_at' => '2026-09-06',
            'remove_starless' => '1',
        ])->assertRedirect(route('admin.astrophotos.index'));

        $photo->refresh();

        $this->assertFalse($photo->hasMedia('starless'));
        $this->assertTrue($photo->hasMedia('astros'));
    }

    public function test_updating_without_starless_fields_keeps_the_existing_version(): void
    {
        $photo = $this->photoWithStarless();

        $this->actingAs($this->admin())->put(route('admin.astrophotos.update', $photo), [
            'title' => 'Nouveau titre',
            'captured_at' => '2026-09-06',
        ])->assertRedirect(route('admin.astrophotos.index'));

        $this->assertTrue($photo->refresh()->hasMedia('starless'));
    }

    public function test_full_page_offers_the_starless_switch_when_available(): void
    {
        $photo = $this->photoWithStarless();

        $this->withoutVite()
            ->get(route('astrophoto.full', $photo))
            ->assertOk()
            ->assertSee('id="starless-switch"', false)
            ->assertSee($photo->getFirstMediaUrl('astros', 'full_res'), false);
    }

    public function test_full_page_can_open_directly_on_the_starless_version(): void
    {
        $photo = $this->photoWithStarless();

        $this->withoutVite()
            ->get(route('astrophoto.full', [$photo, 'starless' => 1]))
            ->assertOk()
            ->assertSee('src="'.$photo->getFirstMediaUrl('starless', 'full_res').'"', false);
    }

    public function test_full_page_hides_the_starless_switch_without_starless_version(): void
    {
        $photo = Astrophoto::factory()->create();

        $this->withoutVite()
            ->get(route('astrophoto.full', [$photo, 'starless' => 1]))
            ->assertOk()
            ->assertDontSee('id="starless-switch"', false)
            ->assertSee('Retour')
            ->assertSee('Copier le lien');
    }

    public function test_gallery_exposes_starless_and_share_url_to_the_modal(): void
    {
        $photo = $this->photoWithStarless();

        $this->withoutVite()
            ->get(route('welcome'))
            ->assertOk()
            ->assertSee('Copier le lien')
            ->assertSee('id="starless-switch"', false)
            ->assertSee(e(json_encode(route('astrophoto.show', $photo))), false);
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'owner@example.com']);
    }

    private function photoWithStarless(): Astrophoto
    {
        $photo = Astrophoto::factory()->create();
        $photo->addMedia(UploadedFile::fake()->image('main.jpg', 400, 300))->toMediaCollection('astros', 's3');
        $photo->addMedia(UploadedFile::fake()->image('starless.jpg', 400, 300))->toMediaCollection('starless', 's3');

        return $photo->refresh();
    }
}
