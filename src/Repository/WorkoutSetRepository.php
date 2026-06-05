<?php

namespace App\Repository;

use App\Entity\Exercise;
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

    /**
     * @return list<WorkoutSet>
     */
    public function findPreviousCompletedForExercise(UserProfile $profile, Exercise $exercise, ?WorkoutSession $currentSession = null): array
    {
        $latestSessionQuery = $this->createQueryBuilder('workoutSet')
            ->select('session.id AS sessionId')
            ->join('workoutSet.sessionExercise', 'sessionExercise')
            ->join('sessionExercise.session', 'session')
            ->andWhere('session.profile = :profile')
            ->andWhere('session.status = :sessionStatus')
            ->andWhere('sessionExercise.exercise = :exercise')
            ->andWhere('session.completedAt IS NOT NULL')
            ->andWhere('workoutSet.completedAt IS NOT NULL')
            ->setParameter('profile', $profile)
            ->setParameter('sessionStatus', WorkoutSession::STATUS_COMPLETED)
            ->setParameter('exercise', $exercise)
            ->orderBy('session.completedAt', 'DESC')
            ->addOrderBy('workoutSet.completedAt', 'DESC')
            ->setMaxResults(1);

        if ($currentSession?->getId()) {
            $latestSessionQuery
                ->andWhere('session.id != :currentSessionId')
                ->setParameter('currentSessionId', $currentSession->getId());
        }

        $latestSession = $latestSessionQuery->getQuery()->getOneOrNullResult();

        if (!$latestSession) {
            return [];
        }

        return $this->createQueryBuilder('workoutSet')
            ->join('workoutSet.sessionExercise', 'sessionExercise')
            ->join('sessionExercise.session', 'session')
            ->andWhere('session.id = :sessionId')
            ->andWhere('sessionExercise.exercise = :exercise')
            ->andWhere('workoutSet.completedAt IS NOT NULL')
            ->setParameter('sessionId', (int) $latestSession['sessionId'])
            ->setParameter('exercise', $exercise)
            ->orderBy('workoutSet.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<WorkoutSet>
     */
    public function findCompletedForRecordCalculation(UserProfile $profile, Exercise $exercise): array
    {
        return $this->createQueryBuilder('workoutSet')
            ->join('workoutSet.sessionExercise', 'sessionExercise')
            ->join('sessionExercise.session', 'session')
            ->andWhere('session.profile = :profile')
            ->andWhere('sessionExercise.exercise = :exercise')
            ->andWhere('session.status IN (:sessionStatuses)')
            ->andWhere('workoutSet.completedAt IS NOT NULL')
            ->setParameter('profile', $profile)
            ->setParameter('exercise', $exercise)
            ->setParameter('sessionStatuses', [
                WorkoutSession::STATUS_ACTIVE,
                WorkoutSession::STATUS_COMPLETED,
            ])
            ->orderBy('workoutSet.completedAt', 'ASC')
            ->addOrderBy('session.startedAt', 'ASC')
            ->addOrderBy('sessionExercise.position', 'ASC')
            ->addOrderBy('workoutSet.position', 'ASC')
            ->addOrderBy('workoutSet.id', 'ASC')
            ->getQuery()
            ->getResult();
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
