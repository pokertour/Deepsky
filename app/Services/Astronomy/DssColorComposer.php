<?php

namespace App\Services\Astronomy;

use GdImage;
use RuntimeException;

/**
 * Recompose une image couleur à partir des plaques DSS2 bleue et rouge
 * (vert = moyenne des deux, comme le relevé DSS2 coloré du CDS), avec un
 * étirement par canal qui assombrit le fond du ciel.
 */
class DssColorComposer
{
    private const float BLACK_PERCENTILE = .35;

    private const float WHITE_PERCENTILE = .998;

    public function compose(string $redJpeg, string $blueJpeg, int $width, int $height): string
    {
        $red = $this->load($redJpeg, $width, $height);
        $blue = $this->load($blueJpeg, $width, $height);

        $redLevels = $this->levels($red, $width, $height);
        $blueLevels = $this->levels($blue, $width, $height);
        $redMap = $this->stretchTable(...$redLevels);
        $blueMap = $this->stretchTable(...$blueLevels);

        $output = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $r = $redMap[imagecolorat($red, $x, $y) & 0xFF];
                $b = $blueMap[imagecolorat($blue, $x, $y) & 0xFF];
                imagesetpixel($output, $x, $y, ($r << 16) | ((($r + $b) >> 1) << 8) | $b);
            }
        }

        ob_start();
        imagejpeg($output, quality: 85);

        return (string) ob_get_clean();
    }

    private function load(string $jpeg, int $width, int $height): GdImage
    {
        $image = @imagecreatefromstring($jpeg);

        // SkyView répond par une petite image « no data » hors couverture.
        if (! $image instanceof GdImage || imagesx($image) !== $width || imagesy($image) !== $height) {
            throw new RuntimeException('Plaque DSS2 absente ou aux mauvaises dimensions.');
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }

    /** @return array{0: int, 1: int} niveaux noir et blanc (0-255) */
    private function levels(GdImage $image, int $width, int $height): array
    {
        $histogram = array_fill(0, 256, 0);

        for ($y = 0; $y < $height; $y += 2) {
            for ($x = 0; $x < $width; $x += 2) {
                $histogram[imagecolorat($image, $x, $y) & 0xFF]++;
            }
        }

        $total = array_sum($histogram);
        $black = $this->percentile($histogram, $total * self::BLACK_PERCENTILE);
        $white = max($black + 1, $this->percentile($histogram, $total * self::WHITE_PERCENTILE));

        return [$black, $white];
    }

    /** @param  array<int, int>  $histogram */
    private function percentile(array $histogram, float $rank): int
    {
        $cumulated = 0;

        foreach ($histogram as $value => $count) {
            $cumulated += $count;

            if ($cumulated >= $rank) {
                return $value;
            }
        }

        return 255;
    }

    /** @return list<int> */
    private function stretchTable(int $black, int $white): array
    {
        return array_map(
            fn (int $value): int => (int) round(255 * min(1, max(0, ($value - $black) / ($white - $black))) ** .9),
            range(0, 255),
        );
    }
}
