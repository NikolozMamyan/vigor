<?php

namespace App\Service\Workout;

use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Repository\WorkoutSetReaderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class WorkoutSetService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkoutSetReaderInterface $setRepository,
        private readonly OneRepMaxCalculator $oneRepMaxCalculator,
        private readonly PersonalRecordService $personalRecordService,
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

    public function createCompletedForHistory(WorkoutSessionExercise $sessionExercise, int $position, float $weight, int $reps): WorkoutSet
    {
        $this->assertValidSetData($weight, $reps);

        $existingSet = $this->setRepository->findOneForSessionExerciseAtPosition($sessionExercise, $position);

        if ($existingSet) {
            return $this->update($existingSet, $weight, $reps);
        }

        $estimate = $this->oneRepMaxCalculator->estimate($weight, $reps);
        $completedAt = $sessionExercise->getSession()->getCompletedAt() ?? new \DateTimeImmutable();
        $set = (new WorkoutSet($sessionExercise))
            ->setPosition($position)
            ->setWeight($weight)
            ->setReps($reps)
            ->complete($completedAt, $estimate);

        $this->entityManager->persist($set);
        $this->personalRecordService->detectAfterCompletedSet($set, $estimate);
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
            $estimate = $this->oneRepMaxCalculator->estimate($weight, $reps);
            $set->complete($set->getCompletedAt(), $estimate);
            $this->personalRecordService->syncAfterUpdatedSet($set, $estimate);
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

        $estimate = $this->oneRepMaxCalculator->estimate($set->getWeight(), $set->getReps());

        $set->complete(new \DateTimeImmutable(), $estimate);
        $record = $this->personalRecordService->detectAfterCompletedSet($set, $estimate);

        $this->entityManager->flush();

        return [
            'set' => $set,
            'estimatedOneRepMax' => $estimate,
            'recordCreated' => null !== $record,
            'recordValue' => $record?->getValue(),
        ];
    }

    public function uncomplete(WorkoutSet $set): WorkoutSet
    {
        $this->personalRecordService->removeForSet($set);
        $set->uncomplete();

        $this->entityManager->flush();

        return $set;
    }

    public function delete(WorkoutSet $set): void
    {
        $this->personalRecordService->removeForSet($set);
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
