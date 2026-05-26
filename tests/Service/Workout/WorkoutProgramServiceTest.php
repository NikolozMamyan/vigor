<?php

declare(strict_types=1);

namespace App\Tests\Service\Workout;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use App\Entity\WorkoutProgram;
use App\Entity\WorkoutProgramExercise;
use App\Service\Workout\WorkoutProgramService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class WorkoutProgramServiceTest extends TestCase
{
    public function testCreatePersistsProgramWithDefaultExercise(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->with(self::logicalOr(
                self::isInstanceOf(WorkoutProgram::class),
                self::isInstanceOf(WorkoutProgramExercise::class),
            ));
        $entityManager->expects(self::once())->method('flush');

        $program = (new WorkoutProgramService($entityManager))->create(
            new UserProfile(),
            $this->createExercise(),
            'Push maison',
        );

        self::assertSame('Push maison', $program->getName());
        self::assertSame('Programme personnalise', $program->getDescription());
        self::assertSame(20, $program->getEstimatedDurationMinutes());
    }

    public function testCreatePersistsConfiguredExercises(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(3))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $program = (new WorkoutProgramService($entityManager))->create(
            new UserProfile(),
            $this->createExercise(),
            'Full body',
            [
                [
                    'exercise' => $this->createExercise(),
                    'targetSets' => 4,
                    'targetRepsMin' => 6,
                    'targetRepsMax' => 8,
                    'targetWeight' => 90.0,
                    'restSeconds' => 120,
                ],
                [
                    'exercise' => (new Exercise())
                        ->setName('Squat')
                        ->setSlug('squat')
                        ->setMuscleGroup('Jambes')
                        ->setEquipment('Barre'),
                    'targetSets' => 3,
                    'targetRepsMin' => 8,
                    'targetRepsMax' => 10,
                    'targetWeight' => 120.0,
                    'restSeconds' => 150,
                ],
            ],
        );

        self::assertSame('Full body', $program->getName());
        self::assertSame(28, $program->getEstimatedDurationMinutes());
    }

    public function testCreateRejectsShortName(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        (new WorkoutProgramService($entityManager))->create(new UserProfile(), $this->createExercise(), 'A');
    }

    public function testDeleteRemovesProgram(): void
    {
        $program = new WorkoutProgram(new UserProfile());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($program);
        $entityManager->expects(self::once())->method('flush');

        (new WorkoutProgramService($entityManager))->delete($program);
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
