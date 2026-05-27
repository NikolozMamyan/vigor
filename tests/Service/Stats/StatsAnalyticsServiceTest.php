<?php

declare(strict_types=1);

namespace App\Tests\Service\Stats;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Service\Stats\StatsAnalyticsService;
use PHPUnit\Framework\TestCase;

final class StatsAnalyticsServiceTest extends TestCase
{
    public function testSessionBubblesUseSessionPeakOneRepMax(): void
    {
        $olderSession = $this->createSession('Push', '2026-05-25 18:00:00');
        $newerSession = $this->createSession('Legs', '2026-05-26 18:00:00');

        $olderSet = $this->createSet($olderSession, 'Developpe couche', 90, 5, 105);
        $newerSet = $this->createSet($newerSession, 'Squat', 120, 4, 136);

        $bubbles = $this->buildSessionBubbles([$newerSet, $olderSet]);

        self::assertSame('Push', $bubbles[0]['name']);
        self::assertSame('105', $bubbles[0]['peakOneRepMax']);
        self::assertSame('Developpe couche', $bubbles[0]['peakExercise']);
        self::assertSame(10, $bubbles[0]['x']);

        self::assertSame('Legs', $bubbles[1]['name']);
        self::assertSame('136', $bubbles[1]['peakOneRepMax']);
        self::assertSame('Squat', $bubbles[1]['peakExercise']);
        self::assertSame(90, $bubbles[1]['x']);
        self::assertGreaterThan($bubbles[0]['y'], $bubbles[1]['y']);
    }

    /**
     * @param list<WorkoutSet> $sets
     *
     * @return list<array<string, mixed>>
     */
    private function buildSessionBubbles(array $sets): array
    {
        $service = (new \ReflectionClass(StatsAnalyticsService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(StatsAnalyticsService::class, 'buildSessionBubbles');
        $method->setAccessible(true);

        return $method->invoke($service, $sets);
    }

    private function createSession(string $name, string $completedAt): WorkoutSession
    {
        $completedAtDate = new \DateTimeImmutable($completedAt);
        $session = (new WorkoutSession(new UserProfile()))
            ->setName($name)
            ->setStartedAt($completedAtDate->modify('-45 minutes'));

        $session->complete($completedAtDate);

        return $session;
    }

    private function createSet(WorkoutSession $session, string $exerciseName, float $weight, int $reps, float $estimatedOneRepMax): WorkoutSet
    {
        $exercise = (new Exercise())
            ->setName($exerciseName)
            ->setSlug(strtolower(str_replace(' ', '-', $exerciseName)))
            ->setMuscleGroup('Force')
            ->setEquipment('Barre');
        $sessionExercise = new WorkoutSessionExercise($session, $exercise);

        return (new WorkoutSet($sessionExercise))
            ->setWeight($weight)
            ->setReps($reps)
            ->complete(new \DateTimeImmutable(), $estimatedOneRepMax);
    }
}
