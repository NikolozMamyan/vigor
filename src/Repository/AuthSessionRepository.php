<?php

namespace App\Repository;

use App\Entity\AuthSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class AuthSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthSession::class);
    }

    public function findActiveByTokenHash(string $tokenHash, \DateTimeImmutable $now): ?AuthSession
    {
        return $this->createQueryBuilder('session')
            ->andWhere('session.tokenHash = :tokenHash')
            ->andWhere('session.revokedAt IS NULL')
            ->andWhere('session.expiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
