<?php

namespace App\Service\Workout;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use App\Entity\WorkoutProgram;
use App\Entity\WorkoutProgramExercise;
use Doctrine\ORM\EntityManagerInterface;

final class WorkoutProgramService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function create(UserProfile $profile, Exercise $firstExercise, string $name): WorkoutProgram
    {
        $name = trim($name);

        if (mb_strlen($name) < 2) {
            throw new \InvalidArgumentException('Program name must contain at least 2 characters.');
        }

        $program = (new WorkoutProgram($profile))
            ->setName($name)
            ->setDescription('Programme personnalise');

        $programExercise = (new WorkoutProgramExercise($program, $firstExercise))
            ->setPosition(1)
            ->setTargetSets(3)
            ->setTargetRepsMin(8)
            ->setTargetRepsMax(10)
            ->setRestSeconds(90);

        $this->entityManager->persist($program);
        $this->entityManager->persist($programExercise);
        $this->entityManager->flush();

        return $program;
    }
}
