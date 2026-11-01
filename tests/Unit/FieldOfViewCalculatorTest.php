<?php

namespace Tests\Unit;

use App\Services\Equipment\FieldOfViewCalculator;
use App\Services\Equipment\PixelScaleCalculator;
use PHPUnit\Framework\TestCase;

class FieldOfViewCalculatorTest extends TestCase
{
    public function test_field_of_view_and_pixel_scale_use_exact_formulas(): void
    {
        $field = (new FieldOfViewCalculator)->calculateDegrees(11.31, 750);
        $scale = (new PixelScaleCalculator)->calculate(3.76, 750);

        $this->assertEqualsWithDelta(0.864, $field, 0.001);
        $this->assertEqualsWithDelta(1.034, $scale, 0.001);
    }
}
