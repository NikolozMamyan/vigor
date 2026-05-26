<?php

namespace App\Repository;

use App\Entity\UserProfile;
use App\Entity\WorkoutSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class WorkoutSessionRepository extends ServiceEntityRepository implements WorkoutSessionReaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutSession::class);
    }

    public function findActiveForProfile(UserProfile $profile): ?WorkoutSession
    {
        return $this->findOneBy([
            'profile' => $profile,
            'status' => WorkoutSession::STATUS_ACTIVE,
        ], [
            'startedAt' => 'DESC',
        ]);
    }

    public function countCompletedForProfile(UserProfile $profile): int
    {
        return (int) $this->createQueryBuilder('session')
            ->select('COUNT(session.id)')
            ->andWhere('session.profile = :profile')
            ->andWhere('session.status = :status')
            ->setParameter('profile', $profile)
            ->setParameter('status', WorkoutSession::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function longestCompletedStreakDays(UserProfile $profile): int
    {
        $sessions = $this->createQueryBuilder('session')
            ->andWhere('session.profile = :profile')
            ->andWhere('session.status = :status')
            ->andWhere('session.completedAt IS NOT NULL')
            ->setParameter('profile', $profile)
            ->setParameter('status', WorkoutSession::STATUS_COMPLETED)
            ->orderBy('session.completedAt', 'ASC')
            ->getQuery()
            ->getResult();

        $days = [];

        foreach ($sessions as $session) {
            $completedAt = $session->getCompletedAt();

            if ($completedAt) {
                $days[$completedAt->format('Y-m-d')] = true;
            }
        }

        $longest = 0;
        $current = 0;
        $previous = null;

        foreach (array_keys($days) as $day) {
            $date = new \DateTimeImmutable($day);
            $current = $previous && $date->modify('-1 day')->format('Y-m-d') === $previous->format('Y-m-d')
                ? $current + 1
                : 1;
            $longest = max($longest, $current);
            $previous = $date;
        }

        return $longest;
    }
}
