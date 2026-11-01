<?php

namespace Tests\Unit;

use App\Rules\HorizonMaskRule;
use App\Services\Astronomy\HorizonMask;
use Tests\TestCase;

class HorizonMaskTest extends TestCase
{
    public function test_decimal_bearings_are_preserved_and_interpolated(): void
    {
        $mask = new HorizonMask;
        $this->assertEqualsWithDelta(15, $mask->altitude(['90.25' => 10, '90.75' => 20], 90.5), 0.00001);
        $this->assertSame(10.0, $mask->altitude(['90.25' => 10, '90.75' => 20], 90.25));
    }

    public function test_interpolation_crosses_north_and_normalizes_azimuth(): void
    {
        $mask = new HorizonMask;
        $points = ['10' => 10, '350' => 30];
        $this->assertSame(20.0, $mask->altitude($points, 0));
        $this->assertSame(20.0, $mask->altitude($points, 360));
        $this->assertSame(20.0, $mask->altitude($points, -360));
        $this->assertSame(0.0, $mask->altitude([], 45));
        $this->assertSame(12.0, $mask->altitude(['25.5' => 12], 300));
    }

    public function test_validation_accepts_decimal_json_object_and_rejects_invalid_masks(): void
    {
        $rule = new HorizonMaskRule;
        foreach (['{}', '{"0":0,"90.25":12.5,"359.9":90}'] as $valid) {
            $rule->validate('horizon_mask', $valid, fn () => $this->fail('Valid mask was rejected.'));
        }
        $tooMany = [];
        for ($i = 0; $i < 721; $i++) {
            $tooMany[(string) ($i / 3)] = 10;
        }
        foreach (['[]', 'null', '25', '{"360":5}', '{"-1":5}', '{"0":91}', '{"0":-1}', '{"0":true}', '{"0":"10"}', '{"NaN":10}', '{"0":1e999}', '{"0":10,"0.0":20}', json_encode((object) $tooMany)] as $invalid) {
            $errors = [];
            $rule->validate('horizon_mask', $invalid, function (string $message) use (&$errors): void {
                $errors[] = $message;
            });
            $this->assertNotEmpty($errors, 'Invalid mask was accepted: '.$invalid);
        }
    }
}
