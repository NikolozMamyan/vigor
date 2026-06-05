<?php

declare(strict_types=1);

namespace App\Tests\Service\Records;

use App\Entity\Exercise;
use App\Entity\PersonalRecord;
use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Service\Records\RecordsViewService;
use PHPUnit\Framework\TestCase;

final class RecordsViewServiceTest extends TestCase
{
    public function testHistoryKeepsLatestFiveRecordsInChronologicalOrder(): void
    {
        $profile = (new UserProfile())->setUsername('alex');
        $exercise = (new Exercise())
            ->setName('Squat')
            ->setSlug('squat')
            ->setMuscleGroup('Jambes')
            ->setEquipment('Barre');
        $session = (new WorkoutSession($profile))
            ->setStartedAt(new \DateTimeImmutable('2026-01-01 09:00:00'));
        $sessionExercise = new WorkoutSessionExercise($session, $exercise);
        $records = [];

        for ($day = 1; $day <= 6; ++$day) {
            $achievedAt = new \DateTimeImmutable(sprintf('2026-01-%02d 10:00:00', $day));
            $set = (new WorkoutSet($sessionExercise))
                ->setWeight(90.0 + $day)
                ->setReps(5)
                ->complete($achievedAt, 100.0 + $day);
            $records[] = (new PersonalRecord($profile, $exercise, $set, 100.0 + $day))
                ->setAchievedAt($achievedAt);
        }

        $service = (new \ReflectionClass(RecordsViewService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(RecordsViewService::class, 'formatRecord');
        $method->setAccessible(true);

        $formatted = $method->invoke($service, $records[5], array_reverse($records));
        $historyTimestamps = array_column($formatted['history'], 'achievedAtTimestamp');

        self::assertCount(5, $historyTimestamps);
        self::assertSame(
            array_map(
                static fn (int $day): int => (new \DateTimeImmutable(sprintf('2026-01-%02d 10:00:00', $day)))->getTimestamp(),
                range(2, 6),
            ),
            $historyTimestamps,
        );
    }
}
