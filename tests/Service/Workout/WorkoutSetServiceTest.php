<?php

declare(strict_types=1);

namespace App\Tests\Service\Workout;

use App\Entity\Exercise;
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

    public function testDeleteRemovesSet(): void
    {
        $set = new WorkoutSet($this->createSessionExercise());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($set);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService($entityManager, $this->createStub(WorkoutSetReaderInterface::class));

        $service->delete($set);
    }

    private function createService(
        EntityManagerInterface $entityManager,
        WorkoutSetReaderInterface $setRepository,
    ): WorkoutSetService {
        return new WorkoutSetService(
            $entityManager,
            $setRepository,
            new OneRepMaxCalculator(),
            new PersonalRecordService(
                $entityManager,
                $this->createStub(PersonalRecordReaderInterface::class),
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
