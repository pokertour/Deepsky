<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Identifie un visiteur anonyme sans stocker son IP : empreinte HMAC
 * (clé applicative) de l'IP et du navigateur, et détection des robots
 * pour ne pas gonfler vues, likes et visites.
 */
class VisitorFingerprint
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|archiver|facebookexternalhit|embedly|preview|lighthouse|headless|phantom|curl|wget|python|httpclient|go-http|java\/|okhttp|axios|node-fetch|scrapy|feedfetcher|monitor|uptime/i';

    public static function isBot(Request $request): bool
    {
        $userAgent = (string) $request->userAgent();

        return $userAgent === '' || preg_match(self::BOT_PATTERN, $userAgent) === 1;
    }

    public static function hash(Request $request): string
    {
        return hash_hmac('sha256', $request->ip().'|'.$request->userAgent(), (string) config('app.key'));
    }
}
