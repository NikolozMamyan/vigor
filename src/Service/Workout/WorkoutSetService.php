<?php

namespace App\Service\Workout;

use App\Entity\PersonalRecord;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Repository\PersonalRecordReaderInterface;
use App\Repository\WorkoutSetReaderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class WorkoutSetService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkoutSetReaderInterface $setRepository,
        private readonly PersonalRecordReaderInterface $recordRepository,
        private readonly OneRepMaxCalculator $oneRepMaxCalculator,
        private readonly PersonalRecordDetector $recordDetector,
    ) {
    }

    public function create(WorkoutSessionExercise $sessionExercise, int $position, float $weight, int $reps): WorkoutSet
    {
        $this->assertValidSetData($weight, $reps);

        $existingSet = $this->setRepository->findOneForSessionExerciseAtPosition($sessionExercise, $position);

        if ($existingSet) {
            return $this->update($existingSet, $weight, $reps);
        }

        $set = (new WorkoutSet($sessionExercise))
            ->setPosition($position)
            ->setWeight($weight)
            ->setReps($reps);

        $this->entityManager->persist($set);
        $this->entityManager->flush();

        return $set;
    }

    public function update(WorkoutSet $set, float $weight, int $reps): WorkoutSet
    {
        $this->assertValidSetData($weight, $reps);

        $set
            ->setWeight($weight)
            ->setReps($reps);

        if ($set->getCompletedAt()) {
            $set->complete($set->getCompletedAt(), $this->oneRepMaxCalculator->estimate($weight, $reps));
        }

        $this->entityManager->flush();

        return $set;
    }

    /**
     * @return array{set: WorkoutSet, estimatedOneRepMax: float, recordCreated: bool, recordValue: float|null}
     */
    public function complete(WorkoutSet $set): array
    {
        $this->assertValidSetData($set->getWeight(), $set->getReps());

        $sessionExercise = $set->getSessionExercise();
        $session = $sessionExercise->getSession();
        $profile = $session->getProfile();
        $exercise = $sessionExercise->getExercise();
        $estimate = $this->oneRepMaxCalculator->estimate($set->getWeight(), $set->getReps());
        $currentBest = $this->recordRepository->findBest($profile, $exercise, PersonalRecord::METRIC_ESTIMATED_1RM);
        $recordCreated = false;
        $recordValue = null;

        $set->complete(new \DateTimeImmutable(), $estimate);

        if ($this->recordDetector->shouldCreateRecord($estimate, $currentBest)) {
            $record = $this->recordDetector->buildRecord($profile, $exercise, $set, $estimate, $currentBest);
            $this->entityManager->persist($record);
            $recordCreated = true;
            $recordValue = $record->getValue();
        }

        $this->entityManager->flush();

        return [
            'set' => $set,
            'estimatedOneRepMax' => $estimate,
            'recordCreated' => $recordCreated,
            'recordValue' => $recordValue,
        ];
    }

    public function delete(WorkoutSet $set): void
    {
        $this->entityManager->remove($set);
        $this->entityManager->flush();
    }

    public function nextPosition(WorkoutSessionExercise $sessionExercise): int
    {
        $sets = $this->setRepository->findForSessionExercise($sessionExercise);

        if ([] === $sets) {
            return 1;
        }

        return max(array_map(static fn (WorkoutSet $set): int => $set->getPosition(), $sets)) + 1;
    }

    private function assertValidSetData(float $weight, int $reps): void
    {
        if ($weight <= 0 || $reps <= 0) {
            throw new \InvalidArgumentException('Weight and reps must be greater than zero.');
        }
    }
}
