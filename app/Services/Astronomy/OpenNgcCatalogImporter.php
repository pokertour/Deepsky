<?php

namespace App\Services\Astronomy;

use App\Models\CelestialObject;
use Illuminate\Support\Carbon;
use RuntimeException;

class OpenNgcCatalogImporter
{
    /** @return array{processed: int, imported: int, skipped: int} */
    public function import(string $csv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));

        if (! $lines || count($lines) < 2) {
            throw new RuntimeException('Le fichier OpenNGC est vide ou invalide.');
        }

        $headers = str_getcsv((string) array_shift($lines), ';', '"', '');
        $requiredHeaders = ['Name', 'Type', 'RA', 'Dec', 'M'];

        if (array_diff($requiredHeaders, $headers) !== []) {
            throw new RuntimeException('Les colonnes attendues sont absentes du fichier OpenNGC.');
        }

        $processed = 0;
        $skipped = 0;
        $rows = [];
        $now = Carbon::now();

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $processed++;
            $values = str_getcsv($line, ';', '"', '');

            if (count($values) !== count($headers)) {
                $skipped++;

                continue;
            }

            /** @var array<string, string> $source */
            $source = array_combine($headers, $values);
            $row = $this->transform($source, $now);

            if ($row === null) {
                $skipped++;

                continue;
            }

            $rows[] = $row;

            if (count($rows) === 500) {
                $this->upsert($rows);
                $rows = [];
            }
        }

        $this->upsert($rows);

        return [
            'processed' => $processed,
            'imported' => $processed - $skipped,
            'skipped' => $skipped,
        ];
    }

    /** @param array<string, string> $source
     * @return array<string, mixed>|null
     */
    private function transform(array $source, Carbon $now): ?array
    {
        $openNgcName = trim($source['Name']);
        $messierNumber = trim($source['M']);
        $sourceType = trim($source['Type']);

        if (in_array($sourceType, ['NonEx', 'Dup'], true)) {
            return null;
        }

        $identity = $this->primaryIdentity($openNgcName, $messierNumber);

        if ($identity === null) {
            return null;
        }

        [$catalog, $catalogNumber] = $identity;
        $ra = $this->rightAscensionToDegrees($source['RA']);
        $dec = $this->declinationToDegrees($source['Dec']);

        if ($catalogNumber === '' || $ra === null || $dec === null) {
            return null;
        }

        $commonNames = $this->splitNames($source['Common names'] ?? '');
        $alternativeNames = $this->mergeAliases(
            $openNgcName,
            $messierNumber,
            $source['NGC'] ?? '',
            $source['IC'] ?? '',
            $source['Identifiers'] ?? '',
            $commonNames,
        );

        return [
            'catalog' => $catalog,
            'catalog_number' => $catalogNumber,
            'name' => $commonNames[0] ?? null,
            'alternative_names' => json_encode($alternativeNames, JSON_THROW_ON_ERROR),
            'type' => $this->objectType($sourceType),
            'constellation' => $this->nullIfEmpty($source['Const'] ?? ''),
            'ra_deg' => $ra,
            'dec_deg' => $dec,
            'magnitude' => $this->number($source['V-Mag'] ?? '') ?? $this->number($source['B-Mag'] ?? ''),
            'surface_brightness' => $this->number($source['SurfBr'] ?? ''),
            'angular_width_arcmin' => $this->number($source['MajAx'] ?? ''),
            'angular_height_arcmin' => $this->number($source['MinAx'] ?? ''),
            'distance_ly' => null,
            'metadata' => json_encode(array_filter([
                'source' => 'OpenNGC',
                'openngc_name' => $openNgcName,
                'openngc_type' => $sourceType,
                'hubble' => $this->nullIfEmpty($source['Hubble'] ?? ''),
                'position_angle_deg' => $this->number($source['PosAng'] ?? ''),
                'aliases' => $alternativeNames,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''), JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** @return array{0: string, 1: string}|null */
    private function primaryIdentity(string $openNgcName, string $messierNumber): ?array
    {
        if ($messierNumber !== '') {
            return ['M', ltrim($messierNumber, '0')];
        }

        if (str_starts_with($openNgcName, 'NGC')) {
            return ['NGC', ltrim(substr($openNgcName, 3), '0')];
        }

        if (str_starts_with($openNgcName, 'IC')) {
            return ['IC', ltrim(substr($openNgcName, 2), '0')];
        }

        if (preg_match('/^M0*(\d+)$/i', $openNgcName, $matches) === 1) {
            return ['M', $matches[1]];
        }

        return null;
    }

    /**
     * @param  list<string>  $commonNames
     * @return list<string>
     */
    private function mergeAliases(
        string $openNgcName,
        string $messierNumber,
        string $ngcCrossRefs,
        string $icCrossRefs,
        string $identifiers,
        array $commonNames,
    ): array {
        $aliases = [
            $openNgcName !== '' ? $this->normalizeDesignation($openNgcName) : null,
            $messierNumber !== '' ? 'M '.$messierNumber : null,
            ...$this->catalogCrossRefs('NGC', $ngcCrossRefs),
            ...$this->catalogCrossRefs('IC', $icCrossRefs),
            ...$this->splitNames($identifiers),
            ...$this->specialCatalogAliases($identifiers),
            ...$commonNames,
        ];

        $normalized = [];

        foreach ($aliases as $alias) {
            if (! is_string($alias)) {
                continue;
            }

            $alias = trim(preg_replace('/\s+/', ' ', $alias) ?? $alias);

            if ($alias === '') {
                continue;
            }

            $normalized[$alias] = $alias;
        }

        return array_values($normalized);
    }

    /** @return list<string> */
    private function catalogCrossRefs(string $catalog, string $value): array
    {
        $refs = [];

        foreach ($this->splitNames($value) as $ref) {
            $number = ltrim(preg_replace('/^(NGC|IC)\s*/i', '', $ref) ?? $ref, '0');

            if ($number === '') {
                continue;
            }

            $refs[] = $catalog.' '.$number;
        }

        return $refs;
    }

    /** @return list<string> */
    private function specialCatalogAliases(string $identifiers): array
    {
        $aliases = [];

        if (preg_match_all('/\bC(?:aldwell)?[\s-]*0*(\d{1,3})\b/i', $identifiers, $matches) > 0) {
            foreach ($matches[1] as $number) {
                $aliases[] = 'C '.$number;
                $aliases[] = 'Caldwell '.$number;
            }
        }

        if (preg_match_all('/\bSh(?:arp)?(?:less)?[\s-]*2[\s-]*0*(\d{1,4})\b/i', $identifiers, $matches) > 0) {
            foreach ($matches[1] as $number) {
                $aliases[] = 'Sh2-'.$number;
                $aliases[] = 'Sharpless 2-'.$number;
            }
        }

        return $aliases;
    }

    /** @param list<array<string, mixed>> $rows */
    private function upsert(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        CelestialObject::query()->upsert(
            $rows,
            ['catalog', 'catalog_number'],
            [
                'name', 'alternative_names', 'type', 'constellation', 'ra_deg', 'dec_deg',
                'magnitude', 'surface_brightness', 'angular_width_arcmin',
                'angular_height_arcmin', 'metadata', 'updated_at',
            ]
        );
    }

    private function rightAscensionToDegrees(string $value): ?float
    {
        $parts = array_map('floatval', explode(':', trim($value)));

        return count($parts) === 3 ? round(($parts[0] + $parts[1] / 60 + $parts[2] / 3600) * 15, 7) : null;
    }

    private function declinationToDegrees(string $value): ?float
    {
        $value = trim($value);
        $parts = array_map('floatval', explode(':', ltrim($value, '+-')));

        if (count($parts) !== 3) {
            return null;
        }

        $degrees = $parts[0] + $parts[1] / 60 + $parts[2] / 3600;

        return round(str_starts_with($value, '-') ? -$degrees : $degrees, 7);
    }

    /** @return list<string> */
    private function splitNames(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function normalizeDesignation(string $value): string
    {
        return preg_replace('/^(NGC|IC)0+/', '$1 ', $value) ?? $value;
    }

    private function number(string $value): ?float
    {
        $value = trim($value);

        return $value !== '' && is_numeric($value) ? (float) $value : null;
    }

    private function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function objectType(string $type): string
    {
        return match ($type) {
            'G', 'GPair', 'GTrpl', 'GGroup' => 'galaxy',
            'OCl', 'GCl', 'Cl+N' => 'cluster',
            'PN', 'HII', 'EmN', 'Neb', 'RfN', 'SNR' => 'nebula',
            '**', '***', '*' => 'star',
            default => strtolower($type),
        };
    }
}
