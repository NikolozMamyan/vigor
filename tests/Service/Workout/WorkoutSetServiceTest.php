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
use App\Service\Workout\WorkoutSetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class WorkoutSetServiceTest extends TestCase
{
    public function testCreateReusesExistingSetAtSamePosition(): void
    {
        $sessionExercise = $this->createSessionExercise();
        $existingSet = (new WorkoutSet($sessionExercise))
            ->setPosition(4)
            ->setWeight(80.0)
            ->setReps(8);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $setRepository = $this->createMock(WorkoutSetReaderInterface::class);
        $setRepository
            ->expects(self::once())
            ->method('findOneForSessionExerciseAtPosition')
            ->with($sessionExercise, 4)
            ->willReturn($existingSet);

        $service = $this->createService($entityManager, $setRepository);

        $result = $service->create($sessionExercise, 4, 82.5, 9);

        self::assertSame($existingSet, $result);
        self::assertSame(82.5, $result->getWeight());
        self::assertSame(9, $result->getReps());
    }

    public function testCreatePersistsNewSetWhenPositionIsFree(): void
    {
        $sessionExercise = $this->createSessionExercise();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(WorkoutSet::class));
        $entityManager->expects(self::once())->method('flush');

        $setRepository = $this->createMock(WorkoutSetReaderInterface::class);
        $setRepository
            ->expects(self::once())
            ->method('findOneForSessionExerciseAtPosition')
            ->with($sessionExercise, 5)
            ->willReturn(null);

        $service = $this->createService($entityManager, $setRepository);

        $result = $service->create($sessionExercise, 5, 90.0, 6);

        self::assertSame($sessionExercise, $result->getSessionExercise());
        self::assertSame(5, $result->getPosition());
        self::assertSame(90.0, $result->getWeight());
        self::assertSame(6, $result->getReps());
    }

    public function testCreateCompletedForHistoryPersistsCompletedSet(): void
    {
        $sessionExercise = $this->createSessionExercise();
        $completedAt = new \DateTimeImmutable('2026-05-26 11:00:00');
        $sessionExercise->getSession()->complete($completedAt);

        $setRepository = $this->createMock(WorkoutSetReaderInterface::class);
        $setRepository->expects(self::once())
            ->method('findOneForSessionExerciseAtPosition')
            ->with($sessionExercise, 6)
            ->willReturn(null);

        $recordRepository = $this->createMock(PersonalRecordReaderInterface::class);
        $recordRepository->expects(self::once())
            ->method('findBest')
            ->willReturn(null);

        $persistedSet = null;
        $persistedRecord = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedSet, &$persistedRecord): void {
                if ($entity instanceof WorkoutSet) {
                    $persistedSet = $entity;
                }

                if ($entity instanceof PersonalRecord) {
                    $persistedRecord = $entity;
                }
            });
        $entityManager->expects(self::once())->method('flush');

        $result = $this->createService($entityManager, $setRepository, $recordRepository)
            ->createCompletedForHistory($sessionExercise, 6, 120.0, 5);

        self::assertSame($persistedSet, $result);
        self::assertSame($sessionExercise, $result->getSessionExercise());
        self::assertSame(6, $result->getPosition());
        self::assertSame(120.0, $result->getWeight());
        self::assertSame(5, $result->getReps());
        self::assertSame($completedAt, $result->getCompletedAt());
        self::assertSame(140.0, $result->getEstimatedOneRepMax());
        self::assertInstanceOf(PersonalRecord::class, $persistedRecord);
    }

    public function testDeleteRemovesSet(): void
    {
        $set = new WorkoutSet($this->createSessionExercise());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($set);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService($entityManager, $this->createStub(WorkoutSetReaderInterface::class));

        $service->delete($set);
    }

    public function testUpdateCompletedSetUpdatesLinkedPersonalRecord(): void
    {
        $sessionExercise = $this->createSessionExercise();
        $set = (new WorkoutSet($sessionExercise))
            ->setWeight(100.0)
            ->setReps(5)
            ->complete(new \DateTimeImmutable('2026-05-26 10:30:00'), 116.67);
        $record = (new PersonalRecord($sessionExercise->getSession()->getProfile(), $sessionExercise->getExercise(), $set, 116.67))
            ->setPreviousValue(100.0);

        $recordRepository = $this->createMock(PersonalRecordReaderInterface::class);
        $recordRepository->expects(self::once())
            ->method('findForWorkoutSet')
            ->with($set, PersonalRecord::METRIC_ESTIMATED_1RM)
            ->willReturn($record);
        $recordRepository->expects(self::never())->method('findBest');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::once())->method('flush');

        $this->createService(
            $entityManager,
            $this->createStub(WorkoutSetReaderInterface::class),
            $recordRepository,
        )->update($set, 120.0, 5);

        self::assertSame(140.0, $set->getEstimatedOneRepMax());
        self::assertSame(140.0, $record->getValue());
    }

    public function testUpdateCompletedSetRemovesLinkedPersonalRecordWhenItIsNoLongerARecord(): void
    {
        $sessionExercise = $this->createSessionExercise();
        $set = (new WorkoutSet($sessionExercise))
            ->setWeight(100.0)
            ->setReps(5)
            ->complete(new \DateTimeImmutable('2026-05-26 10:30:00'), 116.67);
        $record = (new PersonalRecord($sessionExercise->getSession()->getProfile(), $sessionExercise->getExercise(), $set, 116.67))
            ->setPreviousValue(130.0);

        $recordRepository = $this->createMock(PersonalRecordReaderInterface::class);
        $recordRepository->expects(self::once())
            ->method('findForWorkoutSet')
            ->with($set, PersonalRecord::METRIC_ESTIMATED_1RM)
            ->willReturn($record);
        $recordRepository->expects(self::never())->method('findBest');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('remove')->with($record);
        $entityManager->expects(self::once())->method('flush');

        $this->createService(
            $entityManager,
            $this->createStub(WorkoutSetReaderInterface::class),
            $recordRepository,
        )->update($set, 90.0, 5);

        self::assertSame(105.0, $set->getEstimatedOneRepMax());
    }

    private function createService(
        EntityManagerInterface $entityManager,
        WorkoutSetReaderInterface $setRepository,
        ?PersonalRecordReaderInterface $recordRepository = null,
    ): WorkoutSetService {
        return new WorkoutSetService(
            $entityManager,
            $setRepository,
            new OneRepMaxCalculator(),
            new PersonalRecordService(
                $entityManager,
                $recordRepository ?? $this->createStub(PersonalRecordReaderInterface::class),
                new PersonalRecordDetector(),
            ),
        );
    }

    private function createSessionExercise(): WorkoutSessionExercise
    {
        $profile = (new UserProfile())
            ->setUsername('alexvigor')
            ->setDisplayName('Alex');

        $exercise = (new Exercise())
            ->setName('Squat')
            ->setSlug('squat')
            ->setMuscleGroup('Jambes')
            ->setEquipment('Barre');

        $session = new WorkoutSession($profile);

        return new WorkoutSessionExercise($session, $exercise);
    }
}
