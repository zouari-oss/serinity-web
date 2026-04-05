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
}
