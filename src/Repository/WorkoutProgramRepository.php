<?php

namespace App\Repository;

use App\Entity\UserProfile;
use App\Entity\WorkoutProgram;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class WorkoutProgramRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutProgram::class);
    }

    /**
     * @return list<WorkoutProgram>
     */
    public function findForProfile(UserProfile $profile): array
    {
        return $this->findBy(['profile' => $profile], ['createdAt' => 'DESC']);
    }
}
