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
    }

    public function testCreateRejectsShortName(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        (new WorkoutProgramService($entityManager))->create(new UserProfile(), $this->createExercise(), 'A');
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
