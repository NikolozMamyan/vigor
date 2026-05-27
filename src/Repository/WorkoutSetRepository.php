<?php

namespace App\Repository;

use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use App\Entity\WorkoutSessionExercise;
use App\Entity\WorkoutSet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class WorkoutSetRepository extends ServiceEntityRepository implements WorkoutSetReaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutSet::class);
    }

    /**
     * @return list<WorkoutSet>
     */
    public function findCompletedForProfileBetween(UserProfile $profile, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('workoutSet')
            ->join('workoutSet.sessionExercise', 'sessionExercise')
            ->join('sessionExercise.session', 'session')
            ->andWhere('session.profile = :profile')
            ->andWhere('session.status = :sessionStatus')
            ->andWhere('workoutSet.completedAt IS NOT NULL')
            ->andWhere('workoutSet.completedAt >= :from')
            ->andWhere('workoutSet.completedAt < :to')
            ->setParameter('profile', $profile)
            ->setParameter('sessionStatus', WorkoutSession::STATUS_COMPLETED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('workoutSet.completedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<WorkoutSet>
     */
    public function findForSessionExercise(WorkoutSessionExercise $sessionExercise): array
    {
        return $this->findBy(['sessionExercise' => $sessionExercise], ['position' => 'ASC']);
    }

    public function findOneForSessionExerciseAtPosition(WorkoutSessionExercise $sessionExercise, int $position): ?WorkoutSet
    {
        return $this->findOneBy([
            'sessionExercise' => $sessionExercise,
            'position' => $position,
        ]);
    }

    public function sumCompletedVolumeForProfile(UserProfile $profile): float
    {
        return (float) ($this->createQueryBuilder('workoutSet')
            ->select('SUM(workoutSet.weight * workoutSet.reps)')
            ->join('workoutSet.sessionExercise', 'sessionExercise')
            ->join('sessionExercise.session', 'session')
            ->andWhere('session.profile = :profile')
            ->andWhere('session.status = :sessionStatus')
            ->andWhere('workoutSet.completedAt IS NOT NULL')
            ->setParameter('profile', $profile)
            ->setParameter('sessionStatus', WorkoutSession::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }
}
