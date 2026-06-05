<?php

namespace App\Service\Workout;

final class OneRepMaxCalculator
{
    public const MAX_WEIGHT_KG = 2000.0;

    public function estimate(float $weight, int $reps): float
    {
        if ($weight <= 0 || $weight > self::MAX_WEIGHT_KG || $reps <= 0) {
            return 0.0;
        }

        if (1 === $reps) {
            return round($weight, 2);
        }

        return round($weight * (1 + ($reps / 30)), 2);
    }
}
