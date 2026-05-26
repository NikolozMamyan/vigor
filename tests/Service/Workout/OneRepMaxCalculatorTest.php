<?php

namespace App\Tests\Service\Workout;

use App\Service\Workout\OneRepMaxCalculator;
use PHPUnit\Framework\TestCase;

final class OneRepMaxCalculatorTest extends TestCase
{
    public function testItUsesEpleyFormula(): void
    {
        $calculator = new OneRepMaxCalculator();

        self::assertSame(106.67, $calculator->estimate(80, 10));
    }

    public function testOneRepReturnsWeight(): void
    {
        $calculator = new OneRepMaxCalculator();

        self::assertSame(95.0, $calculator->estimate(95, 1));
    }

    public function testInvalidInputsReturnZero(): void
    {
        $calculator = new OneRepMaxCalculator();

        self::assertSame(0.0, $calculator->estimate(0, 10));
        self::assertSame(0.0, $calculator->estimate(100, 0));
    }
}
