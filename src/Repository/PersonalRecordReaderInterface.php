<?php

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\PersonalRecord;
use App\Entity\UserProfile;
use App\Entity\WorkoutSet;

interface PersonalRecordReaderInterface
{
    public function findBest(UserProfile $profile, Exercise $exercise, string $metric): ?PersonalRecord;

    public function findForWorkoutSet(WorkoutSet $set, string $metric): ?PersonalRecord;
}
