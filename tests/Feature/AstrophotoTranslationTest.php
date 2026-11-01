<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use App\Models\User;
use App\Settings\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AstrophotoTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.emails' => ['owner@example.com']]);
        $settings = app(IntegrationSettings::class);
        $settings->gemini_api_key = 'test-gemini-secret';
        $settings->gemini_model = 'gemini-2.5-flash-lite';
        $settings->save();
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'owner@example.com']);
    }

    private function geminiAnswer(string $json, string $finishReason = 'STOP'): array
    {
        return ['candidates' => [['finishReason' => $finishReason, 'content' => ['parts' => [['text' => $json]]]]]];
    }

    public function test_french_texts_are_translated_in_a_single_json_request(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiAnswer(json_encode([
            'title' => 'Iris Nebula',
            'description' => "Two nights of imaging.\nClear sky.",
        ])))]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.astrophotos.translate'), [
                'title' => 'Nébuleuse de l’Iris',
                'description' => "Deux nuits de pose.\nCiel dégagé.",
                'object_description' => '',
            ])
            ->assertOk()
            ->assertExactJson(['translations' => ['title' => 'Iris Nebula', 'description' => "Two nights of imaging.\nClear sky."]]);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-goog-api-key', 'test-gemini-secret')
            && $request['generationConfig']['responseMimeType'] === 'application/json'
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Nébuleuse de l’Iris')
            && ! str_contains($request['contents'][0]['parts'][0]['text'], 'object_description'));
    }

    public function test_unusable_answer_and_missing_texts_are_reported(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiAnswer('pas du JSON'))]);
        $this->actingAs($this->admin());

        $this->postJson(route('admin.astrophotos.translate'), ['title' => 'Nébuleuse de l’Iris'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Gemini n’a pas renvoyé de traduction exploitable. Réessaie dans quelques instants.');

        $this->postJson(route('admin.astrophotos.translate'), ['title' => '', 'description' => ''])
            ->assertJsonValidationErrors(['title' => 'Renseigne au moins le titre ou une description en français.']);
    }

    public function test_guests_cannot_translate(): void
    {
        Http::fake();

        $this->postJson(route('admin.astrophotos.translate'), ['title' => 'Nébuleuse'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_english_texts_are_saved_and_shown_to_english_visitors_with_french_fallback(): void
    {
        $photo = Astrophoto::factory()->create([
            'title' => 'Nébuleuse de l’Iris',
            'description' => 'Deux nuits de pose.',
            'title_en' => 'Iris Nebula',
            'description_en' => null,
        ]);

        $this->get(route('astrophoto.show', ['astrophoto' => $photo, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Iris Nebula')
            ->assertSee('Deux nuits de pose.');

        $this->get(route('astrophoto.show', ['astrophoto' => $photo, 'lang' => 'fr']))
            ->assertOk()
            ->assertSee('Nébuleuse de l’Iris')
            ->assertDontSee('Iris Nebula');
    }
}
