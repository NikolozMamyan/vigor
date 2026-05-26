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
}
