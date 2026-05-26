<?php

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\PersonalRecord;
use App\Entity\UserProfile;
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
        return $this->findOneBy([
            'profile' => $profile,
            'exercise' => $exercise,
            'metric' => $metric,
        ], [
            'value' => 'DESC',
        ]);
    }

    /**
     * @return list<PersonalRecord>
     */
    public function findRecentForProfile(UserProfile $profile, int $limit = 10): array
    {
        return $this->findBy(['profile' => $profile], ['achievedAt' => 'DESC'], $limit);
    }
}
