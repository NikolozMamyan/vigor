<?php

namespace App\Repository;

use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class WorkoutSessionExerciseRepository extends ServiceEntityRepository implements WorkoutSessionExerciseReaderInterface
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

    public function nextPositionForSession(WorkoutSession $session): int
    {
        $position = $this->createQueryBuilder('sessionExercise')
            ->select('MAX(sessionExercise.position)')
            ->andWhere('sessionExercise.session = :session')
            ->setParameter('session', $session)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $position) + 1;
    }
}
