<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\StoreAstrophotoRequest;
use App\Models\Astrophoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AstrophotoTiffUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
        Storage::fake('s3');
    }

    public function test_tiff_masters_pass_astrophoto_image_validation(): void
    {
        $validator = Validator::make([
            'title' => 'Andromède',
            'captured_at' => '2026-09-06',
            'image' => $this->tiffUpload(),
        ], (new StoreAstrophotoRequest)->rules());

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_jpeg_uploads_still_pass_image_validation(): void
    {
        $validator = Validator::make([
            'title' => 'Andromède',
            'captured_at' => '2026-09-06',
            'image' => UploadedFile::fake()->image('m31.jpg', 1200, 800),
        ], (new StoreAstrophotoRequest)->rules());

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_admin_can_store_a_tiff_master(): void
    {
        if (! extension_loaded('imagick') || \Imagick::queryFormats('TIFF') === []) {
            $this->markTestSkipped('Imagick TIFF support is required to convert masters.');
        }

        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)->post(route('admin.astrophotos.store'), [
            'title' => 'Master TIFF',
            'captured_at' => '2026-09-06',
            'image' => $this->tiffUpload(),
        ])->assertRedirect(route('admin.astrophotos.index'));

        $photo = Astrophoto::query()->firstOrFail();

        $this->assertTrue($photo->hasMedia('astros'));
        $this->assertSame('image/tiff', $photo->getFirstMedia('astros')?->mime_type);
    }

    private function tiffUpload(string $name = 'm31.tiff'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            file_get_contents(base_path('tests/Fixtures/minimal.tiff')),
        );
    }
}
