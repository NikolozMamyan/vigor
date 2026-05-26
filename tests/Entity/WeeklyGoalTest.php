<?php

namespace App\Tests\Entity;

use App\Entity\UserProfile;
use App\Entity\WeeklyGoal;
use PHPUnit\Framework\TestCase;

final class WeeklyGoalTest extends TestCase
{
    public function testItUsesProfileDefaultsAndNormalizesWeekStart(): void
    {
        $profile = (new UserProfile())
            ->setWeeklyWorkoutGoal(5)
            ->setWeeklyVolumeGoal(18000);

        $goal = new WeeklyGoal($profile, new \DateTimeImmutable('2026-05-27 15:30:00'));

        self::assertSame($profile, $goal->getProfile());
        self::assertSame('2026-05-27 00:00:00', $goal->getWeekStartDate()->format('Y-m-d H:i:s'));
        self::assertSame(5, $goal->getTargetWorkouts());
        self::assertSame(18000, $goal->getTargetVolume());
        self::assertSame(180, $goal->getTargetTrainingMinutes());
    }
}
