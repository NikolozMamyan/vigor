<?php

namespace App\Repository;

use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class WorkoutSessionExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutSessionExercise::class);
    }

    /**
     * @return list<WorkoutSessionExercise>
     */
    public function findForSession(WorkoutSession $session): array
    {
        return $this->findBy(['session' => $session], ['position' => 'ASC']);
    }
}
