<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use App\Models\User;
use App\Support\AcquisitionData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AstrophotoAcquisitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.emails' => ['owner@example.com']]);
        Storage::fake('s3');
        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']));
    }

    private function sessions(): array
    {
        return [
            ['date' => '2026-08-05', 'camera_mode' => 'mono', 'frames' => [
                ['type' => 'light', 'filter' => 'R', 'count' => 30, 'seconds' => 120],
                ['type' => 'light', 'filter' => 'G', 'count' => 20, 'seconds' => 120],
                ['type' => 'dark', 'filter' => null, 'count' => 50, 'seconds' => 120],
                ['type' => 'flat', 'filter' => 'R', 'count' => 30, 'seconds' => 0.5],
                ['type' => 'bias', 'filter' => null, 'count' => 100, 'seconds' => null],
            ]],
            ['date' => '2026-09-05', 'camera_mode' => 'color', 'frames' => [
                ['type' => 'light', 'filter' => 'B', 'count' => 10, 'seconds' => 60],
            ]],
        ];
    }

    public function test_create_persists_multiple_sessions_and_ignores_submitted_total(): void
    {
        $this->post(route('admin.astrophotos.store'), [
            'title' => 'Acquisition détaillée', 'captured_at' => '2026-09-05',
            'exposure' => '999999', 'acquisition_sessions' => $this->sessions(),
            'image' => UploadedFile::fake()->image('acquisition.jpg', 40, 40),
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.astrophotos.index'));

        $photo = Astrophoto::firstOrFail();
        $this->assertEquals($this->sessions(), $photo->acquisition_sessions);
        $this->assertSame('6600', $photo->exposure);
        $this->assertSame('1h 50m', $photo->formatted_integ);
    }

    public function test_edit_replaces_sessions_and_can_return_to_manual_total(): void
    {
        $photo = Astrophoto::factory()->create(['is_published' => false, 'acquisition_sessions' => $this->sessions()]);
        $this->withoutVite()->get(route('admin.astrophotos.edit', $photo))->assertOk()
            ->assertSee('Sessions et images de calibration')->assertSee('2026-08-05');
        $sessions = $this->sessions();
        $sessions[0]['frames'][0]['count'] = 15;
        $this->put(route('admin.astrophotos.update', $photo), [
            'title' => $photo->title, 'captured_at' => '2026-09-05',
            'acquisition_sessions' => $sessions, 'exposure' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('4800', $photo->fresh()->exposure);

        $this->put(route('admin.astrophotos.update', $photo), [
            'title' => $photo->title, 'captured_at' => '2026-09-05',
            'acquisition_sessions' => '', 'exposure' => '7200',
        ])->assertSessionHasNoErrors();
        $this->assertNull($photo->fresh()->acquisition_sessions);
        $this->assertSame('7200', $photo->fresh()->exposure);
    }

    public function test_invalid_sessions_are_rejected_without_changing_photo(): void
    {
        $photo = Astrophoto::factory()->create(['is_published' => false, 'exposure' => '500']);
        $sessions = $this->sessions();
        $sessions[0]['date'] = '2026-02-30';
        $sessions[0]['camera_mode'] = 'invalid';
        $sessions[0]['frames'][0]['count'] = -1;
        $sessions[0]['frames'][1]['seconds'] = 'invalid';
        $sessions[0]['frames'][2]['type'] = 'invalid';
        $this->put(route('admin.astrophotos.update', $photo), [
            'title' => $photo->title, 'captured_at' => '2026-09-05', 'acquisition_sessions' => $sessions,
        ])->assertSessionHasErrors([
            'acquisition_sessions.0.date', 'acquisition_sessions.0.camera_mode',
            'acquisition_sessions.0.frames.0.count', 'acquisition_sessions.0.frames.1.seconds',
            'acquisition_sessions.0.frames.2.type',
        ]);
        $this->assertSame('500', $photo->fresh()->exposure);
    }

    public function test_light_requires_positive_duration_but_calibration_can_be_count_only(): void
    {
        $photo = Astrophoto::factory()->create(['is_published' => false]);
        foreach ([null, 0] as $seconds) {
            $sessions = $this->sessions();
            $sessions[0]['frames'][0]['seconds'] = $seconds;
            $this->put(route('admin.astrophotos.update', $photo), [
                'title' => $photo->title, 'captured_at' => '2026-09-05', 'acquisition_sessions' => $sessions,
            ])->assertSessionHasErrors('acquisition_sessions.0.frames.0.seconds');
        }
    }

    public function test_fractional_poses_and_legacy_photos_keep_correct_integration(): void
    {
        $photo = Astrophoto::factory()->create(['exposure' => '3661']);
        $this->assertSame('1h 1m 1s', $photo->formatted_integ);
        $this->assertSame('3661', $photo->fresh()->exposure);
        $photo->update(['acquisition_sessions' => [['date' => null, 'camera_mode' => 'color', 'frames' => [
            ['type' => 'light', 'filter' => null, 'count' => 3, 'seconds' => 0.125],
        ]]]]);
        $this->assertSame('0.375', $photo->fresh()->exposure);
        $this->assertSame('0.375s', $photo->formatted_integ);
        $this->assertSame('Sans filtre', AcquisitionData::filterSummary($photo->acquisition_sessions)[0]['filter']);
    }

    public function test_public_page_shows_dates_filters_and_calibration(): void
    {
        $photo = Astrophoto::factory()->create(['acquisition_sessions' => $this->sessions()]);
        $this->withoutVite()->get(route('astrophoto.show', $photo))->assertOk()
            ->assertSee('Sessions d’acquisition')->assertSee('05/08/2026')->assertSee('05/09/2026')
            ->assertSee('Monochrome')->assertSee('Couleur (OSC)')->assertSee('1h 50m')
            ->assertSee('Dark')->assertSee('Flat')->assertSee('Bias')->assertSee('calibration')
            ->assertSee('30 images')->assertSee('× 120 s')->assertDontSee('@if')->assertDontSee('@endif');
    }
}
