<?php

namespace App\Repository;

use App\Entity\WorkoutProgram;
use App\Entity\WorkoutProgramExercise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class WorkoutProgramExerciseRepository extends ServiceEntityRepository implements WorkoutProgramExerciseReaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutProgramExercise::class);
    }

    /**
     * @return list<WorkoutProgramExercise>
     */
    public function findForProgram(WorkoutProgram $program): array
    {
        return $this->findBy(['program' => $program], ['position' => 'ASC']);
    }
}
