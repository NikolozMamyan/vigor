<?php

namespace App\Repository;

use App\Entity\WorkoutProgram;
use App\Entity\WorkoutProgramExercise;

interface WorkoutProgramExerciseReaderInterface
{
    /**
     * @return list<WorkoutProgramExercise>
     */
    public function findForProgram(WorkoutProgram $program): array;
}
