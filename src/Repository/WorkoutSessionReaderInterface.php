<?php

namespace App\Repository;

use App\Entity\UserProfile;
use App\Entity\WorkoutSession;

interface WorkoutSessionReaderInterface
{
    public function findActiveForProfile(UserProfile $profile): ?WorkoutSession;
}
