<?php

declare(strict_types=1);

namespace App\Tests\Service\Dashboard;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Service\Dashboard\DashboardService;
use PHPUnit\Framework\TestCase;

final class DashboardServiceTest extends TestCase
{
    public function testWeeklyActivityStaysEmptyWhenNoSetIsChecked(): void
    {
        $weekly = $this->buildWeekly([], []);

        self::assertSame('0', $weekly['selectedValue']);
        self::assertSame('0', $weekly['totalTons']);
        self::assertSame(0, $weekly['trendPercent']);
        self::assertSame('Aucune serie cochee cette semaine', $weekly['selectedLabel']);

        foreach ($weekly['days'] as $day) {
            self::assertTrue($day['rest']);
            self::assertFalse($day['active']);
            self::assertSame(0, $day['height']);
        }
    }

    public function testWeeklyActivityIgnoresUncompletedSets(): void
    {
        $uncompletedSet = (new WorkoutSet($this->createSessionExercise()))
            ->setWeight(120)
            ->setReps(5);

        $weekly = $this->buildWeekly([$uncompletedSet], []);

        self::assertSame('0', $weekly['selectedValue']);
        self::assertSame('0', $weekly['totalTons']);
        self::assertSame('Aucune serie cochee cette semaine', $weekly['selectedLabel']);
    }

    public function testWeeklyActivityCountsCheckedSetsOnly(): void
    {
        $checkedSet = (new WorkoutSet($this->createSessionExercise()))
            ->setWeight(100)
            ->setReps(10)
            ->complete(new \DateTimeImmutable('2026-05-26 12:00:00'), 133.0);

        $weekly = $this->buildWeekly([$checkedSet], []);

        self::assertSame('1', $weekly['selectedValue']);
        self::assertSame('1', $weekly['totalTons']);
        self::assertSame('Mardi - meilleure seance', $weekly['selectedLabel']);
    }

    /**
     * @param list<WorkoutSet> $completedThisWeek
     * @param list<WorkoutSet> $completedPreviousWeek
     *
     * @return array<string, mixed>
     */
    private function buildWeekly(array $completedThisWeek, array $completedPreviousWeek): array
    {
        $service = (new \ReflectionClass(DashboardService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(DashboardService::class, 'buildWeekly');
        $method->setAccessible(true);

        return $method->invoke($service, $completedThisWeek, $completedPreviousWeek, new \DateTimeImmutable('2026-05-25'));
    }

    private function createSessionExercise(): WorkoutSessionExercise
    {
        $profile = new UserProfile();
        $session = new WorkoutSession($profile);
        $exercise = (new Exercise())
            ->setName('Squat')
            ->setSlug('squat')
            ->setMuscleGroup('Jambes')
            ->setEquipment('Barre');

        return new WorkoutSessionExercise($session, $exercise);
    }
}
