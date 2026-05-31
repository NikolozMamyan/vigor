<?php

namespace App\Service\Workout;

use App\Entity\PersonalRecord;
use App\Entity\WorkoutSet;
use App\Repository\PersonalRecordReaderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class PersonalRecordService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PersonalRecordReaderInterface $recordRepository,
        private readonly PersonalRecordDetector $recordDetector,
    ) {
    }

    public function detectAfterCompletedSet(WorkoutSet $set, float $candidateValue): ?PersonalRecord
    {
        $sessionExercise = $set->getSessionExercise();
        $session = $sessionExercise->getSession();
        $profile = $session->getProfile();
        $exercise = $sessionExercise->getExercise();
        $metric = $profile->getRecordMetricPreference();

        if (PersonalRecord::METRIC_ESTIMATED_1RM !== $metric) {
            return null;
        }

        $currentBest = $this->recordRepository->findBest($profile, $exercise, $metric);

        if (!$this->recordDetector->shouldCreateRecord($candidateValue, $currentBest)) {
            return null;
        }

        $record = $this->recordDetector->buildRecord($profile, $exercise, $set, $candidateValue, $currentBest);
        $this->entityManager->persist($record);

        return $record;
    }

    public function syncAfterUpdatedSet(WorkoutSet $set, float $candidateValue): ?PersonalRecord
    {
        $sessionExercise = $set->getSessionExercise();
        $session = $sessionExercise->getSession();
        $profile = $session->getProfile();
        $exercise = $sessionExercise->getExercise();
        $metric = $profile->getRecordMetricPreference();

        if (PersonalRecord::METRIC_ESTIMATED_1RM !== $metric) {
            return null;
        }

        $existingRecord = $this->recordRepository->findForWorkoutSet($set, $metric);

        if ($existingRecord) {
            $previousValue = $existingRecord->getPreviousValue();

            if (null !== $previousValue && $candidateValue <= $previousValue) {
                $this->entityManager->remove($existingRecord);

                return null;
            }

            $existingRecord->setValue($candidateValue);

            return $existingRecord;
        }

        $currentBest = $this->recordRepository->findBest($profile, $exercise, $metric);

        if (!$this->recordDetector->shouldCreateRecord($candidateValue, $currentBest)) {
            return null;
        }

        $record = $this->recordDetector->buildRecord($profile, $exercise, $set, $candidateValue, $currentBest);
        $this->entityManager->persist($record);

        return $record;
    }
}
