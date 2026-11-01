<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_unknown_url_displays_the_custom_404_page(): void
    {
        $this->get('/une-page-qui-n-existe-pas')
            ->assertNotFound()
            ->assertSee('Perdu dans les étoiles ?')
            ->assertSee('Retour à la galerie')
            ->assertSee('noindex, nofollow')
            ->assertSee(asset('errors.css'));
    }

    #[DataProvider('errorStatuses')]
    public function test_http_errors_use_custom_pages_and_preserve_headers(int $status, string $title): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/error', fn () => abort($status, 'Private exception details', ['Retry-After' => '120']));

        $this->get('/_test/error')
            ->assertStatus($status)
            ->assertHeader('Retry-After', '120')
            ->assertSee($title)
            ->assertSee('Retour à la galerie')
            ->assertDontSee('Private exception details')
            ->assertDontSee('/build/');
    }

    public static function errorStatuses(): array
    {
        return [
            [401, 'Connexion requise'],
            [403, 'Ce secteur est réservé'],
            [404, 'Perdu dans les étoiles ?'],
            [405, 'Une autre trajectoire est nécessaire'],
            [419, 'Votre session s’est éclipsée'],
            [429, 'Un peu de calme sous les étoiles'],
            [500, 'Une perturbation dans le cosmos'],
            [502, 'Le signal s’est perdu'],
            [503, 'Une pause sous les étoiles'],
            [504, 'Le signal se fait attendre'],
            [410, 'Cette trajectoire est inaccessible'],
            [507, 'Une perturbation dans le cosmos'],
        ];
    }

    public function test_unexpected_exception_uses_the_500_page_without_exposing_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/crash', fn () => throw new RuntimeException('Private server details'));

        $this->get('/_test/crash')
            ->assertStatus(500)
            ->assertSee('Une perturbation dans le cosmos')
            ->assertDontSee('Private server details');
    }

    public function test_json_errors_remain_json(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/json-error', fn () => abort(503, 'Service unavailable'));

        $this->getJson('/_test/json-error')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Service unavailable')
            ->assertDontSee('<html');
    }
}
