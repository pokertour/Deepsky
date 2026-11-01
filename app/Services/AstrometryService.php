<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AstrometryService
{
    private const BASE_URL = 'https://nova.astrometry.net/api';

    /**
     * S'authentifie avec la clé API et retourne une clé de session.
     */
    public function login(): string
    {
        $apiKey = config('services.astrometry.key');

        if (blank($apiKey)) {
            throw new RuntimeException('ASTROMETRY_API_KEY manquante dans le .env');
        }

        $response = $this->request()->post(self::BASE_URL.'/login', [
            'request-json' => json_encode(['apikey' => $apiKey]),
        ])->throw()->json();

        if (($response['status'] ?? null) !== 'success') {
            throw new RuntimeException('Astrometry login failed: '.json_encode($response));
        }

        return $response['session'];
    }

    /**
     * Soumet le contenu d'une image en upload direct (multipart),
     * sans dépendre d'une URL publique. Retourne l'ID de soumission.
     */
    public function submitFile(string $session, string $contents, string $filename): int
    {
        $response = Http::connectTimeout(10)
            ->timeout(120)
            ->attach('file', $contents, $filename)
            ->post(self::BASE_URL.'/upload', [
                'request-json' => json_encode([
                    'session' => $session,
                    'publicly_visible' => 'n',
                    'allow_modifications' => 'n',
                    'allow_commercial_use' => 'n',
                ]),
            ])->throw()->json();

        if (($response['status'] ?? null) !== 'success') {
            throw new RuntimeException('Astrometry submission failed: '.json_encode($response));
        }

        return (int) $response['subid'];
    }

    /**
     * État d'une soumission : job ID s'il existe, et message d'erreur éventuel.
     *
     * @return array{job_id: int|null, error: string|null}
     */
    public function getSubmission(int $submissionId): array
    {
        $response = $this->request()
            ->get(self::BASE_URL.'/submissions/'.$submissionId)
            ->throw()
            ->json();

        $jobId = collect($response['jobs'] ?? [])->filter()->first();

        return [
            'job_id' => $jobId !== null ? (int) $jobId : null,
            'error' => $response['error_message'] ?? null,
        ];
    }

    /**
     * Statut d'un job : "success", "failure" ou "solving".
     */
    public function getJobStatus(int $jobId): string
    {
        $response = $this->request()
            ->get(self::BASE_URL.'/jobs/'.$jobId)
            ->throw()
            ->json();

        return $response['status'] ?? 'solving';
    }

    /**
     * Résultat complet d'un job résolu : calibration et objets identifiés.
     *
     * @return array{ra: float, dec: float, pixel_scale: float, field_radius: float, orientation: float, objects_in_field: list<string>}
     */
    public function getJobResult(int $jobId): array
    {
        $info = $this->request()
            ->get(self::BASE_URL.'/jobs/'.$jobId.'/info/')
            ->throw()
            ->json();

        $calibration = $info['calibration'] ?? [];

        return [
            'ra' => (float) ($calibration['ra'] ?? 0),
            'dec' => (float) ($calibration['dec'] ?? 0),
            'pixel_scale' => (float) ($calibration['pixscale'] ?? 0),
            'field_radius' => (float) ($calibration['radius'] ?? 0),
            'orientation' => (float) ($calibration['orientation'] ?? 0),
            'objects_in_field' => array_values($info['objects_in_field'] ?? []),
        ];
    }

    /**
     * Annotations du champ : position pixel (relative à l'image soumise) des
     * objets identifiés. Seuls les objets de catalogue profond sont conservés.
     *
     * @return list<array{name: string, x: float, y: float, r: float}>
     */
    public function getAnnotations(int $jobId): array
    {
        $response = $this->request()
            ->get(self::BASE_URL.'/jobs/'.$jobId.'/annotations/')
            ->throw()
            ->json();

        return collect($response['annotations'] ?? [])
            ->filter(fn (array $annotation): bool => in_array($annotation['type'] ?? '', ['ngc', 'ic', 'messier', 'bright'], true))
            ->map(fn (array $annotation): array => [
                'name' => implode(' / ', $annotation['names'] ?? []),
                'x' => round((float) $annotation['pixelx'], 1),
                'y' => round((float) $annotation['pixely'], 1),
                'r' => round((float) $annotation['radius'], 1),
            ])
            ->values()
            ->all();
    }

    private function request(): PendingRequest
    {
        return Http::asForm()->connectTimeout(10)->timeout(30)->retry(2, 500);
    }
}
