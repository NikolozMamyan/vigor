<?php

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\PersonalRecord;
use App\Entity\UserProfile;

interface PersonalRecordReaderInterface
{
    public function findBest(UserProfile $profile, Exercise $exercise, string $metric): ?PersonalRecord;
}
