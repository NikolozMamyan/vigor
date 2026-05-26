<?php

namespace App\Repository;

use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;

interface WorkoutSetReaderInterface
{
    /**
     * @return list<WorkoutSet>
     */
    public function findForSessionExercise(WorkoutSessionExercise $sessionExercise): array;

    public function findOneForSessionExerciseAtPosition(WorkoutSessionExercise $sessionExercise, int $position): ?WorkoutSet;
}
