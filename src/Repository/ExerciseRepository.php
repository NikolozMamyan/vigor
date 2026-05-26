<?php

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\UserProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercise::class);
    }

    /**
     * @return list<Exercise>
     */
    public function searchForProfile(string $query, ?UserProfile $profile = null, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('exercise')
            ->andWhere('LOWER(exercise.name) LIKE :query OR LOWER(exercise.muscleGroup) LIKE :query OR LOWER(exercise.equipment) LIKE :query')
            ->setParameter('query', '%'.mb_strtolower($query).'%')
            ->setMaxResults($limit)
            ->orderBy('exercise.source', 'DESC')
            ->addOrderBy('exercise.name', 'ASC');

        if ($profile) {
            $qb
                ->andWhere('exercise.source = :vigorSource OR exercise.createdByProfile = :profile')
                ->setParameter('vigorSource', Exercise::SOURCE_VIGOR)
                ->setParameter('profile', $profile);
        } else {
            $qb
                ->andWhere('exercise.source = :vigorSource')
                ->setParameter('vigorSource', Exercise::SOURCE_VIGOR);
        }

        return $qb->getQuery()->getResult();
    }
}
