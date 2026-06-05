<?php

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;

interface WorkoutSetReaderInterface
{
    /**
     * @return list<WorkoutSet>
     */
    public function findForSessionExercise(WorkoutSessionExercise $sessionExercise): array;

    public function findOneForSessionExerciseAtPosition(WorkoutSessionExercise $sessionExercise, int $position): ?WorkoutSet;

    /**
     * @return list<WorkoutSet>
     */
    public function findPreviousCompletedForExercise(UserProfile $profile, Exercise $exercise, ?WorkoutSession $currentSession = null): array;

    /**
     * @return list<WorkoutSet>
     */
    public function findCompletedForRecordCalculation(UserProfile $profile, Exercise $exercise): array;
}
