<?php

namespace App\Tests\Entity;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use App\Entity\WorkoutProgram;
use App\Entity\WorkoutProgramExercise;
use PHPUnit\Framework\TestCase;

final class WorkoutProgramExerciseTest extends TestCase
{
    public function testItStoresNullableTargetWeight(): void
    {
        $program = new WorkoutProgram(new UserProfile());
        $exercise = (new Exercise())
            ->setName('Squat')
            ->setSlug('squat')
            ->setMuscleGroup('Jambes')
            ->setEquipment('Barre');

        $programExercise = (new WorkoutProgramExercise($program, $exercise))
            ->setTargetWeight(92.5);

        self::assertSame(92.5, $programExercise->getTargetWeight());

        $programExercise->setTargetWeight(null);

        self::assertNull($programExercise->getTargetWeight());
    }
}
