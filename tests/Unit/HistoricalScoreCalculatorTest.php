<?php

namespace Tests\Unit;

use App\Data\Planning\TargetHistory;
use App\Services\Planning\HistoricalScoreCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class HistoricalScoreCalculatorTest extends TestCase
{
    public function test_never_photographed_targets_score_maximum(): void
    {
        $score = (new HistoricalScoreCalculator)->calculate(
            new TargetHistory(0, 0, 0, null),
            'galaxy',
        );

        $this->assertSame(100.0, $score);
    }

    public function test_recent_heavy_integration_scores_lower_than_stale_light_integration(): void
    {
        $calculator = new HistoricalScoreCalculator;
        $reference = CarbonImmutable::parse('2026-08-16');

        $recentHeavy = $calculator->calculate(
            new TargetHistory(4, 3, 3600 * 12, $reference->subDays(10)),
            'galaxy',
            ['galaxy' => 20, 'nebula' => 2],
            22,
            $reference,
        );
        $staleLight = $calculator->calculate(
            new TargetHistory(1, 1, 1800, $reference->subDays(200)),
            'nebula',
            ['galaxy' => 20, 'nebula' => 2],
            22,
            $reference,
        );

        $this->assertTrue($staleLight > $recentHeavy);
    }
}
