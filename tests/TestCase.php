<?php

namespace Tests;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        putenv('APP_ENV=testing');
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
        $_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = ':memory:';

        $app = parent::createApplication();
        $app->detectEnvironment(fn (): string => 'testing');
        $app->make('config')->set('database.default', 'sqlite');
        $app->make('config')->set('database.connections.sqlite.database', ':memory:');

        if (! $app->environment('testing')
            || $app->make('config')->get('database.default') !== 'sqlite'
            || $app->make('config')->get('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('Tests must run against the isolated in-memory SQLite database.');
        }

        return $app;
    }

    /**
     * Aucun test ne doit joindre un service externe (SIMBAD, météo…) :
     * les appels non simulés échouent au lieu de partir sur le réseau, et
     * les pauses des retry() HTTP sont neutralisées.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Sleep::fake();
        config(['nightscope.distances.simbad_tap_mirrors' => []]);

        // Le client de test annonce « en-us » par défaut : on simule un navigateur français.
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9');
    }

    /**
     * Réponse TAP de SIMBAD pour la requête groupée : une mesure de distance pour
     * le premier identifiant demandé (ou aucune ligne quand $distance est null).
     */
    protected function simbadDistanceResponse(?string $distance = '0.78', string $unit = 'Mpc'): Closure
    {
        return function (Request $request) use ($distance, $unit) {
            preg_match("/IN \\('((?:[^']|'')*)'/", (string) ($request->data()['QUERY'] ?? urldecode($request->url())), $match);

            return Http::response("id,dist,unit\n".($distance === null ? '' : str_replace("''", "'", $match[1] ?? '').",{$distance},{$unit}\n"));
        };
    }
}
