<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\User;
use App\Settings\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ObjectDescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.emails' => ['owner@example.com']]);
        Http::preventStrayRequests();
        $settings = app(IntegrationSettings::class);
        $settings->gemini_api_key = 'test-gemini-secret';
        $settings->gemini_model = 'gemini-2.5-flash-lite';
        $settings->save();
        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']));
    }

    private function successResponse(): array
    {
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
            ['text' => 'Une nébuleuse est un vaste nuage de gaz et de poussières.'],
        ]]]]];
    }

    public function test_generation_uses_catalog_facts_and_header_secret_without_modifying_photo(): void
    {
        $object = CelestialObject::factory()->create([
            'catalog' => 'M', 'catalog_number' => '42', 'type' => 'nebula', 'distance_ly' => 1350,
            'metadata' => ['openngc_type' => 'HII', 'source' => 'OpenNGC', 'private_note' => 'Never send this'],
        ]);
        $photo = Astrophoto::factory()->create([
            'celestial_object_id' => $object->id, 'description' => 'Mon récit de capture',
            'object_description' => 'Présentation existante', 'is_published' => false,
        ]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->successResponse())]);

        $this->postJson(route('admin.astrophotos.object-description'), ['celestial_object_id' => $object->id])
            ->assertOk()->assertJsonPath('object_id', $object->id)
            ->assertJsonPath('description', 'Une nébuleuse est un vaste nuage de gaz et de poussières.');
        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'test-gemini-secret')
            && ! str_contains($request->url(), 'test-gemini-secret')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'HII')
            && str_contains($request['contents'][0]['parts'][0]['text'], '1350')
            && ! str_contains($request->body(), 'Never send this')
            && ! str_contains($request->body(), 'Mon récit de capture'));
        $this->assertSame('Mon récit de capture', $photo->fresh()->description);
        $this->assertSame('Présentation existante', $photo->fresh()->object_description);
        $this->assertFalse($photo->fresh()->is_published);
    }

    public function test_successful_suggestions_are_cached_and_catalog_changes_invalidate_cache(): void
    {
        $object = CelestialObject::factory()->create();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->successResponse())]);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson(route('admin.astrophotos.object-description'), ['celestial_object_id' => $object->id])->assertOk();
        }
        Http::assertSentCount(1);
        $object->update(['distance_ly' => 1250]);
        $this->postJson(route('admin.astrophotos.object-description'), ['celestial_object_id' => $object->id])->assertOk();
        Http::assertSentCount(2);
    }

    public function test_missing_key_and_invalid_object_do_not_call_gemini(): void
    {
        $this->postJson(route('admin.astrophotos.object-description'), [])->assertUnprocessable()->assertJsonValidationErrors('celestial_object_id');
        $this->postJson(route('admin.astrophotos.object-description'), ['celestial_object_id' => 999])->assertUnprocessable()->assertJsonValidationErrors('celestial_object_id');
        $settings = app(IntegrationSettings::class);
        $settings->gemini_api_key = null;
        $settings->save();
        $object = CelestialObject::factory()->create();
        $this->postJson(route('admin.astrophotos.object-description'), ['celestial_object_id' => $object->id])
            ->assertUnprocessable()->assertJsonPath('message', 'Ajoute une clé API Gemini dans les paramètres pour générer une présentation.');
        Http::assertNothingSent();
    }

    public function test_quota_errors_are_safe_and_not_cached(): void
    {
        $object = CelestialObject::factory()->create();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'Internal secret message']], 429)->push($this->successResponse())]);
        $this->postJson(route('admin.astrophotos.object-description'), ['celestial_object_id' => $object->id])
            ->assertUnprocessable()->assertSee('quota Gemini')->assertDontSee('Internal secret message');
        $this->postJson(route('admin.astrophotos.object-description'), ['celestial_object_id' => $object->id])->assertOk();
        Http::assertSentCount(2);
    }

    public function test_refused_empty_or_truncated_answers_are_rejected(): void
    {
        $object = CelestialObject::factory()->create();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()->push([])
            ->push(['candidates' => [['finishReason' => 'SAFETY']]])
            ->push(['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => 'Un texte tronqué']]]]]])]);
        foreach ([
            'Gemini n’a pas fourni de présentation complète. Réessaie dans quelques instants.',
            'Gemini a bloqué cette proposition. Vérifie la fiche de l’objet ou réessaie avec un autre modèle.',
            'Gemini a atteint la limite de génération avant de terminer le texte. Réessaie ou choisis un modèle Flash dans les paramètres.',
        ] as $message) {
            $this->postJson(route('admin.astrophotos.object-description'), ['celestial_object_id' => $object->id])
                ->assertUnprocessable()->assertJsonPath('message', $message);
        }
        Http::assertSentCount(3);
    }

    public function test_gemini_3_has_room_for_thinking_and_reduced_reasoning(): void
    {
        $settings = app(IntegrationSettings::class);
        $settings->gemini_model = 'gemini-3-flash-preview';
        $settings->save();
        $object = CelestialObject::factory()->create();
        $answer = $this->successResponse();
        array_unshift($answer['candidates'][0]['content']['parts'], ['thought' => true, 'text' => 'Internal thought summary']);
        $answer['usageMetadata'] = ['thoughtsTokenCount' => 982, 'candidatesTokenCount' => 400];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($answer)]);

        $this->postJson(route('admin.astrophotos.object-description'), ['celestial_object_id' => $object->id])
            ->assertOk()->assertDontSee('Internal thought summary');
        Http::assertSent(fn ($request) => $request['generationConfig']['maxOutputTokens'] === 4096
            && $request['generationConfig']['thinkingConfig'] === ['thinkingLevel' => 'LOW']);
    }

    public function test_gemini_25_flash_uses_compatible_thinking_budget(): void
    {
        $object = CelestialObject::factory()->create();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->successResponse())]);
        $this->postJson(route('admin.astrophotos.object-description'), ['celestial_object_id' => $object->id])->assertOk();
        Http::assertSent(fn ($request) => $request['generationConfig']['maxOutputTokens'] === 4096
            && $request['generationConfig']['thinkingConfig'] === ['thinkingBudget' => 0]);
    }

    public function test_blocked_prompt_without_candidate_returns_clear_error(): void
    {
        $object = CelestialObject::factory()->create();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']])]);
        $this->postJson(route('admin.astrophotos.object-description'), ['celestial_object_id' => $object->id])
            ->assertUnprocessable()->assertJsonPath('message', 'Gemini a bloqué cette proposition. Vérifie la fiche de l’objet ou réessaie avec un autre modèle.');
    }

    public function test_generation_is_admin_only(): void
    {
        $object = CelestialObject::factory()->create();
        $this->actingAs(User::factory()->create())->postJson(route('admin.astrophotos.object-description'), ['celestial_object_id' => $object->id])
            ->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_description_is_saved_separately_and_escaped_on_public_page(): void
    {
        $photo = Astrophoto::factory()->create(['is_published' => false, 'description' => 'Mon récit']);
        $text = 'Une présentation accessible. <script>alert(1)</script>';
        $this->put(route('admin.astrophotos.update', $photo), [
            'title' => $photo->title, 'captured_at' => '2026-10-05', 'object_description' => $text,
        ])->assertSessionHasNoErrors();
        $this->assertSame($text, $photo->fresh()->object_description);
        $this->assertSame('Mon récit', $photo->fresh()->description);
        $this->withoutVite()->get(route('astrophoto.show', $photo))->assertOk()
            ->assertSee('À la découverte de cet objet')->assertSee($text)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_key_is_encrypted_and_not_rendered_in_settings_page(): void
    {
        $rawPayload = DB::table('settings')->where('group', 'integrations')->where('name', 'gemini_api_key')->value('payload');
        $this->assertStringNotContainsString('test-gemini-secret', $rawPayload);
        $this->withoutVite()->get(route('admin.settings.edit'))->assertOk()->assertSee('Une clé Gemini est configurée.')
            ->assertDontSee('test-gemini-secret');
    }
}
