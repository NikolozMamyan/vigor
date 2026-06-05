<?php

declare(strict_types=1);

namespace App\Tests\Service\Workout;

use App\Entity\Exercise;
use App\Entity\PersonalRecord;
use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Repository\PersonalRecordReaderInterface;
use App\Repository\WorkoutSetReaderInterface;
use App\Service\Workout\OneRepMaxCalculator;
use App\Service\Workout\PersonalRecordDetector;
use App\Service\Workout\PersonalRecordService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PersonalRecordServiceTest extends TestCase
{
    public function testLowerSetInSameActiveSessionDoesNotCreateAnotherRecord(): void
    {
        [$profile, $exercise, $sessionExercise] = $this->createContext();
        $firstSet = $this->completedSet($sessionExercise, 1, 50.0, 5, '2026-05-27 09:53:28');
        $lowerSet = $this->completedSet($sessionExercise, 2, 50.0, 4, '2026-05-27 09:54:05');

        $setRepository = $this->createMock(WorkoutSetReaderInterface::class);
        $setRepository->expects(self::once())
            ->method('findCompletedForRecordCalculation')
            ->with($profile, $exercise)
            ->willReturn([$firstSet, $lowerSet]);

        $recordRepository = $this->createMock(PersonalRecordReaderInterface::class);
        $recordRepository->expects(self::once())
            ->method('findAllForExercise')
            ->willReturn([]);

        $persistedRecords = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedRecords): void {
                $persistedRecords[] = $entity;
            });

        $result = $this->createService($entityManager, $recordRepository, $setRepository)
            ->recalculateForSet($lowerSet);

        self::assertNull($result);
        self::assertCount(1, $persistedRecords);
        self::assertSame($firstSet, $persistedRecords[0]->getWorkoutSet());
        self::assertSame(58.33, $persistedRecords[0]->getValue());
        self::assertNull($persistedRecords[0]->getPreviousValue());
    }

    public function testEditingOlderSetRebuildsLaterRecordMilestones(): void
    {
        [$profile, $exercise, $sessionExercise] = $this->createContext();
        $olderSet = $this->completedSet($sessionExercise, 1, 100.0, 5, '2026-05-27 09:00:00');
        $laterSet = $this->completedSet($sessionExercise, 2, 110.0, 5, '2026-05-27 09:10:00');
        $olderSet->setWeight(115.0);

        $oldFirstRecord = new PersonalRecord($profile, $exercise, $olderSet, 116.67);
        $oldSecondRecord = (new PersonalRecord($profile, $exercise, $laterSet, 128.33))
            ->setPreviousValue(116.67);

        $setRepository = $this->createMock(WorkoutSetReaderInterface::class);
        $setRepository->expects(self::once())
            ->method('findCompletedForRecordCalculation')
            ->willReturn([$olderSet, $laterSet]);

        $recordRepository = $this->createMock(PersonalRecordReaderInterface::class);
        $recordRepository->expects(self::once())
            ->method('findAllForExercise')
            ->willReturn([$oldFirstRecord, $oldSecondRecord]);

        $persistedRecords = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('remove');
        $entityManager->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedRecords): void {
                $persistedRecords[] = $entity;
            });

        $result = $this->createService($entityManager, $recordRepository, $setRepository)
            ->recalculateForSet($olderSet);

        self::assertSame($persistedRecords[0], $result);
        self::assertCount(1, $persistedRecords);
        self::assertSame($olderSet, $persistedRecords[0]->getWorkoutSet());
        self::assertSame(134.17, $persistedRecords[0]->getValue());
        self::assertNull($persistedRecords[0]->getPreviousValue());
    }

    public function testExcludedCancelledSessionRecordsAreRemoved(): void
    {
        $profile = (new UserProfile())->setDisplayName('Alex')->setUsername('alex');
        $exercise = $this->createExercise();
        $completedSession = (new WorkoutSession($profile))
            ->setStartedAt(new \DateTimeImmutable('2026-05-20 10:00:00'));
        $completedSession->complete(new \DateTimeImmutable('2026-05-20 11:00:00'));
        $activeSession = (new WorkoutSession($profile))
            ->setStartedAt(new \DateTimeImmutable('2026-05-27 10:00:00'));
        $completedSet = $this->completedSet(
            new WorkoutSessionExercise($completedSession, $exercise),
            1,
            80.0,
            5,
            '2026-05-20 10:30:00',
        );
        $cancelledSet = $this->completedSet(
            new WorkoutSessionExercise($activeSession, $exercise),
            1,
            100.0,
            5,
            '2026-05-27 10:30:00',
        );
        $cancelledRecord = new PersonalRecord($profile, $exercise, $cancelledSet, 116.67);

        $setRepository = $this->createMock(WorkoutSetReaderInterface::class);
        $setRepository->expects(self::once())
            ->method('findCompletedForRecordCalculation')
            ->willReturn([$completedSet, $cancelledSet]);

        $recordRepository = $this->createMock(PersonalRecordReaderInterface::class);
        $recordRepository->expects(self::once())
            ->method('findAllForExercise')
            ->willReturn([$cancelledRecord]);

        $persistedRecords = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($cancelledRecord);
        $entityManager->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedRecords): void {
                $persistedRecords[] = $entity;
            });

        $this->createService($entityManager, $recordRepository, $setRepository)
            ->recalculateForExercise($profile, $exercise, excludedSession: $activeSession);

        self::assertCount(1, $persistedRecords);
        self::assertSame($completedSet, $persistedRecords[0]->getWorkoutSet());
        self::assertSame(93.33, $persistedRecords[0]->getValue());
    }

    private function createService(
        EntityManagerInterface $entityManager,
        PersonalRecordReaderInterface $recordRepository,
        WorkoutSetReaderInterface $setRepository,
    ): PersonalRecordService {
        return new PersonalRecordService(
            $entityManager,
            $recordRepository,
            $setRepository,
            new OneRepMaxCalculator(),
            new PersonalRecordDetector(),
        );
    }

    /**
     * @return array{UserProfile, Exercise, WorkoutSessionExercise}
     */
    private function createContext(): array
    {
        $profile = (new UserProfile())->setDisplayName('Alex')->setUsername('alex');
        $exercise = $this->createExercise();
        $session = (new WorkoutSession($profile))
            ->setStartedAt(new \DateTimeImmutable('2026-05-27 09:00:00'));

        return [$profile, $exercise, new WorkoutSessionExercise($session, $exercise)];
    }

    private function createExercise(): Exercise
    {
        return (new Exercise())
            ->setName('Abduction machine')
            ->setSlug('abduction-machine')
            ->setMuscleGroup('Fessiers')
            ->setEquipment('Machine');
    }

    private function completedSet(
        WorkoutSessionExercise $sessionExercise,
        int $position,
        float $weight,
        int $reps,
        string $completedAt,
    ): WorkoutSet {
        return (new WorkoutSet($sessionExercise))
            ->setPosition($position)
            ->setWeight($weight)
            ->setReps($reps)
            ->complete(new \DateTimeImmutable($completedAt), 0.0);
    }
}
