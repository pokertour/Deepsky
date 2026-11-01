<?php

namespace App\Services\Astronomy;

class HorizonMask
{
    /** @param array<int|string, int|float> $mask */
    public function altitude(array $mask, float $azimuth): float
    {
        $points = [];
        foreach ($mask as $bearing => $altitude) {
            if (is_numeric($bearing) && is_numeric($altitude)
                && is_finite((float) $bearing) && is_finite((float) $altitude)) {
                $points[] = ['azimuth' => (float) $bearing, 'altitude' => (float) $altitude];
            }
        }

        if ($points === [] || ! is_finite($azimuth)) {
            return 0;
        }

        usort($points, fn (array $a, array $b): int => $a['azimuth'] <=> $b['azimuth']);
        $first = $points[0];
        $points[] = ['azimuth' => $first['azimuth'] + 360, 'altitude' => $first['altitude']];
        $azimuth = fmod(fmod($azimuth, 360) + 360, 360);
        $azimuth = $azimuth < $first['azimuth'] ? $azimuth + 360 : $azimuth;
        $previous = $first;

        foreach ($points as $point) {
            if ($point['azimuth'] >= $azimuth) {
                $span = $point['azimuth'] - $previous['azimuth'];

                return $span <= 0 ? $point['altitude'] : $previous['altitude']
                    + ($point['altitude'] - $previous['altitude']) * (($azimuth - $previous['azimuth']) / $span);
            }
            $previous = $point;
        }

        return $first['altitude'];
    }
}
