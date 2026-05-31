<?php

namespace App\Service\Workout;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use App\Entity\WorkoutProgram;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Repository\WorkoutProgramExerciseReaderInterface;
use App\Repository\WorkoutSessionReaderInterface;
use App\Repository\WorkoutSessionExerciseReaderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class WorkoutSessionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkoutSessionReaderInterface $sessionRepository,
        private readonly WorkoutProgramExerciseReaderInterface $programExerciseRepository,
        private readonly ?WorkoutSessionExerciseReaderInterface $sessionExerciseRepository = null,
    ) {
    }

    public function startFree(UserProfile $profile, Exercise $exercise): WorkoutSession
    {
        $activeSession = $this->sessionRepository->findActiveForProfile($profile);

        if ($activeSession) {
            return $activeSession;
        }

        $session = (new WorkoutSession($profile))
            ->setName('Seance libre')
            ->setType(WorkoutSession::TYPE_FREE);

        $sessionExercise = (new WorkoutSessionExercise($session, $exercise))
            ->setPosition(1)
            ->setTargetSets(3)
            ->setTargetRepsMin(8)
            ->setTargetRepsMax(10)
            ->setRestSeconds(90);

        $this->entityManager->persist($session);
        $this->entityManager->persist($sessionExercise);
        $this->entityManager->flush();

        return $session;
    }

    public function startProgram(WorkoutProgram $program): WorkoutSession
    {
        $profile = $program->getProfile();
        $activeSession = $this->sessionRepository->findActiveForProfile($profile);

        if ($activeSession) {
            return $activeSession;
        }

        $programExercises = $this->programExerciseRepository->findForProgram($program);

        if ([] === $programExercises) {
            throw new \InvalidArgumentException('Program must contain at least one exercise.');
        }

        $session = (new WorkoutSession($profile))
            ->setName($program->getName())
            ->setProgram($program)
            ->setType(WorkoutSession::TYPE_PROGRAM);

        $this->entityManager->persist($session);

        foreach ($programExercises as $programExercise) {
            $sessionExercise = (new WorkoutSessionExercise($session, $programExercise->getExercise()))
                ->setPosition($programExercise->getPosition())
                ->setTargetSets($programExercise->getTargetSets())
                ->setTargetRepsMin($programExercise->getTargetRepsMin())
                ->setTargetRepsMax($programExercise->getTargetRepsMax())
                ->setRestSeconds($programExercise->getRestSeconds());

            $this->entityManager->persist($sessionExercise);

            for ($position = 1; $position <= $programExercise->getTargetSets(); ++$position) {
                $set = (new WorkoutSet($sessionExercise))
                    ->setPosition($position)
                    ->setWeight($programExercise->getTargetWeight() ?? 0.0)
                    ->setReps($programExercise->getTargetRepsMin());

                $this->entityManager->persist($set);
            }
        }

        $this->entityManager->flush();

        return $session;
    }

    public function complete(WorkoutSession $session, ?\DateTimeImmutable $completedAt = null): WorkoutSession
    {
        $this->assertActive($session);

        $session->complete($completedAt ?? new \DateTimeImmutable());
        $this->entityManager->flush();

        return $session;
    }

    public function addExercise(WorkoutSession $session, Exercise $exercise): WorkoutSessionExercise
    {
        $this->assertActive($session);

        $sessionExercise = (new WorkoutSessionExercise($session, $exercise))
            ->setPosition($this->sessionExerciseRepository?->nextPositionForSession($session) ?? 1)
            ->setTargetSets(3)
            ->setTargetRepsMin(8)
            ->setTargetRepsMax(10)
            ->setRestSeconds(90);

        $this->entityManager->persist($sessionExercise);
        $this->entityManager->flush();

        return $sessionExercise;
    }

    public function addHistoryExercise(WorkoutSession $session, Exercise $exercise): WorkoutSessionExercise
    {
        $this->assertCompletedHistorySession($session);

        $sessionExercise = (new WorkoutSessionExercise($session, $exercise))
            ->setPosition($this->sessionExerciseRepository?->nextPositionForSession($session) ?? 1)
            ->setTargetSets(3)
            ->setTargetRepsMin(8)
            ->setTargetRepsMax(10)
            ->setRestSeconds(90);

        $this->entityManager->persist($sessionExercise);
        $this->entityManager->flush();

        return $sessionExercise;
    }

    public function removeExercise(WorkoutSessionExercise $sessionExercise): void
    {
        $session = $sessionExercise->getSession();
        $this->assertActive($session);

        $sessionExercises = $this->sessionExerciseRepository?->findForSession($session) ?? [];

        if (count($sessionExercises) <= 1) {
            throw new \InvalidArgumentException('A workout session must keep at least one exercise.');
        }

        $this->entityManager->remove($sessionExercise);
        $this->entityManager->flush();
    }

    public function cancel(WorkoutSession $session): WorkoutSession
    {
        $this->assertActive($session);

        $session->cancel();
        $this->entityManager->flush();

        return $session;
    }

    public function deleteHistorySession(WorkoutSession $session): void
    {
        $this->assertCompletedHistorySession($session);

        $this->entityManager->remove($session);
        $this->entityManager->flush();
    }

    private function assertActive(WorkoutSession $session): void
    {
        if (WorkoutSession::STATUS_ACTIVE !== $session->getStatus()) {
            throw new \InvalidArgumentException('Only an active workout session can be changed.');
        }
    }

    private function assertCompletedHistorySession(WorkoutSession $session): void
    {
        if (WorkoutSession::STATUS_COMPLETED !== $session->getStatus()) {
            throw new \InvalidArgumentException('Only completed workout sessions can be edited from history.');
        }
    }
}
