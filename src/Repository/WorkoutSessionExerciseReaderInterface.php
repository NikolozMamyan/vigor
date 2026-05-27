<?php

namespace App\Repository;

use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;

interface WorkoutSessionExerciseReaderInterface
{
    /**
     * @return list<WorkoutSessionExercise>
     */
    public function findForSession(WorkoutSession $session): array;

    public function nextPositionForSession(WorkoutSession $session): int;
}
