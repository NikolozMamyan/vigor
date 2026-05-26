<?php

namespace App\Repository;

use App\Entity\UserProfile;
use App\Entity\WeeklyGoal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class WeeklyGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeeklyGoal::class);
    }

    public function findForProfileAndWeek(UserProfile $profile, \DateTimeImmutable $weekStartDate): ?WeeklyGoal
    {
        return $this->findOneBy([
            'profile' => $profile,
            'weekStartDate' => $weekStartDate->setTime(0, 0),
        ]);
    }
}
