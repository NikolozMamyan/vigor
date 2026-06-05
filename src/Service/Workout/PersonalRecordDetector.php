<?php

namespace App\Service\Workout;

use App\Entity\Exercise;
use App\Entity\PersonalRecord;
use App\Entity\UserProfile;

final class PersonalRecordDetector
{
    public function shouldCreateRecord(float $candidateValue, ?PersonalRecord $currentBest): bool
    {
        if ($candidateValue <= 0) {
            return false;
        }

        return null === $currentBest || $candidateValue > $currentBest->getValue();
    }

    public function buildRecord(UserProfile $profile, Exercise $exercise, \App\Entity\WorkoutSet $set, float $candidateValue, ?PersonalRecord $currentBest): PersonalRecord
    {
        return (new PersonalRecord($profile, $exercise, $set, $candidateValue))
            ->setMetric(PersonalRecord::METRIC_ESTIMATED_1RM)
            ->setPreviousValue($currentBest?->getValue())
            ->setAchievedAt($set->getCompletedAt() ?? new \DateTimeImmutable());
    }
}
