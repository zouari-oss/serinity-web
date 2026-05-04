<?php

namespace App\Repository;

use App\Entity\ForumThread;
use App\Enum\ThreadStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumThread>
 */
class ForumThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumThread::class);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return ForumThread[]
     */
    public function findFeed(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
            ->addSelect('c')
            ->orderBy('t.isPinned', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        if (!empty($filters['search'])) {
            $qb->andWhere('LOWER(t.title) LIKE :q OR LOWER(t.content) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower((string) $filters['search']).'%');
        }

        if (!empty($filters['status']) && $filters['status'] instanceof ThreadStatus) {
            $qb->andWhere('t.status = :status')->setParameter('status', $filters['status']);
        }

        if (($filters['excludeArchived'] ?? false) === true && empty($filters['status'])) {
            $qb->andWhere('t.status != :archivedStatus')
                ->setParameter('archivedStatus', ThreadStatus::ARCHIVED);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('t.type = :type')->setParameter('type', $filters['type']);
        }

        if (!empty($filters['categoryId'])) {
            $qb->andWhere('c.id = :categoryId')->setParameter('categoryId', $filters['categoryId']);
        }

        if (!empty($filters['authorId'])) {
            $qb->andWhere('t.authorId = :authorId')->setParameter('authorId', (string) $filters['authorId']);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneSuggestedInCategory(string $userId, int $categoryId): ?ForumThread
    {
        $result = $this->createSuggestionQueryBuilder($userId)
            ->andWhere('c.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof ForumThread ? $result : null;
    }

    public function findOneSuggestedAnyCategory(string $userId): ?ForumThread
    {
        $result = $this->createSuggestionQueryBuilder($userId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof ForumThread ? $result : null;
    }

    private function createSuggestionQueryBuilder(string $userId): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
            ->addSelect('c')
            ->leftJoin('t.interactions', 'pi', 'WITH', 'pi.userId = :userId')
            ->leftJoin('t.replies', 'ur', 'WITH', 'ur.authorId = :userId')
            ->andWhere('t.status != :archivedStatus')
            ->andWhere('t.authorId != :userId')
            ->andWhere('pi.id IS NULL')
            ->andWhere('ur.id IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('archivedStatus', ThreadStatus::ARCHIVED)
            ->orderBy('t.followCount', 'DESC')
            ->addOrderBy('t.likeCount', 'DESC')
            ->addOrderBy('t.replyCount', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');
    }

    /**
     * @return ForumThread[]
     */
    public function findDuplicateRadarCandidates(?string $excludeAuthorId = null, int $limit = 60): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
            ->addSelect('c')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($excludeAuthorId !== null && $excludeAuthorId !== '') {
            $qb->andWhere('t.authorId != :authorId')
                ->setParameter('authorId', $excludeAuthorId);
        }

        return $qb->getQuery()->getResult();
    }
}
