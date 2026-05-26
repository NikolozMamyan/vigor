<?php

declare(strict_types=1);

namespace App\Tests\Service\Workout;

use App\Entity\UserProfile;
use App\Entity\Exercise;
use App\Entity\WorkoutProgram;
use App\Entity\WorkoutProgramExercise;
use App\Entity\WorkoutSession;
use App\Repository\WorkoutProgramExerciseReaderInterface;
use App\Repository\WorkoutSessionReaderInterface;
use App\Service\Workout\WorkoutSessionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class WorkoutSessionServiceTest extends TestCase
{
    public function testCompleteMarksActiveSessionCompletedAndCalculatesDuration(): void
    {
        $session = (new WorkoutSession(new UserProfile()))
            ->setStartedAt(new \DateTimeImmutable('2026-05-26 10:00:00'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $result = $this->createService($entityManager)->complete(
            $session,
            new \DateTimeImmutable('2026-05-26 10:45:00'),
        );

        self::assertSame($session, $result);
        self::assertSame(WorkoutSession::STATUS_COMPLETED, $session->getStatus());
        self::assertSame(2700, $session->getDurationSeconds());
        self::assertNotNull($session->getCompletedAt());
    }

    public function testCancelMarksActiveSessionCancelled(): void
    {
        $session = new WorkoutSession(new UserProfile());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $result = $this->createService($entityManager)->cancel($session);

        self::assertSame($session, $result);
        self::assertSame(WorkoutSession::STATUS_CANCELLED, $session->getStatus());
        self::assertNull($session->getCompletedAt());
    }

    public function testCannotCompleteNonActiveSession(): void
    {
        $session = new WorkoutSession(new UserProfile());
        $session->cancel();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);

        $this->createService($entityManager)->complete($session);
    }

    public function testStartFreeReusesActiveSession(): void
    {
        $profile = new UserProfile();
        $activeSession = new WorkoutSession($profile);
        $exercise = $this->createExercise();

        $sessionRepository = $this->createStub(WorkoutSessionReaderInterface::class);
        $sessionRepository
            ->method('findActiveForProfile')
            ->willReturn($activeSession);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $result = $this->createService($entityManager, $sessionRepository)->startFree($profile, $exercise);

        self::assertSame($activeSession, $result);
    }

    public function testStartFreeCreatesSessionWhenNoneIsActive(): void
    {
        $profile = new UserProfile();
        $exercise = $this->createExercise();

        $sessionRepository = $this->createStub(WorkoutSessionReaderInterface::class);
        $sessionRepository
            ->method('findActiveForProfile')
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $result = $this->createService($entityManager, $sessionRepository)->startFree($profile, $exercise);

        self::assertSame(WorkoutSession::STATUS_ACTIVE, $result->getStatus());
        self::assertSame(WorkoutSession::TYPE_FREE, $result->getType());
        self::assertSame('Seance libre', $result->getName());
    }

    public function testStartProgramCreatesSessionFromProgramExercises(): void
    {
        $profile = new UserProfile();
        $exercise = $this->createExercise();
        $program = (new WorkoutProgram($profile))->setName('Push');
        $programExercise = (new WorkoutProgramExercise($program, $exercise))
            ->setPosition(2)
            ->setTargetSets(4)
            ->setTargetRepsMin(6)
            ->setTargetRepsMax(8)
            ->setRestSeconds(120);

        $sessionRepository = $this->createStub(WorkoutSessionReaderInterface::class);
        $sessionRepository->method('findActiveForProfile')->willReturn(null);

        $programExerciseRepository = $this->createStub(WorkoutProgramExerciseReaderInterface::class);
        $programExerciseRepository->method('findForProgram')->willReturn([$programExercise]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(6))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $result = $this->createService($entityManager, $sessionRepository, $programExerciseRepository)->startProgram($program);

        self::assertSame(WorkoutSession::TYPE_PROGRAM, $result->getType());
        self::assertSame('Push', $result->getName());
        self::assertSame($program, $result->getProgram());
    }

    private function createService(
        EntityManagerInterface $entityManager,
        ?WorkoutSessionReaderInterface $sessionRepository = null,
        ?WorkoutProgramExerciseReaderInterface $programExerciseRepository = null,
    ): WorkoutSessionService {
        return new WorkoutSessionService(
            $entityManager,
            $sessionRepository ?? $this->createStub(WorkoutSessionReaderInterface::class),
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
