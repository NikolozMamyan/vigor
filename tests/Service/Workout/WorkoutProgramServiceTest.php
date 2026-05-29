<?php

declare(strict_types=1);

namespace App\Tests\Service\Workout;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use App\Entity\WorkoutProgram;
use App\Entity\WorkoutProgramExercise;
use App\Repository\WorkoutProgramExerciseReaderInterface;
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

        $program = $this->createService($entityManager)->create(
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

        $program = $this->createService($entityManager)->create(
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

        $this->createService($entityManager)->create(new UserProfile(), $this->createExercise(), 'A');
    }

    public function testUpdateReplacesProgramExercises(): void
    {
        $program = new WorkoutProgram(new UserProfile());
        $existingProgramExercise = new WorkoutProgramExercise($program, $this->createExercise());
        $newExercise = (new Exercise())
            ->setName('Squat')
            ->setSlug('squat')
            ->setMuscleGroup('Jambes')
            ->setEquipment('Barre');

        $programExerciseRepository = $this->createMock(WorkoutProgramExerciseReaderInterface::class);
        $programExerciseRepository->expects(self::once())
            ->method('findForProgram')
            ->with($program)
            ->willReturn([$existingProgramExercise]);

        $persistedProgramExercise = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($existingProgramExercise);
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (object $entity) use (&$persistedProgramExercise): bool {
                $persistedProgramExercise = $entity;

                return $entity instanceof WorkoutProgramExercise;
            }));
        $entityManager->expects(self::once())->method('flush');

        $updatedProgram = $this->createService($entityManager, $programExerciseRepository)->update(
            $program,
            'Leg day',
            [[
                'exercise' => $newExercise,
                'targetSets' => 6,
                'targetRepsMin' => 5,
                'targetRepsMax' => 7,
                'targetWeight' => 120.0,
                'restSeconds' => 75,
            ]],
        );

        self::assertSame($program, $updatedProgram);
        self::assertSame('Leg day', $program->getName());
        self::assertSame(24, $program->getEstimatedDurationMinutes());
        self::assertInstanceOf(WorkoutProgramExercise::class, $persistedProgramExercise);
        self::assertSame($program, $persistedProgramExercise->getProgram());
        self::assertSame($newExercise, $persistedProgramExercise->getExercise());
        self::assertSame(1, $persistedProgramExercise->getPosition());
        self::assertSame(6, $persistedProgramExercise->getTargetSets());
        self::assertSame(5, $persistedProgramExercise->getTargetRepsMin());
        self::assertSame(7, $persistedProgramExercise->getTargetRepsMax());
        self::assertSame(120.0, $persistedProgramExercise->getTargetWeight());
        self::assertSame(75, $persistedProgramExercise->getRestSeconds());
    }

    public function testUpdateRejectsEmptyExerciseList(): void
    {
        $program = new WorkoutProgram(new UserProfile());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        $this->createService($entityManager)->update($program, 'Push maison', []);
    }

    public function testDeleteRemovesProgram(): void
    {
        $program = new WorkoutProgram(new UserProfile());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($program);
        $entityManager->expects(self::once())->method('flush');

        $this->createService($entityManager)->delete($program);
    }

    private function createService(
        EntityManagerInterface $entityManager,
        ?WorkoutProgramExerciseReaderInterface $programExerciseRepository = null,
    ): WorkoutProgramService {
        return new WorkoutProgramService(
            $entityManager,
            $programExerciseRepository ?? $this->createStub(WorkoutProgramExerciseReaderInterface::class),
        );
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
