<?php

namespace App\Services\Gemini;

use App\Settings\IntegrationSettings;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Appel texte → texte à l'API Gemini, avec des messages d'erreur lisibles
 * dans l'administration (DomainException).
 */
class GeminiClient
{
    public function __construct(private IntegrationSettings $settings) {}

    public function isConfigured(): bool
    {
        return filled($this->settings->gemini_api_key);
    }

    public function model(): string
    {
        return $this->settings->gemini_model;
    }

    public function generate(string $instructions, string $prompt, bool $json = false): string
    {
        if (! $this->isConfigured()) {
            throw new DomainException(__('Ajoute une clé API Gemini dans les paramètres pour générer une présentation.'));
        }

        try {
            $response = Http::connectTimeout(5)->timeout(30)
                ->retry(2, 300, fn ($exception): bool => $exception instanceof ConnectionException, throw: false)
                ->withHeaders(['x-goog-api-key' => $this->settings->gemini_api_key])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($this->model()).':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $instructions]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => $this->generationConfig($json),
                ]);
        } catch (ConnectionException) {
            throw new DomainException(__('Gemini ne répond pas pour le moment. Réessaie dans quelques instants.'));
        }

        if ($response->status() === 429) {
            throw new DomainException(__('Le quota Gemini est atteint. Attends avant de réessayer ou vérifie tes limites dans Google AI Studio.'));
        }
        if (! $response->successful()) {
            throw new DomainException(__('La génération Gemini a échoué. Vérifie la clé API et le modèle dans les paramètres.'));
        }

        $candidate = $response->json('candidates.0', []);
        if (! is_array($candidate)) {
            throw new DomainException(__('Gemini n’a pas fourni de présentation complète. Réessaie dans quelques instants.'));
        }
        if (($candidate['finishReason'] ?? null) === 'MAX_TOKENS') {
            throw new DomainException(__('Gemini a atteint la limite de génération avant de terminer le texte. Réessaie ou choisis un modèle Flash dans les paramètres.'));
        }
        if (filled($response->json('promptFeedback.blockReason')) || in_array($candidate['finishReason'] ?? null, ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII'], true)) {
            throw new DomainException(__('Gemini a bloqué cette proposition. Vérifie la fiche de l’objet ou réessaie avec un autre modèle.'));
        }
        $text = trim(collect($candidate['content']['parts'] ?? [])
            ->filter(fn ($part) => is_array($part) && ! ($part['thought'] ?? false) && is_string($part['text'] ?? null))
            ->pluck('text')->implode("\n"));

        if (($candidate['finishReason'] ?? null) !== 'STOP' || blank($text) || Str::length($text) > 20000) {
            throw new DomainException(__('Gemini n’a pas fourni de présentation complète. Réessaie dans quelques instants.'));
        }

        return $text;
    }

    /** @return array<string, mixed> */
    private function generationConfig(bool $json): array
    {
        $config = ['temperature' => 0.3, 'maxOutputTokens' => 4096];

        if ($json) {
            $config['responseMimeType'] = 'application/json';
        }

        if (Str::startsWith($this->model(), 'gemini-3')) {
            $config['thinkingConfig'] = ['thinkingLevel' => 'LOW'];
        } elseif (Str::startsWith($this->model(), 'gemini-2.5-flash')) {
            $config['thinkingConfig'] = ['thinkingBudget' => 0];
        }

        return $config;
    }
}
