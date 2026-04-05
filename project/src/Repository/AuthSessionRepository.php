<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuthSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuthSession> */
class AuthSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthSession::class);
    }

    /** @return list<AuthSession> */
    public function findActiveForUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.revoked = :revoked')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('revoked', false)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findValidByRefreshToken(string $refreshToken): ?AuthSession
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.refreshToken = :refreshToken')
            ->andWhere('s.revoked = :revoked')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('refreshToken', $refreshToken)
            ->setParameter('revoked', false)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
