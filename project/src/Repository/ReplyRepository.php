<?php

namespace App\Repository;

use App\Entity\ForumThread;
use App\Entity\Reply;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reply>
 */
class ReplyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reply::class);
    }

    /**
     * @return Reply[]
     */
    public function findTopLevelByThread(ForumThread $thread): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.thread = :thread')
            ->andWhere('r.parent IS NULL')
            ->setParameter('thread', $thread)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
