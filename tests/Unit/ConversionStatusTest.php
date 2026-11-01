<?php

namespace Tests\Unit;

use App\Enums\ConversionStatus;
use Tests\TestCase;

class ConversionStatusTest extends TestCase
{
    public function test_every_status_has_a_french_label(): void
    {
        $this->assertSame([
            'missing' => 'Image manquante',
            'queued' => 'Conversions en attente',
            'converting' => 'Conversion en cours',
            'ready' => 'Images prêtes',
            'failed' => 'Échec des conversions',
        ], collect(ConversionStatus::cases())->mapWithKeys(fn (ConversionStatus $status): array => [$status->value => $status->getLabel()])->all());
    }
}
