<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return Notification[]
     */
        public function findForUser(string $userId): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.thread', 't')
            ->addSelect('t')
            ->andWhere('n.recipientId = :userId')
            ->andWhere('t.authorId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

        public function countUnread(string $userId): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->leftJoin('n.thread', 't')
            ->andWhere('n.recipientId = :userId')
            ->andWhere('t.authorId = :userId')
            ->andWhere('n.seen = false')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

        public function deleteAllForUser(string $userId): void
    {
        $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.recipientId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
