<?php

namespace App\Services\Planning;

use App\Data\Equipment\EquipmentOptics;
use App\Data\Planning\ExternalReferencePhoto;
use App\Enums\SetupItemType;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Services\Astronomy\WikimediaCommonsClient;

class ExternalReferencePhotosFinder
{
    public function __construct(private WikimediaCommonsClient $commons) {}

    /**
     * @return list<ExternalReferencePhoto>
     */
    public function find(CelestialObject $target, int $limit = 8): array
    {
        foreach ($this->subjectCandidates($target) as $subject) {
            $images = $this->commons->searchImages($subject, $limit);

            if ($images === []) {
                continue;
            }

            return collect($images)
                ->map(fn (array $image): ExternalReferencePhoto => new ExternalReferencePhoto(
                    title: $image['title'],
                    author: $image['author'],
                    thumbUrl: $image['thumbUrl'],
                    pageUrl: $image['pageUrl'],
                    matchLabel: 'Wikimedia Commons',
                    similarityScore: 50,
                ))
                ->take($limit)
                ->values()
                ->all();
        }

        return [];
    }

    public function astroBinSearchUrl(CelestialObject $target, ?EquipmentOptics $optics = null, ?EquipmentSetup $setup = null): string
    {
        $parts = [$this->primarySubject($target)];

        if ($optics) {
            $parts[] = ((int) round($optics->effectiveFocalLengthMm)).'mm';
        }

        $tube = $setup?->enabledSetupItems
            ?->firstWhere('type', SetupItemType::OpticalTube);

        if ($tube) {
            $parts[] = trim(($tube->brand ?? '').' '.($tube->name ?? ''));
        }

        $query = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts))) ?? '');

        return 'https://www.astrobin.com/search/?q='.rawurlencode($query);
    }

    /**
     * @return list<string>
     */
    private function subjectCandidates(CelestialObject $target): array
    {
        $candidates = [];

        $compact = strtoupper(preg_replace('/\s+/', '', $target->designation()) ?? '');
        if ($compact !== '') {
            $candidates[] = $compact;
        }

        $spaced = trim($target->designation());
        if ($spaced !== '') {
            $candidates[] = $spaced;
        }

        if (filled($target->name)) {
            $candidates[] = (string) $target->name;
        }

        foreach ($target->alternative_names ?? [] as $alias) {
            $alias = trim((string) $alias);
            if ($alias === '') {
                continue;
            }

            $candidates[] = $alias;
            $candidates[] = preg_replace('/\s+/', '', $alias) ?? $alias;

            if (preg_match('/^(?:M|MESSIER)\s*(\d+)$/i', $alias, $matches) === 1) {
                $candidates[] = 'M'.$matches[1];
                $candidates[] = 'Messier '.$matches[1];
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function primarySubject(CelestialObject $target): string
    {
        foreach ($target->alternative_names ?? [] as $alias) {
            if (preg_match('/^(?:M|MESSIER)\s*(\d+)$/i', trim((string) $alias), $matches) === 1) {
                return 'M'.$matches[1];
            }
        }

        return preg_replace('/\s+/', '', $target->designation()) ?: $target->designation();
    }
}
