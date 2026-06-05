<?php

namespace App\Service\Workout;

use App\Entity\Exercise;
use App\Entity\PersonalRecord;
use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use App\Repository\PersonalRecordReaderInterface;
use App\Repository\WorkoutSetReaderInterface;
use Doctrine\ORM\EntityManagerInterface;

final class PersonalRecordService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PersonalRecordReaderInterface $recordRepository,
        private readonly WorkoutSetReaderInterface $setRepository,
        private readonly OneRepMaxCalculator $oneRepMaxCalculator,
        private readonly PersonalRecordDetector $recordDetector,
    ) {
    }

    public function recalculateForSet(WorkoutSet $set, bool $includeSet = true): ?PersonalRecord
    {
        return $this->recalculate(
            $set->getSessionExercise()->getSession()->getProfile(),
            $set->getSessionExercise()->getExercise(),
            $set,
            $includeSet,
        );
    }

    public function recalculateForExercise(
        UserProfile $profile,
        Exercise $exercise,
        ?WorkoutSession $excludedSession = null,
        ?WorkoutSessionExercise $excludedSessionExercise = null,
    ): void {
        $this->recalculate(
            $profile,
            $exercise,
            excludedSession: $excludedSession,
            excludedSessionExercise: $excludedSessionExercise,
        );
    }

    private function recalculate(
        UserProfile $profile,
        Exercise $exercise,
        ?WorkoutSet $changedSet = null,
        bool $includeChangedSet = true,
        ?WorkoutSession $excludedSession = null,
        ?WorkoutSessionExercise $excludedSessionExercise = null,
    ): ?PersonalRecord {
        $metric = $profile->getRecordMetricPreference();

        if (PersonalRecord::METRIC_ESTIMATED_1RM !== $metric) {
            return null;
        }

        foreach ($this->recordRepository->findAllForExercise($profile, $exercise, $metric) as $record) {
            $this->entityManager->remove($record);
        }

        $sets = array_values(array_filter(
            $this->setRepository->findCompletedForRecordCalculation($profile, $exercise),
            static function (WorkoutSet $set) use (
                $changedSet,
                $includeChangedSet,
                $excludedSession,
                $excludedSessionExercise,
            ): bool {
                if (null === $set->getCompletedAt()) {
                    return false;
                }

                if (!$includeChangedSet && $set === $changedSet) {
                    return false;
                }

                $sessionExercise = $set->getSessionExercise();

                if ($excludedSession && $sessionExercise->getSession() === $excludedSession) {
                    return false;
                }

                return !$excludedSessionExercise || $sessionExercise !== $excludedSessionExercise;
            },
        ));

        if (
            $changedSet
            && $includeChangedSet
            && null !== $changedSet->getCompletedAt()
            && !\in_array($changedSet, $sets, true)
        ) {
            $sets[] = $changedSet;
        }

        usort($sets, $this->compareSets(...));

        $currentBest = null;
        $changedSetRecord = null;

        foreach ($sets as $set) {
            $estimate = $this->oneRepMaxCalculator->estimate($set->getWeight(), $set->getReps());

            if ($estimate <= 0) {
                continue;
            }

            $set->complete($set->getCompletedAt() ?? new \DateTimeImmutable(), $estimate);

            if (!$this->recordDetector->shouldCreateRecord($estimate, $currentBest)) {
                continue;
            }

            $record = $this->recordDetector->buildRecord($profile, $exercise, $set, $estimate, $currentBest);
            $this->entityManager->persist($record);
            $currentBest = $record;

            if ($set === $changedSet) {
                $changedSetRecord = $record;
            }
        }

        return $changedSetRecord;
    }

    private function compareSets(WorkoutSet $left, WorkoutSet $right): int
    {
        $leftCompletedAt = $left->getCompletedAt()?->getTimestamp() ?? 0;
        $rightCompletedAt = $right->getCompletedAt()?->getTimestamp() ?? 0;

        if ($leftCompletedAt !== $rightCompletedAt) {
            return $leftCompletedAt <=> $rightCompletedAt;
        }

        $leftSessionExercise = $left->getSessionExercise();
        $rightSessionExercise = $right->getSessionExercise();
        $leftSessionStartedAt = $leftSessionExercise->getSession()->getStartedAt()->getTimestamp();
        $rightSessionStartedAt = $rightSessionExercise->getSession()->getStartedAt()->getTimestamp();

        if ($leftSessionStartedAt !== $rightSessionStartedAt) {
            return $leftSessionStartedAt <=> $rightSessionStartedAt;
        }

        if ($leftSessionExercise->getPosition() !== $rightSessionExercise->getPosition()) {
            return $leftSessionExercise->getPosition() <=> $rightSessionExercise->getPosition();
        }

        if ($left->getPosition() !== $right->getPosition()) {
            return $left->getPosition() <=> $right->getPosition();
        }

        return ($left->getId() ?? spl_object_id($left)) <=> ($right->getId() ?? spl_object_id($right));
    }
}
