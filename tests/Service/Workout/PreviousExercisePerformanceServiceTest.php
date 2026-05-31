<?php

declare(strict_types=1);

namespace App\Tests\Service\Workout;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Repository\WorkoutSetReaderInterface;
use App\Service\Workout\PreviousExercisePerformanceService;
use PHPUnit\Framework\TestCase;

final class PreviousExercisePerformanceServiceTest extends TestCase
{
    public function testItReturnsEmptyStateWhenExerciseHasNoPreviousCompletedSet(): void
    {
        $profile = new UserProfile();
        $exercise = $this->createExercise();
        $session = new WorkoutSession($profile);

        $setRepository = $this->createMock(WorkoutSetReaderInterface::class);
        $setRepository->expects(self::once())
            ->method('findPreviousCompletedForExercise')
            ->with($profile, $exercise, $session)
            ->willReturn([]);

        $result = (new PreviousExercisePerformanceService($setRepository))->forExercise($profile, $exercise, $session);

        self::assertFalse($result['hasData']);
        self::assertSame('Premiere fois suivie', $result['title']);
        self::assertSame('Nouveau repere', $result['summary']);
        self::assertSame([], $result['sets']);
    }

    public function testItSummarizesLastCompletedSetsForExercise(): void
    {
        $profile = new UserProfile();
        $exercise = $this->createExercise();
        $completedSession = (new WorkoutSession($profile))
            ->setStartedAt(new \DateTimeImmutable('2026-05-20 10:00:00'));
        $completedSession->complete(new \DateTimeImmutable('2026-05-20 11:00:00'));

        $sessionExercise = new WorkoutSessionExercise($completedSession, $exercise);
        $sets = [
            (new WorkoutSet($sessionExercise))->setPosition(1)->setWeight(30.0)->setReps(10)->complete(new \DateTimeImmutable('2026-05-20 10:15:00'), 40.0),
            (new WorkoutSet($sessionExercise))->setPosition(2)->setWeight(35.0)->setReps(8)->complete(new \DateTimeImmutable('2026-05-20 10:25:00'), 44.33),
            (new WorkoutSet($sessionExercise))->setPosition(3)->setWeight(35.0)->setReps(10)->complete(new \DateTimeImmutable('2026-05-20 10:35:00'), 46.67),
        ];

        $currentSession = new WorkoutSession($profile);
        $setRepository = $this->createMock(WorkoutSetReaderInterface::class);
        $setRepository->expects(self::once())
            ->method('findPreviousCompletedForExercise')
            ->with($profile, $exercise, $currentSession)
            ->willReturn($sets);

        $result = (new PreviousExercisePerformanceService($setRepository))->forExercise($profile, $exercise, $currentSession);

        self::assertTrue($result['hasData']);
        self::assertSame('Derniere fois', $result['title']);
        self::assertSame('20/05 - 3 series', $result['subtitle']);
        self::assertSame('35kg x 10', $result['summary']);
        self::assertSame('930', $result['volume']);
        self::assertSame(1, $result['sets'][0]['position']);
        self::assertSame('30kg x 10', $result['sets'][0]['label']);
        self::assertSame('30', $result['sets'][0]['weight']);
        self::assertSame(10, $result['sets'][0]['reps']);
        self::assertSame('300', $result['sets'][0]['volume']);
        self::assertTrue($result['sets'][0]['hasData']);
        self::assertSame('35kg x 8', $result['sets'][1]['label']);
        self::assertSame('35kg x 10', $result['sets'][2]['label']);
    }

    private function createExercise(): Exercise
    {
        return (new Exercise())
            ->setName('Developpe couche')
            ->setSlug('developpe-couche')
            ->setMuscleGroup('Pecs')
            ->setEquipment('Barre');
    }
}
