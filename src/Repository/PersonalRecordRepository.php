<?php

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\PersonalRecord;
use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PersonalRecordRepository extends ServiceEntityRepository implements PersonalRecordReaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonalRecord::class);
    }

    public function findBest(UserProfile $profile, Exercise $exercise, string $metric): ?PersonalRecord
    {
        return $this->createQueryBuilder('record')
            ->join('record.workoutSet', 'workoutSet')
            ->join('workoutSet.sessionExercise', 'sessionExercise')
            ->join('sessionExercise.session', 'session')
            ->andWhere('record.profile = :profile')
            ->andWhere('record.exercise = :exercise')
            ->andWhere('record.metric = :metric')
            ->andWhere('session.status = :sessionStatus')
            ->setParameter('profile', $profile)
            ->setParameter('exercise', $exercise)
            ->setParameter('metric', $metric)
            ->setParameter('sessionStatus', WorkoutSession::STATUS_COMPLETED)
            ->orderBy('record.value', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<PersonalRecord>
     */
    public function findRecentForProfile(UserProfile $profile, int $limit = 10): array
    {
        return $this->createQueryBuilder('record')
            ->join('record.workoutSet', 'workoutSet')
            ->join('workoutSet.sessionExercise', 'sessionExercise')
            ->join('sessionExercise.session', 'session')
            ->andWhere('record.profile = :profile')
            ->andWhere('session.status = :sessionStatus')
            ->setParameter('profile', $profile)
            ->setParameter('sessionStatus', WorkoutSession::STATUS_COMPLETED)
            ->orderBy('record.achievedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countForProfile(UserProfile $profile): int
    {
        return (int) $this->createQueryBuilder('record')
            ->select('COUNT(record.id)')
            ->join('record.workoutSet', 'workoutSet')
            ->join('workoutSet.sessionExercise', 'sessionExercise')
            ->join('sessionExercise.session', 'session')
            ->andWhere('record.profile = :profile')
            ->andWhere('session.status = :sessionStatus')
            ->setParameter('profile', $profile)
            ->setParameter('sessionStatus', WorkoutSession::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
