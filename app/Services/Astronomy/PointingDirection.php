<?php

namespace App\Services\Astronomy;

class PointingDirection
{
    /** @var list<string> */
    private const DIRECTIONS = [
        'N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE',
        'S', 'SSO', 'SO', 'OSO', 'O', 'ONO', 'NO', 'NNO',
    ];

    public function fromAzimuth(float $azimuthDeg): string
    {
        $normalized = fmod($azimuthDeg, 360);
        $normalized = $normalized < 0 ? $normalized + 360 : $normalized;
        $index = (int) floor(($normalized + 11.25) / 22.5) % 16;

        return self::DIRECTIONS[$index];
    }
}
