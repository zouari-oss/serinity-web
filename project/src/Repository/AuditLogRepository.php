<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuditLog> */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /** @return list<AuditLog> */
    public function findRecentForUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.authSession', 's')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<AuditLog> */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.authSession', 's')
            ->addSelect('s')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count audit events from the last N days.
     */
    public function countRecentEvents(int $days = 7): int
    {
        $since = new \DateTimeImmutable("-{$days} days");
        
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

