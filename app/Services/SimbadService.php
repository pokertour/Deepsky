<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SimbadService
{
    /**
     * Résout un identifiant de catalogue (ex: M81, NGC 7000) via SIMBAD.
     *
     * @return array{title: string, id: string, type: string}|null
     */
    public static function lookup(string $identifier): ?array
    {
        $identifier = Str::squish($identifier);

        if ($identifier === '') {
            return null;
        }

        return Cache::remember(
            'simbad:'.Str::lower($identifier),
            now()->addDay(),
            fn (): ?array => self::fetch($identifier),
        );
    }

    /**
     * Convertit un type d'objet SIMBAD (ex: "Seyfert2", "HIIReg") en tag de la galerie.
     */
    public static function tagForType(string $type): ?string
    {
        $type = Str::lower($type);

        return match (true) {
            Str::contains($type, ['gal', 'seyfert', 'sy1', 'sy2', 'agn', 'qso']) => 'galaxy',
            Str::contains($type, ['neb', 'h ii', 'hii', 'ism', 'snr']) => 'nebula',
            Str::contains($type, ['cl*', 'cluster', 'glc', 'opc']) => 'cluster',
            Str::contains($type, ['sn ', 'supernova']) => 'supernova',
            Str::contains($type, ['planet']) => 'planets',
            default => null,
        };
    }

    /**
     * @return array{title: string, id: string, type: string}|null
     */
    private static function fetch(string $identifier): ?array
    {
        $script = "format object \"%IDLIST(1)|%IDLIST(A;LGG,NAME,NOGG)|%otype(S)\"\nquery id ".$identifier;

        try {
            $response = Http::asForm()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(2, 200)
                ->post('https://simbad.cds.unistra.fr/simbad/sim-script', [
                    'script' => $script,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $content = $response->body();

        // On isole les lignes après le marqueur ::data::
        $dataSection = Str::after($content, '::data::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::');
        $lines = explode("\n", trim($dataSection));
        $dataLine = $lines[0] ?? null;

        if (! $dataLine || ! str_contains($dataLine, '|')) {
            return null;
        }

        // On garantit 3 segments pour éviter "Undefined array key".
        [$id, $aliases, $type] = array_pad(explode('|', $dataLine, 3), 3, '');

        // On transforme la chaîne d'alias en tableau
        // "NAME M 81*, NAME Bode's Galaxy" -> ["NAME M 81*", "NAME Bode's Galaxy"]
        $aliasArray = explode(',', $aliases);

        $cleanTitle = trim(str_replace('NAME ', '', $id)); // Valeur par défaut

        foreach ($aliasArray as $alias) {
            $currentName = trim(str_replace('NAME ', '', $alias));

            // STRATÉGIE : on retient le premier nom commun (avec lettres),
            // en écartant les simples matricules de type "M 81".
            if (strlen($currentName) > strlen($cleanTitle) && ! preg_match('/^[A-Z]\s?\d+$/', $currentName)) {
                $cleanTitle = $currentName;
                break;
            }
        }

        return [
            'title' => trim($cleanTitle), // ex: Bode's Galaxy
            'id' => trim(preg_replace('/\s+/', ' ', $id)), // ex: M 81
            'type' => trim($type), // ex: Seyfert2
        ];
    }
}
